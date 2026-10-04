import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import '../l10n/l10n.dart';

/// Sync status: pending, failed, synced and conflict records (Doc 12).
class SyncScreen extends StatefulWidget {
  const SyncScreen({super.key});

  @override
  State<SyncScreen> createState() => _SyncScreenState();
}

class _SyncScreenState extends State<SyncScreen> {
  String _filter = 'unsynced';

  static const _filters = {
    'unsynced': ['pending', 'retry', 'syncing'],
    'problems': ['rejected', 'conflict'],
    'synced': ['synced'],
  };

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    final c = s.queueCounts;
    return Scaffold(
      appBar: AppBar(title: Text(tr('Sync status'))),
      body: Column(
        children: [
          Card(
            margin: const EdgeInsets.all(12),
            child: ListTile(
              leading: Icon(s.online ? Icons.cloud_done : Icons.cloud_off, color: s.online ? Colors.green : Colors.orange, size: 32),
              title: Text(s.online ? tr('Online') : tr('Offline – records are saved on this phone')),
              subtitle: Text(
                [
                  if (s.lastSyncAt != null) tr('Last sync {time}', {'time': TimeOfDay.fromDateTime(s.lastSyncAt!).format(context)}),
                  if (s.lastSyncMessage != null) s.lastSyncMessage!,
                ].join('\n'),
              ),
              trailing: FilledButton(
                onPressed: s.syncing
                    ? null
                    : () async {
                        final r = await s.syncNow();
                        if (context.mounted && r != null) showMessage(context, r.toString(), error: !r.ok);
                      },
                child: s.syncing ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : Text(tr('Sync now')),
              ),
            ),
          ),
          SegmentedButton<String>(
            segments: [
              ButtonSegment(value: 'unsynced', label: Text(tr('Waiting {n}', {'n': (c['pending'] ?? 0) + (c['retry'] ?? 0) + (c['syncing'] ?? 0)}))),
              ButtonSegment(value: 'problems', label: Text(tr('Problems {n}', {'n': (c['rejected'] ?? 0) + (c['conflict'] ?? 0)}))),
              ButtonSegment(value: 'synced', label: Text(tr('Synced {n}', {'n': c['synced'] ?? 0}))),
            ],
            selected: {_filter},
            onSelectionChanged: (v) => setState(() => _filter = v.first),
          ),
          Expanded(
            child: FutureBuilder<List<QueueItem>>(
              key: ValueKey('${s.revision}$_filter'),
              future: s.store.queue(statuses: _filters[_filter]),
              builder: (context, snap) {
                final items = snap.data ?? [];
                if (snap.hasData && items.isEmpty) return EmptyState(icon: Icons.done_all, message: tr('Nothing here.'));
                return ListView.builder(
                  itemCount: items.length,
                  itemBuilder: (_, i) {
                    final it = items[i];
                    return ListTile(
                      title: Text('${tr(it.entity.replaceAll('_', ' '))}${it.reference != null ? ' · ${it.reference}' : ''}'),
                      subtitle: Text(
                        [
                          it.summary,
                          '${it.createdAt.toLocal()}'.substring(0, 16),
                          if (it.error != null && it.status != 'synced') it.error!,
                          if (it.status == 'conflict') tr('Waiting for the Super Admin to accept or reject on the web.'),
                        ].join('\n'),
                      ),
                      isThreeLine: true,
                      trailing: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [if (it.amount > 0) Text(money(context, it.amount, symbol: false)), StatusChip(it.status)],
                      ),
                      onTap: () => showDialog(
                        context: context,
                        builder: (ctx) => AlertDialog(
                          title: Text(it.entity),
                          content: SingleChildScrollView(
                            child: Text(
                              const JsonEncoder.withIndent('  ').convert(jsonDecode(it.payload)),
                              style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
                            ),
                          ),
                          actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: Text(tr('Close')))],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
