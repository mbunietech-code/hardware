import 'dart:convert';

import 'package:sqflite/sqflite.dart';
import 'package:uuid/uuid.dart';

import 'db.dart';
import 'models.dart';
import '../l10n/l10n.dart';

/// Thrown when a record fails local validation before it is saved.
class LocalValidationException implements Exception {
  LocalValidationException(this.message);
  final String message;
  @override
  String toString() => message;
}

String today() => dateOnly(DateTime.now());

String dateOnly(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

double round2(double v) => (v * 100).roundToDouble() / 100;

/// All offline reads and writes. Every user action is saved locally and queued for
/// sync in a single SQLite transaction, so nothing is lost if the app is closed.
class LocalStore {
  LocalStore(this.appDb, {Uuid? uuid}) : _uuid = uuid ?? const Uuid();

  final AppDb appDb;
  final Uuid _uuid;
  int? userId;

  Database get db => appDb.db;

  String newUuid() => _uuid.v4();

  // ---------------------------------------------------------------- settings
  Future<Map<String, dynamic>> settings() async {
    final raw = await appDb.getKv('settings');
    return raw == null ? {} : Map<String, dynamic>.from(jsonDecode(raw) as Map);
  }

  Future<bool> settingBool(String key, {bool fallback = false}) async {
    final v = (await settings())[key];
    return v == null ? fallback : (v == true || v == 1 || v == '1' || v == 'true');
  }

  // ---------------------------------------------------------------- catalog
  Future<List<Product>> products({required int shopId, String search = '', bool activeOnly = true, bool lowOnly = false}) async {
    final where = <String>[];
    final args = <Object?>[shopId, shopId];
    if (activeOnly) where.add('p.is_active = 1');
    if (search.trim().isNotEmpty) {
      where.add('(p.name LIKE ? OR p.code LIKE ?)');
      args
        ..add('%${search.trim()}%')
        ..add('%${search.trim()}%');
    }
    final rows = await db.rawQuery('''
      SELECT p.*, COALESCE(s.server_qty, 0) + COALESCE((SELECT SUM(d.delta) FROM stock_deltas d WHERE d.shop_id = ? AND d.product_id = p.id), 0) AS stock
      FROM products p LEFT JOIN stock s ON s.product_id = p.id AND s.shop_id = ?
      ${where.isEmpty ? '' : 'WHERE ${where.join(' AND ')}'}
      ORDER BY p.name''', args);
    final list = rows.map(Product.fromRow).toList();
    return lowOnly ? list.where((p) => p.isLow).toList() : list;
  }

  Future<double> available(int shopId, int productId) async {
    final r = await db.rawQuery(
      '''
      SELECT COALESCE((SELECT server_qty FROM stock WHERE shop_id = ? AND product_id = ?), 0)
           + COALESCE((SELECT SUM(delta) FROM stock_deltas WHERE shop_id = ? AND product_id = ?), 0) AS q''',
      [shopId, productId, shopId, productId],
    );
    return toDouble(r.first['q']);
  }

  Future<List<Map<String, Object?>>> categories() => db.query('categories', where: 'is_active = 1', orderBy: 'name');

  Future<List<Map<String, Object?>>> expenseCategories() => db.query('expense_categories', where: 'is_active = 1', orderBy: 'name');

  Future<List<Party>> parties(String table, {String search = ''}) async {
    final rows = await db.query(
      table,
      where: search.isEmpty ? 'is_active = 1' : 'is_active = 1 AND (name LIKE ? OR phone LIKE ?)',
      whereArgs: search.isEmpty ? null : ['%$search%', '%$search%'],
      orderBy: 'name',
    );
    return rows.map(Party.fromRow).toList();
  }

  Future<Party> addParty(String table, String name, String? phone, {Transaction? txn}) async {
    if (name.trim().isEmpty) throw LocalValidationException(tr('Name is required.'));
    final uid = newUuid();
    Future<Party> run(DatabaseExecutor ex) async {
      await ex.insert(table, {'uid': uid, 'name': name.trim(), 'phone': phone});
      await _enqueue(ex, table == 'customers' ? 'customer' : 'supplier', uid, {'name': name.trim(), 'phone': phone}, summary: name.trim());
      return Party.fromRow({'uid': uid, 'name': name.trim(), 'phone': phone});
    }

    return txn != null ? run(txn) : db.transaction(run);
  }

  // ---------------------------------------------------------------- queue
  Future<void> _enqueue(
    DatabaseExecutor ex,
    String entity,
    String uuid,
    Map<String, Object?> payload, {
    int? shopId,
    String? businessDate,
    String summary = '',
    double amount = 0,
    String? method,
  }) async {
    final now = DateTime.now().toIso8601String();
    payload['client_created_at'] = now;
    await ex.insert('sync_queue', {
      'local_uuid': uuid,
      'entity': entity,
      'payload': jsonEncode(payload),
      'user_id': userId,
      'shop_id': shopId,
      'business_date': businessDate,
      'summary': summary,
      'amount': amount,
      'method': method,
      'status': 'pending',
      'created_at': now,
      'updated_at': now,
    });
  }

  Future<int> unsyncedCount() async {
    final r = await db.rawQuery("SELECT COUNT(*) c FROM sync_queue WHERE status IN ('pending','retry','syncing')");
    return (r.first['c'] as int?) ?? 0;
  }

  Future<Map<String, int>> queueCounts() async {
    final rows = await db.rawQuery('SELECT status, COUNT(*) c FROM sync_queue GROUP BY status');
    return {for (final r in rows) r['status'] as String: r['c'] as int};
  }

  Future<List<QueueItem>> queue({List<String>? statuses, int limit = 200}) async {
    final rows = await db.query(
      'sync_queue',
      where: statuses == null ? null : 'status IN (${List.filled(statuses.length, '?').join(',')})',
      whereArgs: statuses,
      orderBy: 'seq DESC',
      limit: limit,
    );
    return rows.map(QueueItem.fromRow).toList();
  }

  Future<List<QueueItem>> activity({required int shopId, required String date, List<String>? entities}) async {
    final rows = await db.query(
      'sync_queue',
      where: 'shop_id = ? AND business_date = ?${entities == null ? '' : ' AND entity IN (${List.filled(entities.length, '?').join(',')})'}',
      whereArgs: [shopId, date, ...?entities],
      orderBy: 'seq DESC',
    );
    return rows.map(QueueItem.fromRow).toList();
  }

  /// Items ready to push for the logged-in user, oldest first so dependencies sync first.
  Future<List<QueueItem>> pushable({int limit = 100}) async {
    final rows = await db.query(
      'sync_queue',
      where: "status IN ('pending','retry','syncing') AND (user_id IS NULL OR user_id = ?)",
      whereArgs: [userId],
      orderBy: 'seq ASC',
      limit: limit,
    );
    return rows.map(QueueItem.fromRow).toList();
  }

  Future<void> retryRejected(String uuid) => db.update(
    'sync_queue',
    {'status': 'pending', 'error': null, 'updated_at': DateTime.now().toIso8601String()},
    where: 'local_uuid = ?',
    whereArgs: [uuid],
  );

  // ---------------------------------------------------------------- daily session
  Future<DailySession?> session(int shopId, String date) async {
    final rows = await db.query('daily_sessions', where: 'shop_id = ? AND business_date = ?', whereArgs: [shopId, date]);
    return rows.isEmpty ? null : DailySession.fromRow(rows.first);
  }

  Future<DailySession> openDay(int shopId, {double openingCash = 0, String? notes}) async {
    final date = today();
    final existing = await session(shopId, date);
    if (existing != null) {
      if (existing.isOpen) return existing;
      throw LocalValidationException(tr('Today is already closed for this shop.'));
    }
    final uuid = newUuid();
    await db.transaction((txn) async {
      await txn.insert('daily_sessions', {
        'uid': uuid,
        'shop_id': shopId,
        'business_date': date,
        'status': 'open',
        'opening_cash': openingCash,
        'local_uuid': uuid,
      });
      await _enqueue(
        txn,
        'daily_session_open',
        uuid,
        {'shop_id': shopId, 'business_date': date, 'opening_cash': openingCash, 'opening_notes': notes},
        shopId: shopId,
        businessDate: date,
        summary: tr('Opened day'),
        amount: openingCash,
      );
    });
    return (await session(shopId, date))!;
  }

  /// Local totals for a day, computed from what this device recorded (server recomputes on close).
  Future<Map<String, double>> dayTotals(int shopId, String date) async {
    final rows = await db.rawQuery(
      '''
      SELECT entity, method, SUM(amount) total, COUNT(*) c FROM sync_queue
      WHERE shop_id = ? AND business_date = ? AND status != 'rejected' GROUP BY entity, method''',
      [shopId, date],
    );
    final t = <String, double>{
      'sales_total': 0,
      'sales_count': 0,
      'cash_sales': 0,
      'purchases_total': 0,
      'expenses_total': 0,
      'cash_out': 0,
      'debt_payments': 0,
    };
    for (final r in rows) {
      final amount = toDouble(r['total']);
      switch (r['entity']) {
        case 'sale':
          t['sales_total'] = t['sales_total']! + amount;
          t['sales_count'] = t['sales_count']! + toDouble(r['c']);
          if (r['method'] == 'cash') t['cash_sales'] = t['cash_sales']! + amount;
        case 'purchase':
          t['purchases_total'] = t['purchases_total']! + amount;
          if (r['method'] == 'cash') t['cash_out'] = t['cash_out']! + amount;
        case 'expense':
          t['expenses_total'] = t['expenses_total']! + amount;
          if (r['method'] == 'cash') t['cash_out'] = t['cash_out']! + amount;
        case 'debt_payment':
          t['debt_payments'] = t['debt_payments']! + amount;
      }
    }
    return t;
  }

  Future<void> closeDay(DailySession s, {double? closingCash, String? notes, String? exceptions}) async {
    if (!s.isOpen) throw LocalValidationException(tr('This day is already closed.'));
    final totals = await dayTotals(s.shopId, s.businessDate);
    final uuid = newUuid();
    await db.transaction((txn) async {
      await txn.update(
        'daily_sessions',
        {'status': 'closed', 'closing_cash': closingCash, 'totals': jsonEncode(totals)},
        where: 'uid = ?',
        whereArgs: [s.uid],
      );
      await _enqueue(
        txn,
        'daily_session_close',
        uuid,
        {
          'shop_id': s.shopId,
          'business_date': s.businessDate,
          'session_local_uuid': s.localUuid,
          'closing_cash': closingCash,
          'closing_notes': notes,
          'exceptions': exceptions,
          'client_totals': totals,
        },
        shopId: s.shopId,
        businessDate: s.businessDate,
        summary: tr('Closed day'),
      );
    });
  }

  Future<void> _requireOpenDay(int shopId, String date) async {
    if (!await settingBool('require_open_day', fallback: true)) return;
    final s = await session(shopId, date);
    if (s == null) throw LocalValidationException(tr('Open the business day before recording transactions.'));
    if (!s.isOpen) throw LocalValidationException(tr('Today is closed. Ask the Super Admin to reopen it.'));
  }

  // ---------------------------------------------------------------- sales
  Future<({String uuid, double total, double balance, List<String> warnings})> recordSale({
    required int shopId,
    required List<CartLine> lines,
    required String paymentMethod,
    double discount = 0,
    double? amountPaid,
    Party? customer,
    String? customerName,
    String? customerPhone,
    String? notes,
  }) async {
    final date = today();
    await _requireOpenDay(shopId, date);
    if (lines.isEmpty) throw LocalValidationException(tr('Add at least one product.'));
    final warnings = <String>[];
    final blockNegative = ((await settings())['negative_stock_policy'] ?? 'block') == 'block';
    final belowCost = (await settings())['sell_below_cost_policy'] ?? 'warn';

    final perProduct = <int, double>{};
    for (final l in lines) {
      if (l.quantity <= 0) throw LocalValidationException(tr('Quantity for {product} must be more than zero.', {'product': l.product.name}));
      if (l.price < 0 || l.discount < 0) throw LocalValidationException(tr('Prices and discounts cannot be negative.'));
      if (l.discount > l.quantity * l.price) {
        throw LocalValidationException(tr('Discount on {product} is larger than the line amount.', {'product': l.product.name}));
      }
      perProduct[l.product.id] = (perProduct[l.product.id] ?? 0) + l.quantity;
      if (l.product.costPrice > 0 && l.price < l.product.costPrice) {
        final msg = tr('{product} is below cost price.', {'product': l.product.name});
        if (belowCost == 'block') throw LocalValidationException(msg);
        if (belowCost == 'warn') warnings.add(msg);
      }
    }
    if (blockNegative) {
      for (final e in perProduct.entries) {
        final avail = await available(shopId, e.key);
        if (avail < e.value) {
          final name = lines.firstWhere((l) => l.product.id == e.key).product.name;
          throw LocalValidationException(
            tr('Not enough stock for {product}: available {available}, requested {requested}.', {
              'product': name,
              'available': _q(avail),
              'requested': _q(e.value),
            }),
          );
        }
      }
    }

    final subtotal = round2(lines.fold(0.0, (s, l) => s + l.total));
    final total = round2(subtotal - discount);
    if (total < 0) throw LocalValidationException(tr('Discount is larger than the subtotal.'));
    final paid = round2(amountPaid ?? (paymentMethod == 'credit' ? 0 : total));
    if (paid > total) throw LocalValidationException(tr('Amount paid cannot be more than the total.'));
    final balance = round2(total - paid);
    final hasCustomer = customer != null || (customerName != null && customerName.trim().isNotEmpty);
    if (balance > 0 && !hasCustomer) throw LocalValidationException(tr('Choose a customer for an unpaid or credit sale.'));

    final autoDebt = await settingBool('auto_debt_from_credit', fallback: true);
    final uuid = newUuid();
    // Note: inside the transaction only `txn` may be used, never `db`, or SQLite deadlocks.
    await db.transaction((txn) async {
      Party? c = customer;
      if (c == null && hasCustomer) c = await addParty('customers', customerName!, customerPhone, txn: txn);
      await _enqueue(
        txn,
        'sale',
        uuid,
        {
          'shop_id': shopId,
          'sale_date': date,
          'payment_method': paymentMethod,
          'amount_paid': paid,
          'discount': discount,
          'notes': notes,
          ...?c?.ref('customer'),
          'items': [
            for (final l in lines) {'product_id': l.product.id, 'quantity': l.quantity, 'unit_price': l.price, 'discount': l.discount},
          ],
        },
        shopId: shopId,
        businessDate: date,
        summary: tr('{n} item(s)', {'n': lines.length}) + (c != null ? ' · ${c.name}' : ''),
        amount: total,
        method: paymentMethod,
      );
      for (final l in lines) {
        await txn.insert('stock_deltas', {'txn_uuid': uuid, 'shop_id': shopId, 'product_id': l.product.id, 'delta': -l.quantity});
      }
      if (balance > 0 && c != null && autoDebt) {
        await txn.insert('debts', {
          'uid': '$uuid-debt',
          'shop_id': shopId,
          'type': 'receivable',
          'party_name': c.name,
          'party_phone': c.phone,
          'original_amount': balance,
          'paid_amount': 0,
          'balance': balance,
          'debt_date': date,
          'status': 'open',
          'provisional': 1,
          'source_uuid': uuid,
        });
      }
    });
    return (uuid: uuid, total: total, balance: balance, warnings: warnings);
  }

  // ---------------------------------------------------------------- purchases
  Future<String> recordPurchase({
    required int shopId,
    required List<CartLine> lines,
    required String paymentMethod,
    double? amountPaid,
    Party? supplier,
    String? supplierName,
    String? invoiceNumber,
    String? notes,
  }) async {
    final date = today();
    await _requireOpenDay(shopId, date);
    if (lines.isEmpty) throw LocalValidationException(tr('Add at least one product.'));
    for (final l in lines) {
      if (l.quantity <= 0) throw LocalValidationException(tr('Quantity for {product} must be more than zero.', {'product': l.product.name}));
      if (l.price < 0) throw LocalValidationException(tr('Unit cost cannot be negative.'));
    }
    final total = round2(lines.fold(0.0, (s, l) => s + l.quantity * l.price));
    final paid = round2(amountPaid ?? (paymentMethod == 'credit' ? 0 : total));
    if (paid > total) throw LocalValidationException(tr('Amount paid cannot be more than the total.'));
    final hasSupplier = supplier != null || (supplierName != null && supplierName.trim().isNotEmpty);
    if (total - paid > 0 && !hasSupplier) throw LocalValidationException(tr('Choose a supplier for an unpaid purchase.'));

    final uuid = newUuid();
    await db.transaction((txn) async {
      Party? s = supplier;
      if (s == null && hasSupplier) s = await addParty('suppliers', supplierName!, null, txn: txn);
      await _enqueue(
        txn,
        'purchase',
        uuid,
        {
          'shop_id': shopId,
          'purchase_date': date,
          'payment_method': paymentMethod,
          'amount_paid': paid,
          'invoice_number': invoiceNumber,
          'notes': notes,
          ...?s?.ref('supplier'),
          'items': [
            for (final l in lines) {'product_id': l.product.id, 'quantity': l.quantity, 'unit_cost': l.price},
          ],
        },
        shopId: shopId,
        businessDate: date,
        summary: tr('{n} item(s)', {'n': lines.length}) + (s != null ? ' · ${s.name}' : ''),
        amount: total,
        method: paymentMethod,
      );
      for (final l in lines) {
        await txn.insert('stock_deltas', {'txn_uuid': uuid, 'shop_id': shopId, 'product_id': l.product.id, 'delta': l.quantity});
      }
    });
    return uuid;
  }

  // ---------------------------------------------------------------- expenses / capital
  Future<String> recordExpense({
    required int shopId,
    required int categoryId,
    required String categoryName,
    required double amount,
    required String reason,
    String paymentMethod = 'cash',
  }) async {
    final date = today();
    await _requireOpenDay(shopId, date);
    if (amount <= 0) throw LocalValidationException(tr('Amount must be more than zero.'));
    if (reason.trim().isEmpty) throw LocalValidationException(tr('Enter the reason for the expense.'));
    final uuid = newUuid();
    await db.transaction(
      (txn) => _enqueue(
        txn,
        'expense',
        uuid,
        {
          'shop_id': shopId,
          'expense_category_id': categoryId,
          'expense_date': date,
          'amount': amount,
          'payment_method': paymentMethod,
          'reason': reason.trim(),
        },
        shopId: shopId,
        businessDate: date,
        summary: '$categoryName · ${reason.trim()}',
        amount: amount,
        method: paymentMethod,
      ),
    );
    return uuid;
  }

  Future<String> recordCapital({required int shopId, required String type, required double amount, required String reason}) async {
    final date = today();
    await _requireOpenDay(shopId, date);
    if (amount <= 0) throw LocalValidationException(tr('Amount must be more than zero.'));
    if (reason.trim().isEmpty) throw LocalValidationException(tr('Enter a reason.'));
    final uuid = newUuid();
    await db.transaction(
      (txn) => _enqueue(
        txn,
        'capital_entry',
        uuid,
        {'shop_id': shopId, 'type': type, 'amount': amount, 'entry_date': date, 'reason': reason.trim(), 'payment_method': 'cash'},
        shopId: shopId,
        businessDate: date,
        summary: '${tr('Capital')} ${tr(type)} · ${reason.trim()}',
        amount: amount,
        method: 'cash',
      ),
    );
    return uuid;
  }

  // ---------------------------------------------------------------- debts
  Future<List<Debt>> debts({required int shopId, bool outstandingOnly = true, String search = ''}) async {
    final where = ['shop_id = ?'];
    final args = <Object?>[shopId];
    if (outstandingOnly) where.add("status IN ('open','partial')");
    if (search.isNotEmpty) {
      where.add('party_name LIKE ?');
      args.add('%$search%');
    }
    final rows = await db.query('debts', where: where.join(' AND '), whereArgs: args, orderBy: 'due_date IS NULL, due_date, party_name');
    return rows.map(Debt.fromRow).toList();
  }

  Future<String> recordDebt({
    required int shopId,
    required String type,
    required String partyName,
    String? partyPhone,
    required double amount,
    String? dueDate,
    String? notes,
  }) async {
    final date = today();
    await _requireOpenDay(shopId, date);
    if (partyName.trim().isEmpty) throw LocalValidationException(tr('Enter who owes or is owed.'));
    if (amount <= 0) throw LocalValidationException(tr('Amount must be more than zero.'));
    final uuid = newUuid();
    await db.transaction((txn) async {
      await txn.insert('debts', {
        'uid': uuid,
        'shop_id': shopId,
        'type': type,
        'party_name': partyName.trim(),
        'party_phone': partyPhone,
        'original_amount': amount,
        'paid_amount': 0,
        'balance': amount,
        'debt_date': date,
        'due_date': dueDate,
        'status': 'open',
      });
      await _enqueue(
        txn,
        'debt',
        uuid,
        {
          'shop_id': shopId,
          'type': type,
          'party_name': partyName.trim(),
          'party_phone': partyPhone,
          'amount': amount,
          'debt_date': date,
          'due_date': dueDate,
          'notes': notes,
        },
        shopId: shopId,
        businessDate: date,
        summary: '${tr('Debt')} · ${partyName.trim()}',
        amount: amount,
      );
    });
    return uuid;
  }

  Future<String> recordDebtPayment(Debt debt, {required double amount, String method = 'cash', String? notes}) async {
    final date = today();
    await _requireOpenDay(debt.shopId!, date);
    if (debt.provisional) throw LocalValidationException(tr('This credit sale has not synced yet. Sync first, then record the payment.'));
    if (!debt.isOutstanding) throw LocalValidationException(tr('This debt is already settled.'));
    if (amount <= 0) throw LocalValidationException(tr('Amount must be more than zero.'));
    if (amount > debt.balance + 0.001) throw LocalValidationException(tr('Payment is more than the outstanding balance.'));
    final uuid = newUuid();
    final balance = round2(debt.balance - amount);
    await db.transaction((txn) async {
      await txn.update(
        'debts',
        {'paid_amount': debt.paidAmount + amount, 'balance': balance, 'status': balance <= 0 ? 'paid' : 'partial'},
        where: 'uid = ?',
        whereArgs: [debt.uid],
      );
      await _enqueue(
        txn,
        'debt_payment',
        uuid,
        {
          if (debt.serverId != null) 'debt_id': debt.serverId else 'debt_local_uuid': debt.uid,
          'amount': amount,
          'payment_date': date,
          'payment_method': method,
          'notes': notes,
        },
        shopId: debt.shopId,
        businessDate: date,
        summary: '${tr('Payment')} · ${debt.partyName} (${tr(debt.type)})',
        amount: amount,
        method: method,
      );
    });
    return uuid;
  }

  // ---------------------------------------------------------------- stock
  Future<String> adjustStock({
    required int shopId,
    required Product product,
    required String direction,
    required double quantity,
    required String reason,
  }) async {
    if (quantity <= 0) throw LocalValidationException(tr('Quantity must be more than zero.'));
    if (reason.trim().isEmpty) throw LocalValidationException(tr('Enter the reason for the adjustment.'));
    final uuid = newUuid();
    await db.transaction((txn) async {
      await _enqueue(
        txn,
        'stock_adjustment',
        uuid,
        {'shop_id': shopId, 'product_id': product.id, 'direction': direction, 'quantity': quantity, 'reason': reason.trim()},
        shopId: shopId,
        businessDate: today(),
        summary: '${direction == 'in' ? '+' : '−'}${_q(quantity)} ${product.name} · ${reason.trim()}',
      );
      await txn.insert('stock_deltas', {
        'txn_uuid': uuid,
        'shop_id': shopId,
        'product_id': product.id,
        'delta': direction == 'in' ? quantity : -quantity,
      });
    });
    return uuid;
  }

  // ---------------------------------------------------------------- sync results
  Future<void> markSyncing(List<String> uuids) async {
    if (uuids.isEmpty) return;
    await db.update('sync_queue', {'status': 'syncing'}, where: 'local_uuid IN (${List.filled(uuids.length, '?').join(',')})', whereArgs: uuids);
  }

  /// Apply one server acknowledgement to the local queue.
  Future<void> applyResult(Map<String, dynamic> r) async {
    final uuid = r['local_uuid'] as String?;
    final entity = r['entity'] as String?;
    if (uuid == null || entity == null) return;
    final status = r['status'] as String? ?? 'retry';
    final now = DateTime.now().toIso8601String();
    await db.transaction((txn) async {
      final values = <String, Object?>{'status': status == 'accepted' ? 'synced' : status, 'updated_at': now};
      switch (status) {
        case 'accepted':
          values.addAll({'server_id': r['server_id'], 'reference': r['reference'], 'error': null});
          await txn.delete('stock_deltas', where: 'txn_uuid = ?', whereArgs: [uuid]);
          await txn.delete('debts', where: 'source_uuid = ? AND provisional = 1', whereArgs: [uuid]);
          if (entity == 'customer' || entity == 'supplier') {
            await txn.update(entity == 'customer' ? 'customers' : 'suppliers', {'server_id': r['server_id']}, where: 'uid = ?', whereArgs: [uuid]);
          } else if (entity == 'debt') {
            await txn.update('debts', {'server_id': r['server_id']}, where: 'uid = ?', whereArgs: [uuid]);
          } else if (entity == 'daily_session_open') {
            await txn.update('daily_sessions', {'server_id': r['server_id']}, where: 'uid = ?', whereArgs: [uuid]);
          }
        case 'rejected':
          values['error'] = r['message'] ?? tr('Rejected by server');
          // The server refused the record, so undo its local stock effect.
          await txn.delete('stock_deltas', where: 'txn_uuid = ?', whereArgs: [uuid]);
          await txn.delete('debts', where: 'source_uuid = ? AND provisional = 1', whereArgs: [uuid]);
        case 'conflict':
          values['error'] = r['message'] ?? tr('Conflict – waiting for admin review');
        default:
          values['status'] = 'retry';
          values['error'] = r['message'] ?? tr('Will retry');
          await txn.rawUpdate('UPDATE sync_queue SET retries = retries + 1 WHERE local_uuid = ?', [uuid]);
      }
      await txn.update('sync_queue', values, where: 'local_uuid = ? AND entity = ?', whereArgs: [uuid, entity]);
    });
  }

  Future<void> markRetry(List<String> uuids, String error) async {
    if (uuids.isEmpty) return;
    await db.rawUpdate(
      "UPDATE sync_queue SET status = 'retry', retries = retries + 1, error = ?, updated_at = ? "
      'WHERE local_uuid IN (${List.filled(uuids.length, '?').join(',')})',
      [error, DateTime.now().toIso8601String(), ...uuids],
    );
  }

  /// Merge data pulled from the server (sync/pull) into the local cache.
  Future<void> applyPull(Map<String, dynamic> data) async {
    List<Map<String, dynamic>> list(String k) => ((data[k] as List?) ?? const []).cast<Map<String, dynamic>>();
    int b(Object? v) => (v == true || v == 1) ? 1 : 0;

    await db.transaction((txn) async {
      final full = data['full'] == true;
      if (full) {
        for (final t in ['shops', 'categories', 'expense_categories', 'products']) {
          await txn.delete(t);
        }
      }
      for (final s in list('shops')) {
        await txn.insert('shops', {
          'id': s['id'],
          'name': s['name'],
          'code': s['code'],
          'location': s['location'],
          'phone': s['phone'],
          'is_active': b(s['is_active']),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }
      for (final t in ['categories', 'expense_categories']) {
        for (final c in list(t)) {
          await txn.insert(t, {'id': c['id'], 'name': c['name'], 'is_active': b(c['is_active'])}, conflictAlgorithm: ConflictAlgorithm.replace);
        }
      }
      for (final p in list('products')) {
        await txn.insert('products', {
          'id': p['id'],
          'category_id': p['category_id'],
          'code': p['code'],
          'name': p['name'],
          'unit': p['unit'],
          'cost_price': toDouble(p['cost_price']),
          'selling_price': toDouble(p['selling_price']),
          'reorder_level': toDouble(p['reorder_level']),
          'is_active': b(p['is_active']),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }
      for (final t in ['customers', 'suppliers']) {
        for (final c in list(t)) {
          final localUuid = c['local_uuid'] as String?;
          final mine = localUuid == null
              ? 0
              : (await txn.update(
                  t,
                  {'server_id': c['id'], 'name': c['name'], 'phone': c['phone'], 'is_active': b(c['is_active'])},
                  where: 'uid = ?',
                  whereArgs: [localUuid],
                ));
          if (mine == 0) {
            await txn.delete(t, where: 'server_id = ? AND uid != ?', whereArgs: [c['id'], 'srv:${c['id']}']);
            await txn.insert(t, {
              'uid': 'srv:${c['id']}',
              'server_id': c['id'],
              'name': c['name'],
              'phone': c['phone'],
              'is_active': b(c['is_active']),
            }, conflictAlgorithm: ConflictAlgorithm.replace);
          }
        }
      }
      for (final s in list('stock')) {
        await txn.insert('stock', {
          'shop_id': s['shop_id'],
          'product_id': s['product_id'],
          'server_qty': toDouble(s['quantity']),
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }
      for (final d in list('debts')) {
        final uid = (d['local_uuid'] as String?) ?? 'srv:${d['id']}';
        // Skip server copies of debts that still have unsynced local payments, so the local balance is not reset.
        final pendingPayments = await txn.rawQuery(
          "SELECT COUNT(*) c FROM sync_queue WHERE entity = 'debt_payment' AND status IN ('pending','retry','syncing') "
          'AND (payload LIKE ? OR payload LIKE ?)',
          ['%"debt_id":${d['id']},%', '%"debt_local_uuid":"$uid"%'],
        );
        if (((pendingPayments.first['c'] as int?) ?? 0) > 0) continue;
        await txn.delete('debts', where: 'server_id = ? AND uid != ?', whereArgs: [d['id'], uid]);
        await txn.insert('debts', {
          'uid': uid,
          'server_id': d['id'],
          'shop_id': d['shop_id'],
          'type': d['type'],
          'party_name': d['party_name'],
          'party_phone': d['party_phone'],
          'original_amount': toDouble(d['original_amount']),
          'paid_amount': toDouble(d['paid_amount']),
          'balance': toDouble(d['balance']),
          'debt_date': d['debt_date'],
          'due_date': d['due_date'],
          'status': d['status'],
          'provisional': 0,
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }
      for (final s in list('daily_sessions')) {
        final date = (s['business_date'] as String).substring(0, 10);
        final local = await txn.query('daily_sessions', where: 'shop_id = ? AND business_date = ?', whereArgs: [s['shop_id'], date]);
        final pendingClose = await txn.rawQuery(
          "SELECT COUNT(*) c FROM sync_queue WHERE entity = 'daily_session_close' AND business_date = ? AND shop_id = ? AND status IN ('pending','retry','syncing','conflict')",
          [date, s['shop_id']],
        );
        final values = {
          'server_id': s['id'], 'shop_id': s['shop_id'], 'business_date': date,
          // A close recorded on this device wins locally until it has synced.
          'status': ((pendingClose.first['c'] as int?) ?? 0) > 0 ? 'closed' : s['status'],
          'opening_cash': toDouble(s['opening_cash']), 'expected_cash': s['expected_cash'] == null ? null : toDouble(s['expected_cash']),
          'totals': s['totals'] == null ? null : jsonEncode(s['totals']),
        };
        if (local.isEmpty) {
          await txn.insert('daily_sessions', {...values, 'uid': (s['local_uuid'] as String?) ?? 'srv:${s['id']}', 'local_uuid': s['local_uuid']});
        } else {
          await txn.update('daily_sessions', values, where: 'uid = ?', whereArgs: [local.first['uid']]);
        }
      }
      await txn.delete('notifications');
      for (final n in list('notifications')) {
        await txn.insert('notifications', {
          'id': n['id'],
          'type': n['type'],
          'title': n['title'],
          'message': n['message'],
          'created_at': n['created_at'],
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }
      if (data['settings'] is Map) {
        await txn.insert('kv', {'key': 'settings', 'value': jsonEncode(data['settings'])}, conflictAlgorithm: ConflictAlgorithm.replace);
      }
      if (data['server_time'] != null) {
        await txn.insert('kv', {'key': 'last_pull', 'value': data['server_time']}, conflictAlgorithm: ConflictAlgorithm.replace);
      }
    });
  }

  Future<List<Map<String, Object?>>> notifications() => db.query('notifications', orderBy: 'created_at DESC');

  Future<List<Map<String, Object?>>> shops() => db.query('shops', where: 'is_active = 1', orderBy: 'name');

  /// Remove synced history older than [days] days to keep the local database small.
  Future<void> purgeHistory({int days = 45}) async {
    final cutoff = DateTime.now().subtract(Duration(days: days)).toIso8601String();
    await db.delete('sync_queue', where: "status = 'synced' AND created_at < ?", whereArgs: [cutoff]);
  }

  static String _q(double v) => v == v.roundToDouble() ? v.toStringAsFixed(0) : v.toString();
}
