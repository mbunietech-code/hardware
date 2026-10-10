import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../l10n/l10n.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'sale_detail_screen.dart';

/// Sales recorded on this phone (last 45 days), grouped by day.
class SalesHistoryScreen extends StatefulWidget {
  const SalesHistoryScreen({super.key});

  @override
  State<SalesHistoryScreen> createState() => _SalesHistoryScreenState();
}

class _SalesHistoryScreenState extends State<SalesHistoryScreen> {
  String? _date = today(); // null = all

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    final yesterday = dateOnly(DateTime.now().subtract(const Duration(days: 1)));
    return Scaffold(
      appBar: AppBar(title: Text(tr('Sales history'))),
      body: Column(
        children: [
          SizedBox(
            height: 50,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              children: [
                for (final (label, value) in [(tr('Today'), today()), (tr('Yesterday'), yesterday), (tr('All'), null)])
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(label),
                      selected: _date == value,
                      showCheckmark: false,
                      labelStyle: TextStyle(color: _date == value ? Colors.white : Brand.ink, fontWeight: FontWeight.w700),
                      onSelected: (_) => setState(() => _date = value),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder<List<QueueItem>>(
              key: ValueKey('${s.revision}$_date'),
              future: s.store.salesHistory(shopId: s.shopId!, date: _date),
              builder: (context, snap) {
                final list = snap.data ?? [];
                if (snap.hasData && list.isEmpty) return EmptyState(icon: Icons.receipt_long_rounded, message: tr('No sales for this period.'));
                final total = list.where((q) => q.status != 'rejected').fold<double>(0, (a, q) => a + q.amount);
                String? lastDay;
                return ListView(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                  children: [
                    if (list.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Text(tr('{n} sale(s) · {amount}', {'n': list.length, 'amount': money(context, total)}),
                            style: const TextStyle(color: Brand.muted, fontWeight: FontWeight.w600)),
                      ),
                    for (final q in list) ...[
                      if (_date == null && q.businessDate != lastDay) ...[
                        Padding(
                          padding: const EdgeInsets.fromLTRB(4, 12, 4, 6),
                          child: Text(lastDay = q.businessDate ?? '', style: const TextStyle(fontWeight: FontWeight.w800)),
                        ),
                      ],
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Card(
                          child: ListTile(
                            leading: const IconBubble(icon: Icons.shopping_cart_rounded, color: Colors.teal),
                            title: Text(q.reference ?? tr('Sale'), style: const TextStyle(fontWeight: FontWeight.w700)),
                            subtitle: Text('${TimeOfDay.fromDateTime(q.createdAt.toLocal()).format(context)} · ${q.summary}', maxLines: 1, overflow: TextOverflow.ellipsis),
                            trailing: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(money(context, q.amount, symbol: false), style: const TextStyle(fontWeight: FontWeight.w800)),
                                const SizedBox(height: 4),
                                StatusChip(q.status),
                              ],
                            ),
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SaleDetailScreen(uuid: q.localUuid))),
                          ),
                        ),
                      ),
                    ],
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
