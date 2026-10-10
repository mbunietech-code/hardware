// End-to-end: Flutter data layer (SQLite) ↔ running Laravel API ↔ MySQL.
// Runs only when the server answers at E2E_SERVER (default http://127.0.0.1:8765).
// It writes to that server's database, so use a development database.
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:hardware_bms/data/api_client.dart';
import 'package:hardware_bms/data/local_store.dart';
import 'package:hardware_bms/data/models.dart';
import 'package:hardware_bms/data/sync_engine.dart';
import 'package:http/http.dart' as http;

import 'helpers.dart';

void main() {
  final server = Platform.environment['E2E_SERVER'] ?? 'http://127.0.0.1:8765';

  Future<bool> reachable() async {
    try {
      final r = await http.get(Uri.parse('$server/api/v1/ping')).timeout(const Duration(seconds: 3));
      return r.statusCode == 200;
    } catch (_) {
      return false;
    }
  }

  test('offline records sync to MySQL and come back via pull', () async {
    if (!await reachable()) {
      markTestSkipped('Laravel server not running at $server');
      return;
    }
    final store = LocalStore(await memoryDb());
    final api = ApiClient(baseUrl: server, deviceId: 'e2e-${DateTime.now().millisecondsSinceEpoch}');
    final login = await api.login('shop@hardware.test', 'password', deviceName: 'e2e', platform: 'test');
    api.token = login['token'] as String;
    store.userId = login['user']['id'] as int;
    final shopId = login['user']['shop_id'] as int;
    final engine = SyncEngine(store, api);

    await engine.pull();
    final products = await store.products(shopId: shopId);
    expect(products, isNotEmpty);
    final cement = products.firstWhere((p) => p.code == 'CEM-50');

    // --- offline work ---
    final session = await store.session(shopId, today());
    if (session == null) await store.openDay(shopId, openingCash: 20000);
    if (await store.available(shopId, cement.id) < 5) {
      await store.adjustStock(shopId: shopId, product: cement, direction: 'in', quantity: 20, reason: 'E2E opening stock');
    }
    final before = await store.available(shopId, cement.id);
    final sale = await store.recordSale(shopId: shopId, lines: [CartLine(cement, quantity: 2)], paymentMethod: 'credit', amountPaid: 1000, customerName: 'E2E Customer');
    await store.recordExpense(shopId: shopId, categoryId: (await store.expenseCategories()).first['id'] as int, categoryName: 'x', amount: 1500, reason: 'E2E');

    // --- back online ---
    final report = await engine.run();
    expect(report.rejected, 0, reason: report.toString());
    expect(report.conflicts, 0, reason: report.toString());
    expect(await store.unsyncedCount(), 0);
    final synced = (await store.queue(statuses: ['synced'])).firstWhere((q) => q.localUuid == sale.uuid);
    expect(synced.reference, startsWith('S-'));

    // Server stock now reflects the sale; local view matches.
    expect(await store.available(shopId, cement.id), closeTo(before - 2, 0.001));

    // The server-created debt for the credit sale came back via pull.
    final debts = await store.debts(shopId: shopId, search: 'E2E Customer');
    expect(debts.where((d) => !d.provisional && d.serverId != null), isNotEmpty);

    // Retrying the same push must not create duplicates on the server.
    final again = await api.post('sync/push', {
      'items': [
        {'entity': 'sale', 'local_uuid': sale.uuid, 'payload': decode(synced.payload)},
      ],
    });
    expect(again['results'][0]['duplicate'], isTrue);
    expect(again['results'][0]['server_id'], synced.serverId);
  });
}
