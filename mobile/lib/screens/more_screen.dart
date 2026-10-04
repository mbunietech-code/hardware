import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import 'cart_screen.dart';
import 'expense_screen.dart';
import '../l10n/l10n.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  Future<void> _capital(BuildContext context) async {
    final s = context.read<AppState>();
    String type = 'injection';
    final amount = TextEditingController();
    final reason = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => AlertDialog(
          title: Text(tr('Capital entry')),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(value: 'injection', label: Text(tr('Money in'))),
                  ButtonSegment(value: 'withdrawal', label: Text(tr('Money out'))),
                ],
                selected: {type},
                onSelectionChanged: (v) => set(() => type = v.first),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: tr('Amount')),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: reason,
                decoration: InputDecoration(labelText: tr('Reason')),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(tr('Cancel'))),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(tr('Save'))),
          ],
        ),
      ),
    );
    if (ok != true || !context.mounted) return;
    try {
      await s.store.recordCapital(shopId: s.shopId!, type: type, amount: parseNum(amount.text) ?? 0, reason: reason.text);
      await s.recorded();
      if (context.mounted) showMessage(context, tr('Capital entry saved.'));
    } on LocalValidationException catch (e) {
      if (context.mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(title: Text(tr('More')), actions: [SyncBadge()]),
      body: ListView(
        children: [
          ListTile(
            leading: const CircleAvatar(child: Icon(Icons.person)),
            title: Text('${s.user?['name']}'),
            subtitle: Text(
              '${s.isSuperAdmin ? tr('Super Admin') : tr('Shop Admin')} · ${(s.user?['shop'] as Map?)?['name'] ?? '${tr('shop')} #${s.shopId}'}',
            ),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.translate),
            title: Text(tr('Language')),
            subtitle: const Text('English / Kiswahili'),
            trailing: const LanguageSwitch(),
          ),
          ListTile(
            leading: const Icon(Icons.event_available),
            title: Text(tr('Open / close business day')),
            onTap: () => Navigator.pushNamed(context, '/day'),
          ),
          ListTile(
            leading: const Icon(Icons.local_shipping),
            title: Text(tr('New purchase')),
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CartScreen(mode: CartMode.purchase))),
          ),
          ListTile(
            leading: const Icon(Icons.receipt_long),
            title: Text(tr('Expenses')),
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ExpenseScreen())),
          ),
          if (s.can('record_capital')) ListTile(leading: const Icon(Icons.savings), title: Text(tr('Capital entry')), onTap: () => _capital(context)),
          ListTile(
            leading: const Icon(Icons.notifications),
            title: Text(tr('Alerts')),
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _AlertsScreen())),
          ),
          ListTile(
            leading: const Icon(Icons.sync),
            title: Text(tr('Sync status')),
            trailing: s.unsynced > 0 ? Badge(label: Text('${s.unsynced}')) : null,
            onTap: () => Navigator.pushNamed(context, '/sync'),
          ),
          if (s.isSuperAdmin)
            ListTile(
              leading: const Icon(Icons.store),
              title: Text(tr('Switch shop')),
              onTap: () async {
                final shops = await s.store.shops();
                if (!context.mounted) return;
                final id = await showDialog<int>(
                  context: context,
                  builder: (ctx) => SimpleDialog(
                    title: Text(tr('Choose shop')),
                    children: [
                      for (final shop in shops)
                        SimpleDialogOption(onPressed: () => Navigator.pop(ctx, shop['id'] as int), child: Text(shop['name'] as String)),
                    ],
                  ),
                );
                if (id != null) await s.selectShop(id);
              },
            ),
          const Divider(),
          ListTile(leading: const Icon(Icons.dns), title: Text(tr('Server')), subtitle: Text(s.serverUrl)),
          ListTile(
            leading: const Icon(Icons.logout, color: Colors.red),
            title: Text(tr('Log out')),
            subtitle: s.unsynced > 0 ? Text(tr('{n} record(s) not synced yet – they stay on this phone.', {'n': s.unsynced})) : null,
            onTap: () async {
              final ok = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: Text(tr('Log out?')),
                  content: Text(
                    s.unsynced > 0
                        ? tr('Unsynced records stay on this phone and will sync after you log in again.')
                        : tr('You will need internet to log in again.'),
                  ),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(tr('Cancel'))),
                    FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(tr('Log out'))),
                  ],
                ),
              );
              if (ok == true) await s.logout();
            },
          ),
        ],
      ),
    );
  }
}

class _AlertsScreen extends StatelessWidget {
  const _AlertsScreen();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(title: Text(tr('Alerts'))),
      body: FutureBuilder(
        key: ValueKey(s.revision),
        future: s.store.notifications(),
        builder: (context, snap) {
          final list = snap.data ?? [];
          if (snap.hasData && list.isEmpty) {
            return EmptyState(icon: Icons.notifications_none, message: tr('No alerts. Alerts update when the phone syncs.'));
          }
          return ListView(
            children: [
              for (final n in list)
                ListTile(
                  leading: const Icon(Icons.notifications_active, color: Colors.orange),
                  title: Text('${n['title']}'),
                  subtitle: Text('${n['message']}'),
                ),
            ],
          );
        },
      ),
    );
  }
}
