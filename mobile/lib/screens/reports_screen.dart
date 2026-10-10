import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/api_client.dart';
import '../data/local_store.dart';
import '../data/models.dart';
import '../l10n/l10n.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// Sales, expenses and profit for today / this week / this month, calculated by the server.
class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  String _period = 'daily';
  Future<Map<String, dynamic>>? _future;

  ({String from, String to}) get _range {
    final now = DateTime.now();
    return switch (_period) {
      'weekly' => (from: dateOnly(now.subtract(Duration(days: now.weekday - 1))), to: today()),
      'monthly' => (from: dateOnly(DateTime(now.year, now.month)), to: today()),
      _ => (from: today(), to: today()),
    };
  }

  Future<Map<String, dynamic>> _load() async {
    final api = context.read<AppState>().api;
    final r = _range;
    final q = {'from': r.from, 'to': r.to};
    final profit = await api.get('reports/profit', query: {...q, 'group_by': 'product'});
    final sales = await api.get('reports/sales', query: q);
    final expenses = await api.get('reports/expenses', query: q);
    return {'profit': profit, 'sales': sales, 'expenses': expenses};
  }

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  void _select(String p) => setState(() {
        _period = p;
        _future = _load();
      });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(tr('Reports'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: SegmentedButton<String>(
              segments: [
                ButtonSegment(value: 'daily', label: Text(tr('Today'))),
                ButtonSegment(value: 'weekly', label: Text(tr('This week'))),
                ButtonSegment(value: 'monthly', label: Text(tr('This month'))),
              ],
              selected: {_period},
              onSelectionChanged: (v) => _select(v.first),
            ),
          ),
          Expanded(
            child: FutureBuilder<Map<String, dynamic>>(
              future: _future,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
                if (snap.hasError) {
                  final e = snap.error;
                  return _Offline(message: e is ApiException && !e.isNetwork ? e.message : tr('Reports need internet. Showing what this phone recorded today.'));
                }
                final profit = snap.data!['profit'] as Map<String, dynamic>;
                final summary = Map<String, dynamic>.from(profit['summary'] as Map? ?? {});
                final rows = (profit['rows'] as List).cast<Map<String, dynamic>>();
                final sales = snap.data!['sales'] as Map<String, dynamic>;
                final expenses = snap.data!['expenses'] as Map<String, dynamic>;
                final salesTotal = toDouble((sales['totals'] as Map?)?['total']);
                final expenseTotal = toDouble((expenses['totals'] as Map?)?['amount']);
                final values = summary.values.map(toDouble).toList();
                final netProfit = values.isEmpty ? 0.0 : values.last;
                return RefreshIndicator(
                  onRefresh: () async => _select(_period),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                    children: [
                      Row(children: [
                        Expanded(child: StatCard(icon: Icons.shopping_cart_rounded, tone: Colors.teal, label: tr('Sales'), value: money(context, salesTotal),
                            sub: tr('{n} sale(s)', {'n': (sales['rows'] as List).length}))),
                        const SizedBox(width: 12),
                        Expanded(child: StatCard(icon: Icons.receipt_long_rounded, tone: Colors.amber, label: tr('Expenses'), value: money(context, expenseTotal))),
                      ]),
                      const SizedBox(height: 12),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(tr('Profit'), style: const TextStyle(color: Brand.muted, fontWeight: FontWeight.w600)),
                              Text(money(context, netProfit), style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: netProfit < 0 ? Colors.red : Brand.teal700)),
                              const SizedBox(height: 10),
                              for (final e in summary.entries)
                                Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 3),
                                  child: Row(children: [
                                    Expanded(child: Text(e.key, style: const TextStyle(color: Brand.muted, fontSize: 13))),
                                    Text(money(context, toDouble(e.value), symbol: false), style: const TextStyle(fontWeight: FontWeight.w600)),
                                  ]),
                                ),
                            ],
                          ),
                        ),
                      ),
                      if (rows.isNotEmpty) ...[
                        const SizedBox(height: 18),
                        SectionTitle(tr('Best-selling products')),
                        const SizedBox(height: 8),
                        Card(
                          child: Column(children: [
                            for (final r in rows.take(10))
                              ListTile(
                                dense: true,
                                leading: ProductAvatar(name: '${r['name']}', size: 34),
                                title: Text('${r['name']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                                subtitle: Text('${tr('Qty sold')}: ${qty(toDouble(r['qty']))} · ${tr('Gross profit')}: ${money(context, toDouble(r['gross']), symbol: false)}'),
                                trailing: Text(money(context, toDouble(r['revenue']), symbol: false), style: const TextStyle(fontWeight: FontWeight.w700)),
                              ),
                          ]),
                        ),
                      ],
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

class _Offline extends StatelessWidget {
  const _Offline({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return FutureBuilder(
      future: s.store.dayTotals(s.shopId!, today()),
      builder: (context, snap) {
        final t = snap.data ?? {};
        return ListView(padding: const EdgeInsets.all(16), children: [
          Card(color: Colors.amber.shade50, child: ListTile(leading: const Icon(Icons.wifi_off_rounded), title: Text(message))),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: StatCard(icon: Icons.shopping_cart_rounded, tone: Colors.teal, label: tr('Sales today'), value: money(context, t['sales_total'] ?? 0))),
            const SizedBox(width: 12),
            Expanded(child: StatCard(icon: Icons.receipt_long_rounded, tone: Colors.amber, label: tr('Expenses today'), value: money(context, t['expenses_total'] ?? 0))),
          ]),
        ]);
      },
    );
  }
}
