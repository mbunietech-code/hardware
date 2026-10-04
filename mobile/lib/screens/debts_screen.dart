import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import '../l10n/l10n.dart';

class DebtsScreen extends StatefulWidget {
  const DebtsScreen({super.key});

  @override
  State<DebtsScreen> createState() => _DebtsScreenState();
}

class _DebtsScreenState extends State<DebtsScreen> {
  String _search = '';
  bool _all = false;

  Future<void> _pay(Debt d) async {
    final s = context.read<AppState>();
    final amount = await askNumber(
      context,
      tr('Payment from/to {name}', {'name': d.partyName}),
      initial: d.balance,
      hint: tr('Balance {amount}', {'amount': qty(d.balance)}),
    );
    if (amount == null || !mounted) return;
    try {
      await s.store.recordDebtPayment(d, amount: amount);
      await s.recorded();
      if (mounted) showMessage(context, tr('Payment saved.'));
    } on LocalValidationException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  Future<void> _newDebt() async {
    final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const _NewDebtScreen()));
    if (saved == true && mounted) showMessage(context, tr('Debt saved.'));
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(title: Text(tr('Debts')), actions: [SyncBadge()]),
      floatingActionButton: FloatingActionButton.extended(onPressed: _newDebt, icon: const Icon(Icons.add), label: Text(tr('Debt'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    decoration: InputDecoration(hintText: tr('Search name'), prefixIcon: Icon(Icons.search)),
                    onChanged: (v) => setState(() => _search = v),
                  ),
                ),
                const SizedBox(width: 8),
                FilterChip(label: Text(tr('Show paid')), selected: _all, onSelected: (v) => setState(() => _all = v)),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder(
              key: ValueKey('${s.revision}$_search$_all'),
              future: s.store.debts(shopId: s.shopId!, outstandingOnly: !_all, search: _search),
              builder: (context, snap) {
                final debts = snap.data ?? [];
                if (snap.hasData && debts.isEmpty) return EmptyState(icon: Icons.check_circle_outline, message: tr('No outstanding debts.'));
                final receivable = debts.where((d) => d.type == 'receivable').fold(0.0, (a, d) => a + d.balance);
                final payable = debts.where((d) => d.type == 'payable').fold(0.0, (a, d) => a + d.balance);
                return RefreshIndicator(
                  onRefresh: () => s.syncNow(),
                  child: ListView(
                    children: [
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(
                          tr('Customers owe {a} · We owe {b}', {'a': money(context, receivable), 'b': money(context, payable)}),
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                      for (final d in debts)
                        Card(
                          margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                          child: ListTile(
                            leading: CircleAvatar(
                              backgroundColor: d.type == 'receivable' ? Colors.blue.shade50 : Colors.orange.shade50,
                              child: Icon(
                                d.type == 'receivable' ? Icons.call_received : Icons.call_made,
                                color: d.type == 'receivable' ? Colors.blue : Colors.orange,
                              ),
                            ),
                            title: Text(d.partyName),
                            subtitle: Text(
                              [
                                d.type == 'receivable' ? tr('Owes us') : tr('We owe'),
                                if (d.dueDate != null) tr('due {date}', {'date': d.dueDate}),
                                if (d.isOverdue) tr('OVERDUE'),
                                if (d.provisional) tr('not synced'),
                              ].join(' · '),
                              style: TextStyle(color: d.isOverdue ? Colors.red : null),
                            ),
                            trailing: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(money(context, d.balance, symbol: false), style: const TextStyle(fontWeight: FontWeight.bold)),
                                StatusChip(d.status),
                              ],
                            ),
                            onTap: d.isOutstanding ? () => _pay(d) : null,
                          ),
                        ),
                      const SizedBox(height: 80),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _NewDebtScreen extends StatefulWidget {
  const _NewDebtScreen();

  @override
  State<_NewDebtScreen> createState() => _NewDebtScreenState();
}

class _NewDebtScreenState extends State<_NewDebtScreen> {
  String _type = 'receivable';
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _amount = TextEditingController();
  final _notes = TextEditingController();
  DateTime? _due;

  Future<void> _save() async {
    final s = context.read<AppState>();
    try {
      await s.store.recordDebt(
        shopId: s.shopId!,
        type: _type,
        partyName: _name.text,
        partyPhone: _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        amount: parseNum(_amount.text) ?? 0,
        dueDate: _due == null ? null : dateOnly(_due!),
        notes: _notes.text,
      );
      await s.recorded();
      if (mounted) Navigator.pop(context, true);
    } on LocalValidationException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(tr('New debt'))),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const NeedsOpenDay(),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(value: 'receivable', label: Text(tr('Owes us'))),
            ButtonSegment(value: 'payable', label: Text(tr('We owe'))),
          ],
          selected: {_type},
          onSelectionChanged: (v) => setState(() => _type = v.first),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _name,
          decoration: InputDecoration(labelText: tr('Name *')),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _phone,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(labelText: tr('Phone')),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _amount,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: tr('Amount *')),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: () async {
            final d = await showDatePicker(context: context, firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 365)));
            if (d != null) setState(() => _due = d);
          },
          icon: const Icon(Icons.event),
          label: Text(_due == null ? tr('Due date (optional)') : tr('Due {date}', {'date': dateOnly(_due!)})),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _notes,
          decoration: InputDecoration(labelText: tr('Notes')),
        ),
        const SizedBox(height: 16),
        FilledButton(onPressed: _save, child: Text(tr('Save debt'))),
      ],
    ),
  );
}
