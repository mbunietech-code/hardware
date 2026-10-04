import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../l10n/l10n.dart';

/// Daily opening and closing (Doc 07 / FR-022, FR-023).
class DayScreen extends StatefulWidget {
  const DayScreen({super.key});

  @override
  State<DayScreen> createState() => _DayScreenState();
}

class _DayScreenState extends State<DayScreen> {
  final _cash = TextEditingController(text: '0');
  final _notes = TextEditingController();
  final _exceptions = TextEditingController();
  bool _busy = false;

  Future<void> _run(Future<void> Function() action, String done) async {
    final state = context.read<AppState>();
    setState(() => _busy = true);
    try {
      await action();
      await state.recorded();
      if (mounted) showMessage(context, done);
    } on LocalValidationException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    final session = s.todaySession;
    return Scaffold(
      appBar: AppBar(title: Text('${tr('Business day')} · ${today()}'), actions: const [SyncBadge()]),
      body: FutureBuilder(
        key: ValueKey(s.revision),
        future: s.store.dayTotals(s.shopId!, today()),
        builder: (context, snap) {
          final t = snap.data ?? {};
          final expected = (session?.openingCash ?? 0) + (t['cash_sales'] ?? 0) - (t['cash_out'] ?? 0);
          final sales = t['sales_total'] ?? 0;
          final remaining = (session?.openingCash ?? 0) + sales - (t['purchases_total'] ?? 0) - (t['expenses_total'] ?? 0);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              PhotoCard(
                image: Photos.cement,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(tr('Business day'), style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Text(today(), style: TextStyle(color: Colors.white.withValues(alpha: .8))),
                    if (session != null) ...[const SizedBox(height: 10), StatusChip(session.status)],
                  ],
                ),
              ),
              const SizedBox(height: 16),
              if (session == null) ...[
                Text(tr('Open the day to start recording sales, purchases and expenses.'), style: Theme.of(context).textTheme.bodyLarge),
                const SizedBox(height: 16),
                TextField(
                  controller: _cash,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(labelText: tr('Opening cash in drawer')),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _notes,
                  decoration: InputDecoration(labelText: tr('Opening notes')),
                ),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _busy
                      ? null
                      : () => _run(
                          () => s.store.openDay(s.shopId!, openingCash: parseNum(_cash.text) ?? 0, notes: _notes.text),
                          tr('Business day opened.'),
                        ),
                  icon: const Icon(Icons.lock_open),
                  label: Text(tr('Open business day')),
                ),
              ] else ...[
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        _row(context, tr('Status'), null, trailing: StatusChip(session.status)),
                        _row(context, tr('Opening cash'), session.openingCash),
                        _row(context, tr('Sales ({n})', {'n': (t['sales_count'] ?? 0).toInt()}), t['sales_total']),
                        _row(context, tr('Cash sales'), t['cash_sales']),
                        _row(context, tr('Purchases'), t['purchases_total']),
                        _row(context, tr('Expenses'), t['expenses_total']),
                        _row(context, tr('Debt payments'), t['debt_payments']),
                        const Divider(),
                        // Opening cash + sales − purchases − expenses = what should remain today.
                        _row(context, tr('Money remaining'), remaining, bold: true),
                        _row(context, tr('Expenses as % of sales'), null,
                            trailing: Text(sales > 0 ? '${((t['expenses_total'] ?? 0) / sales * 100).toStringAsFixed(1)}%' : '—',
                                style: const TextStyle(fontWeight: FontWeight.w700))),
                        _row(context, tr('Expected cash (this phone)'), expected),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  tr(
                    'Totals here include records made on this phone. The server recalculates the official totals from all records when the closing syncs.',
                  ),
                  style: TextStyle(fontSize: 12, color: Colors.grey),
                ),
                if (session.isOpen) ...[
                  const SizedBox(height: 16),
                  TextField(
                    controller: _cash..text = _cash.text == '0' ? '' : _cash.text,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(labelText: tr('Counted cash in drawer')),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _exceptions,
                    maxLines: 2,
                    decoration: InputDecoration(labelText: tr('Exceptions (shortages, damaged stock…)')),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _notes,
                    maxLines: 2,
                    decoration: InputDecoration(labelText: tr('Closing notes')),
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: _busy
                        ? null
                        : () async {
                            final ok = await showDialog<bool>(
                              context: context,
                              builder: (ctx) => AlertDialog(
                                title: Text(tr('Close business day?')),
                                content: Text(tr('After closing, changes for today need a Super Admin.')),
                                actions: [
                                  TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(tr('Cancel'))),
                                  FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(tr('Close day'))),
                                ],
                              ),
                            );
                            if (ok == true) {
                              await _run(
                                () => s.store.closeDay(session, closingCash: parseNum(_cash.text), notes: _notes.text, exceptions: _exceptions.text),
                                tr('Business day closed.'),
                              );
                            }
                          },
                    icon: const Icon(Icons.lock),
                    label: Text(tr('Close business day')),
                  ),
                ],
              ],
            ],
          );
        },
      ),
    );
  }

  Widget _row(BuildContext context, String label, double? value, {bool bold = false, Widget? trailing}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      children: [
        Expanded(
          child: Text(label, style: TextStyle(fontWeight: bold ? FontWeight.bold : null)),
        ),
        trailing ?? Text(money(context, value ?? 0), style: TextStyle(fontWeight: bold ? FontWeight.bold : null)),
      ],
    ),
  );
}
