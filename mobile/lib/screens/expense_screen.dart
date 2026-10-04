import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import '../l10n/l10n.dart';

class ExpenseScreen extends StatefulWidget {
  const ExpenseScreen({super.key});

  @override
  State<ExpenseScreen> createState() => _ExpenseScreenState();
}

class _ExpenseScreenState extends State<ExpenseScreen> {
  final _amount = TextEditingController();
  final _reason = TextEditingController();
  int? _category;
  String _method = 'cash';
  bool _busy = false;

  Future<void> _save(List<Map<String, Object?>> cats) async {
    final s = context.read<AppState>();
    if (_category == null) return showMessage(context, tr('Choose a category.'), error: true);
    setState(() => _busy = true);
    try {
      await s.store.recordExpense(
        shopId: s.shopId!,
        categoryId: _category!,
        categoryName: cats.firstWhere((c) => c['id'] == _category)['name'] as String,
        amount: parseNum(_amount.text) ?? 0,
        reason: _reason.text,
        paymentMethod: _method,
      );
      await s.recorded();
      if (!mounted) return;
      showMessage(context, tr('Expense saved.'));
      _amount.clear();
      _reason.clear();
      setState(() {});
    } on LocalValidationException catch (e) {
      showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(title: Text(tr('Expenses')), actions: [SyncBadge()]),
      body: FutureBuilder(
        key: ValueKey(s.revision),
        future: Future.wait([
          s.store.expenseCategories(),
          s.store.activity(shopId: s.shopId!, date: today(), entities: ['expense']),
        ]),
        builder: (context, snap) {
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final cats = snap.data![0] as List<Map<String, Object?>>;
          final todays = snap.data![1] as List<QueueItem>;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              const NeedsOpenDay(),
              DropdownButtonFormField<int>(
                initialValue: _category,
                decoration: InputDecoration(labelText: tr('Category *')),
                items: [for (final c in cats) DropdownMenuItem(value: c['id'] as int, child: Text(c['name'] as String))],
                onChanged: (v) => setState(() => _category = v),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _amount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: tr('Amount *')),
              ),
              SizedBox(height: 12),
              TextField(
                controller: _reason,
                decoration: InputDecoration(labelText: tr('Reason *')),
              ),
              SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _method,
                decoration: InputDecoration(labelText: tr('Paid via')),
                items: [
                  DropdownMenuItem(value: 'cash', child: Text(tr('Cash'))),
                  DropdownMenuItem(value: 'mobile_money', child: Text(tr('Mobile money'))),
                  DropdownMenuItem(value: 'bank', child: Text(tr('Bank'))),
                ],
                onChanged: (v) => setState(() => _method = v!),
              ),
              const SizedBox(height: 16),
              FilledButton(onPressed: _busy ? null : () => _save(cats), child: Text(tr('Save expense'))),
              const SizedBox(height: 24),
              Text(tr("Today's expenses"), style: Theme.of(context).textTheme.titleMedium),
              if (todays.isEmpty)
                Padding(
                  padding: EdgeInsets.all(12),
                  child: Text(tr('None yet.'), style: TextStyle(color: Colors.grey)),
                ),
              for (final e in todays)
                ListTile(
                  title: Text(e.summary),
                  trailing: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [Text(money(context, e.amount, symbol: false)), StatusChip(e.status)],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
