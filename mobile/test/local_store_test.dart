import 'package:flutter_test/flutter_test.dart';
import 'package:hardware_bms/data/local_store.dart';
import 'package:hardware_bms/data/models.dart';

import 'helpers.dart';

void main() {
  late LocalStore store;
  late List<Product> products;

  setUp(() async {
    store = await seededStore();
    products = await store.products(shopId: 1);
  });

  Product cement() => products.firstWhere((p) => p.code == 'CEM-50');

  test('pull caches catalogue and stock', () async {
    expect(products, hasLength(2));
    expect(cement().stock, 10);
    expect(cement().sellingPrice, 19000);
  });

  test('transactions need an open day', () async {
    expect(
      () => store.recordSale(shopId: 1, lines: [CartLine(cement())], paymentMethod: 'cash'),
      throwsA(isA<LocalValidationException>()),
    );
  });

  test('offline sale is queued, reduces local stock and keeps a stable local_uuid', () async {
    await store.openDay(1, openingCash: 5000);
    final r = await store.recordSale(shopId: 1, lines: [CartLine(cement(), quantity: 3)], paymentMethod: 'cash');
    expect(r.total, 57000);
    expect(await store.available(1, cement().id), 7);

    final queue = await store.pushable();
    expect(queue.map((q) => q.entity), ['daily_session_open', 'sale']);
    final payload = decode(queue.last.payload);
    expect(queue.last.localUuid, r.uuid);
    expect(payload['items'][0]['quantity'], 3);
    expect(payload['client_created_at'], isNotNull);
  });

  test('cannot sell more than available stock when policy blocks', () async {
    await store.openDay(1);
    expect(
      () => store.recordSale(shopId: 1, lines: [CartLine(cement(), quantity: 11)], paymentMethod: 'cash'),
      throwsA(isA<LocalValidationException>().having((e) => e.message, 'message', contains('Not enough stock'))),
    );
  });

  test('credit sale needs a customer and creates a provisional debt plus new customer', () async {
    await store.openDay(1);
    expect(
      () => store.recordSale(shopId: 1, lines: [CartLine(cement())], paymentMethod: 'credit'),
      throwsA(isA<LocalValidationException>()),
    );
    final r = await store.recordSale(shopId: 1, lines: [CartLine(cement(), quantity: 2)], paymentMethod: 'credit', amountPaid: 8000, customerName: 'Juma');
    expect(r.balance, 30000);

    final debts = await store.debts(shopId: 1);
    expect(debts.single.provisional, isTrue);
    expect(debts.single.balance, 30000);

    final queue = await store.pushable();
    expect(queue.map((q) => q.entity), ['daily_session_open', 'customer', 'sale']);
    expect(decode(queue.last.payload)['customer_local_uuid'], queue[1].localUuid);

    // Paying a provisional debt is blocked until it syncs.
    expect(() => store.recordDebtPayment(debts.single, amount: 100), throwsA(isA<LocalValidationException>()));
  });

  test('accepted result clears local deltas; rejected result reverts stock', () async {
    await store.openDay(1);
    final ok = await store.recordSale(shopId: 1, lines: [CartLine(cement(), quantity: 2)], paymentMethod: 'cash');
    final bad = await store.recordSale(shopId: 1, lines: [CartLine(cement(), quantity: 1)], paymentMethod: 'cash');
    expect(await store.available(1, cement().id), 7);

    await store.applyResult({'entity': 'sale', 'local_uuid': ok.uuid, 'status': 'accepted', 'server_id': 10, 'reference': 'S-MAIN-000010'});
    await store.applyResult({'entity': 'sale', 'local_uuid': bad.uuid, 'status': 'rejected', 'message': 'Product inactive'});
    // Accepted delta is now part of the server quantity (refreshed on next pull); rejected one is undone.
    expect(await store.available(1, cement().id), 10);

    final counts = await store.queueCounts();
    expect(counts['synced'], 1);
    expect(counts['rejected'], 1);
    expect((await store.queue(statuses: ['synced'])).single.reference, 'S-MAIN-000010');
  });

  test('day totals and closing', () async {
    final session = await store.openDay(1, openingCash: 10000);
    await store.recordSale(shopId: 1, lines: [CartLine(cement(), quantity: 1)], paymentMethod: 'cash');
    await store.recordExpense(shopId: 1, categoryId: 1, categoryName: 'Rent', amount: 2000, reason: 'Tea');
    final t = await store.dayTotals(1, today());
    expect(t['sales_total'], 19000);
    expect(t['cash_out'], 2000);

    await store.closeDay(session, closingCash: 27000);
    expect((await store.session(1, today()))!.isOpen, isFalse);
    final close = (await store.pushable()).last;
    expect(close.entity, 'daily_session_close');
    expect(decode(close.payload)['session_local_uuid'], session.localUuid);
    expect(() => store.recordExpense(shopId: 1, categoryId: 1, categoryName: 'Rent', amount: 1, reason: 'x'), throwsA(isA<LocalValidationException>()));
  });

  test('debt payment reduces local balance and is queued against the server id', () async {
    await store.applyPull({
      ...samplePull(),
      'full': false,
      'debts': [
        {'id': 7, 'shop_id': 1, 'type': 'receivable', 'party_name': 'Asha', 'original_amount': '5000', 'paid_amount': '0', 'balance': '5000', 'status': 'open'},
      ],
    });
    await store.openDay(1);
    final debt = (await store.debts(shopId: 1)).single;
    expect(() => store.recordDebtPayment(debt, amount: 6000), throwsA(isA<LocalValidationException>()));
    await store.recordDebtPayment(debt, amount: 2000);
    final updated = (await store.debts(shopId: 1)).single;
    expect(updated.balance, 3000);
    expect(updated.status, 'partial');
    expect(decode((await store.pushable()).last.payload)['debt_id'], 7);

    // A pull before the payment syncs must not overwrite the local balance.
    await store.applyPull({...samplePull(), 'full': false, 'debts': [
      {'id': 7, 'shop_id': 1, 'type': 'receivable', 'party_name': 'Asha', 'original_amount': '5000', 'paid_amount': '0', 'balance': '5000', 'status': 'open'},
    ]});
    expect((await store.debts(shopId: 1)).single.balance, 3000);
  });
}
