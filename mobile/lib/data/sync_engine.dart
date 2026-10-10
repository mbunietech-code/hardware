import 'dart:convert';

import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'local_store.dart';
import '../l10n/l10n.dart';

class SyncReport {
  SyncReport({this.pushed = 0, this.accepted = 0, this.rejected = 0, this.conflicts = 0, this.retry = 0, this.error});

  int pushed;
  int accepted;
  int rejected;
  int conflicts;
  int retry;
  String? error;

  bool get ok => error == null;

  @override
  String toString() =>
      error ?? tr('Sent {n} · accepted {a} · rejected {r} · conflicts {c}', {'n': pushed, 'a': accepted, 'r': rejected, 'c': conflicts});
}

/// Pushes the local queue to the server and pulls fresh reference data (Doc 09):
/// pending → syncing → synced | rejected | conflict | retry. A failed push never
/// deletes local data; the same local_uuid is resent so the server can de-duplicate.
class SyncEngine {
  SyncEngine(this.store, this.api);

  final LocalStore store;
  final ApiClient api;
  bool _running = false;

  bool get running => _running;

  Future<SyncReport> run({bool pull = true}) async {
    if (_running) return SyncReport(error: tr('Sync already running'));
    _running = true;
    final report = SyncReport();
    try {
      await _push(report);
      await _refreshConflicts();
      if (pull) await this.pull();
      await store.purgeHistory();
    } on ApiException catch (e) {
      report.error = e.message;
      if (e.isUnauthorized) rethrow;
    } finally {
      _running = false;
    }
    return report;
  }

  Future<void> _push(SyncReport report) async {
    // Push in batches until the queue is drained (bounded to avoid endless loops).
    for (var round = 0; round < 20; round++) {
      final batch = await store.pushable(limit: 100);
      if (batch.isEmpty) return;
      final uuids = batch.map((i) => i.localUuid).toList();
      await store.markSyncing(uuids);
      Map<String, dynamic> res;
      try {
        res = await api.post('sync/push', {
          'items': [
            for (final i in batch) {'entity': i.entity, 'local_uuid': i.localUuid, 'payload': _decode(i.payload)},
          ],
        });
      } on ApiException catch (e) {
        await store.markRetry(uuids, e.message);
        rethrow;
      }
      final results = ((res['results'] as List?) ?? const []).cast<Map<String, dynamic>>();
      final answered = <String>{};
      var progressed = 0;
      for (final r in results) {
        answered.add(r['local_uuid'] as String? ?? '');
        await store.applyResult(r);
        report.pushed++;
        if (r['status'] != 'retry') progressed++;
        switch (r['status']) {
          case 'accepted':
            report.accepted++;
          case 'rejected':
            report.rejected++;
          case 'conflict':
            report.conflicts++;
          default:
            report.retry++;
        }
      }
      final missing = uuids.where((u) => !answered.contains(u)).toList();
      if (missing.isNotEmpty) await store.markRetry(missing, tr('No answer from server'));
      // Stop if nothing progressed this round (everything is retrying).
      if (progressed == 0) return;
    }
  }

  /// Conflicts are decided by a Super Admin on the web; ask the server for the outcome.
  Future<void> _refreshConflicts() async {
    final conflicts = await store.queue(statuses: ['conflict']);
    if (conflicts.isEmpty) return;
    final res = await api.post('sync/status', {'uuids': conflicts.map((c) => c.localUuid).toList()});
    for (final r in ((res['data'] as List?) ?? const []).cast<Map<String, dynamic>>()) {
      if (r['status'] != 'conflict') await store.applyResult(r);
    }
  }

  Future<void> pull() async {
    final since = await store.appDb.getKv('last_pull');
    final data = await api.get('sync/pull', query: since == null ? null : {'since': since});
    await store.applyPull(data);
  }

  static Object? _decode(String payload) {
    try {
      return jsonDecode(payload);
    } on FormatException catch (e) {
      debugPrint('Bad payload: $e');
      return <String, dynamic>{};
    }
  }
}
