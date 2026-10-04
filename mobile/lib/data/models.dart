/// Plain data classes used by the UI.
library;

double toDouble(Object? v) => v == null ? 0 : (v is num ? v.toDouble() : double.tryParse(v.toString()) ?? 0);

int? toInt(Object? v) => v == null ? null : (v is int ? v : int.tryParse(v.toString()));

class Product {
  Product.fromRow(Map<String, Object?> r)
    : id = r['id'] as int,
      categoryId = r['category_id'] as int?,
      code = (r['code'] ?? '') as String,
      name = r['name'] as String,
      unit = (r['unit'] ?? 'pcs') as String,
      costPrice = toDouble(r['cost_price']),
      sellingPrice = toDouble(r['selling_price']),
      reorderLevel = toDouble(r['reorder_level']),
      stock = toDouble(r['stock']),
      isActive = (r['is_active'] ?? 1) == 1;

  final int id;
  final int? categoryId;
  final String code;
  final String name;
  final String unit;
  final double costPrice;
  final double sellingPrice;
  final double reorderLevel;
  final double stock;
  final bool isActive;

  bool get isLow => reorderLevel > 0 && stock <= reorderLevel;
}

class Party {
  Party.fromRow(Map<String, Object?> r)
    : uid = r['uid'] as String,
      serverId = r['server_id'] as int?,
      name = r['name'] as String,
      phone = r['phone'] as String?;

  final String uid;
  final int? serverId;
  final String name;
  final String? phone;

  /// Reference fields for a payload: server id when known, else the local uuid.
  Map<String, Object?> ref(String prefix) => serverId != null ? {'${prefix}_id': serverId} : {'${prefix}_local_uuid': uid};
}

class Debt {
  Debt.fromRow(Map<String, Object?> r)
    : uid = r['uid'] as String,
      serverId = r['server_id'] as int?,
      shopId = r['shop_id'] as int?,
      type = (r['type'] ?? 'receivable') as String,
      partyName = (r['party_name'] ?? '') as String,
      partyPhone = r['party_phone'] as String?,
      originalAmount = toDouble(r['original_amount']),
      paidAmount = toDouble(r['paid_amount']),
      balance = toDouble(r['balance']),
      debtDate = r['debt_date'] as String?,
      dueDate = r['due_date'] as String?,
      status = (r['status'] ?? 'open') as String,
      provisional = r['provisional'] == 1;

  final String uid;
  final int? serverId;
  final int? shopId;
  final String type;
  final String partyName;
  final String? partyPhone;
  final double originalAmount;
  final double paidAmount;
  final double balance;
  final String? debtDate;
  final String? dueDate;
  final String status;
  final bool provisional;

  bool get isOutstanding => status == 'open' || status == 'partial';

  bool get isOverdue {
    if (dueDate == null || !isOutstanding) return false;
    final due = DateTime.tryParse(dueDate!);
    final today = DateTime.now();
    return due != null && due.isBefore(DateTime(today.year, today.month, today.day));
  }
}

class DailySession {
  DailySession.fromRow(Map<String, Object?> r)
    : uid = r['uid'] as String,
      serverId = r['server_id'] as int?,
      shopId = r['shop_id'] as int,
      businessDate = r['business_date'] as String,
      status = r['status'] as String,
      openingCash = toDouble(r['opening_cash']),
      localUuid = r['local_uuid'] as String?;

  final String uid;
  final int? serverId;
  final int shopId;
  final String businessDate;
  final String status;
  final double openingCash;
  final String? localUuid;

  bool get isOpen => status == 'open';
}

class QueueItem {
  QueueItem.fromRow(Map<String, Object?> r)
    : seq = r['seq'] as int,
      localUuid = r['local_uuid'] as String,
      entity = r['entity'] as String,
      payload = r['payload'] as String,
      summary = (r['summary'] ?? '') as String,
      amount = toDouble(r['amount']),
      method = r['method'] as String?,
      status = r['status'] as String,
      retries = (r['retries'] ?? 0) as int,
      error = r['error'] as String?,
      reference = r['reference'] as String?,
      serverId = r['server_id'] as int?,
      businessDate = r['business_date'] as String?,
      createdAt = DateTime.parse(r['created_at'] as String);

  final int seq;
  final String localUuid;
  final String entity;
  final String payload;
  final String summary;
  final double amount;
  final String? method;
  final String status;
  final int retries;
  final String? error;
  final String? reference;
  final int? serverId;
  final String? businessDate;
  final DateTime createdAt;

  bool get isUnsynced => status == 'pending' || status == 'retry' || status == 'syncing';
}

class CartLine {
  CartLine(this.product, {this.quantity = 1, double? price, this.discount = 0}) : price = price ?? product.sellingPrice;

  final Product product;
  double quantity;
  double price;
  double discount;

  double get total => (quantity * price - discount).clamp(0, double.infinity).toDouble();
}
