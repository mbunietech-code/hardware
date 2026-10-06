import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../l10n/l10n.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// Customers with what they owe; add new customers (works offline).
class CustomersScreen extends StatefulWidget {
  const CustomersScreen({super.key});

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends State<CustomersScreen> {
  String _search = '';

  Future<void> _add() async {
    final name = TextEditingController();
    final phone = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(tr('New customer')),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: name, decoration: InputDecoration(labelText: tr('Name *'))),
          const SizedBox(height: 12),
          TextField(controller: phone, keyboardType: TextInputType.phone, decoration: InputDecoration(labelText: tr('Phone'))),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(tr('Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(tr('Save'))),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final s = context.read<AppState>();
    try {
      await s.store.addParty('customers', name.text, phone.text.trim().isEmpty ? null : phone.text.trim());
      await s.recorded();
      if (mounted) showMessage(context, tr('Customer added.'));
    } on LocalValidationException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(title: Text(tr('Customers'))),
      floatingActionButton: FloatingActionButton.extended(onPressed: _add, icon: const Icon(Icons.person_add_alt_1_rounded), label: Text(tr('Customer'))),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            decoration: InputDecoration(hintText: tr('Search name'), prefixIcon: const Icon(Icons.search_rounded)),
            onChanged: (v) => setState(() => _search = v.trim()),
          ),
        ),
        Expanded(
          child: FutureBuilder<List<Map<String, Object?>>>(
            key: ValueKey('${s.revision}$_search'),
            future: s.store.customersWithBalance(search: _search),
            builder: (context, snap) {
              final list = snap.data ?? [];
              if (snap.hasData && list.isEmpty) return EmptyState(icon: Icons.people_alt_rounded, message: tr('No customers yet.'));
              return ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 90),
                itemCount: list.length,
                separatorBuilder: (_, _) => const SizedBox(height: 8),
                itemBuilder: (_, i) {
                  final c = list[i];
                  final owes = toDouble(c['owes']);
                  return Card(
                    child: ListTile(
                      leading: ProductAvatar(name: '${c['name']}', size: 40),
                      title: Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: Text((c['phone'] as String?) ?? '—'),
                      trailing: owes > 0
                          ? Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                              Text(tr('Owes'), style: const TextStyle(color: Brand.muted, fontSize: 11.5)),
                              Text(money(context, owes, symbol: false), style: const TextStyle(fontWeight: FontWeight.w800, color: Colors.orange)),
                            ])
                          : Pill(tr('No debt'), color: Colors.green),
                    ),
                  );
                },
              );
            },
          ),
        ),
      ]),
    );
  }
}
