import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:hardware_bms/data/api_client.dart';
import 'package:hardware_bms/data/models.dart';
import 'package:hardware_bms/data/sync_engine.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'helpers.dart';

void main() {
  test('network failure keeps records queued for retry and nothing is lost', () async {
    final store = await seededStore();
    await store.openDay(1);
    final p = (await store.products(shopId: 1)).first;
    await store.recordSale(shopId: 1, lines: [CartLine(p)], paymentMethod: 'cash');

    final api = ApiClient(baseUrl: 'http://x', token: 't', deviceId: 'd', client: MockClient((_) async => throw http.ClientException('offline')));
    final report = await SyncEngine(store, api).run();
    expect(report.ok, isFalse);
    final counts = await store.queueCounts();
    expect(counts['retry'], 2);
    expect(await store.unsyncedCount(), 2);
  });

  test('push sends local_uuids in order and applies acknowledgements, retry is idempotent', () async {
    final store = await seededStore();
    await store.openDay(1);
    final p = (await store.products(shopId: 1)).first;
    await store.recordSale(shopId: 1, lines: [CartLine(p, quantity: 2)], paymentMethod: 'cash');

    final seen = <String>[];
    var calls = 0;
    final client = MockClient((req) async {
      if (req.url.path.endsWith('sync/push')) {
        calls++;
        final items = (jsonDecode(req.body)['items'] as List).cast<Map<String, dynamic>>();
        seen.addAll(items.map((i) => i['local_uuid'] as String));
        expect(req.headers['X-Device-Id'], 'dev');
        return http.Response(jsonEncode({
          'results': [
            for (final i in items)
              {'entity': i['entity'], 'local_uuid': i['local_uuid'], 'status': i['entity'] == 'sale' ? 'conflict' : 'accepted', 'server_id': 1, 'message': 'Insufficient stock'},
          ],
        }), 200);
      }
      if (req.url.path.endsWith('sync/status')) {
        return http.Response(jsonEncode({'data': []}), 200);
      }
      return http.Response(jsonEncode(samplePull(cementQty: 10)..['full'] = false), 200);
    });
    final engine = SyncEngine(store, ApiClient(baseUrl: 'http://x', token: 't', deviceId: 'dev', client: client));
    final report = await engine.run();
    expect(report.accepted, 1);
    expect(report.conflicts, 1);
    expect(seen, hasLength(2));

    // Conflicts are not resent; they wait for the admin decision.
    await engine.run();
    expect(calls, 1);
    final conflict = (await store.queue(statuses: ['conflict'])).single;
    expect(conflict.error, contains('Insufficient'));
    // Stock stays reduced locally while the conflict is pending (the goods left the shop).
    expect(await store.available(1, p.id), 8);
  });

  test('401 from server bubbles up so the app asks for login, queue untouched', () async {
    final store = await seededStore();
    await store.openDay(1);
    final api = ApiClient(baseUrl: 'http://x', token: 't', deviceId: 'd', client: MockClient((_) async => http.Response('{"message":"Unauthenticated."}', 401)));
    await expectLater(SyncEngine(store, api).run(), throwsA(isA<ApiException>().having((e) => e.isUnauthorized, 'unauthorized', isTrue)));
    expect(await store.unsyncedCount(), 1);
  });
}
