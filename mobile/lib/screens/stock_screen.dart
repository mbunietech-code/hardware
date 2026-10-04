import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import 'cart_screen.dart';
import '../l10n/l10n.dart';

class StockScreen extends StatefulWidget {
  const StockScreen({super.key});

  @override
  State<StockScreen> createState() => _StockScreenState();
}

class _StockScreenState extends State<StockScreen> {
  String _search = '';
  bool _lowOnly = false;

  Future<void> _adjust(Product p) async {
    final s = context.read<AppState>();
    if (!s.can('adjust_stock')) return showMessage(context, tr('You are not allowed to adjust stock.'), error: true);
    String direction = 'in';
    final q = TextEditingController();
    final reason = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => AlertDialog(
          title: Text(tr('Adjust {name}', {'name': p.name})),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(tr('Current: {qty} {unit}', {'qty': qty(p.stock), 'unit': p.unit})),
              const SizedBox(height: 12),
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(value: 'in', label: Text(tr('+ Increase'))),
                  ButtonSegment(value: 'out', label: Text(tr('− Decrease'))),
                ],
                selected: {direction},
                onSelectionChanged: (v) => set(() => direction = v.first),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: q,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: tr('Quantity')),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: reason,
                decoration: InputDecoration(labelText: tr('Reason (damaged, count, opening…)')),
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
    if (ok != true || !mounted) return;
    try {
      await s.store.adjustStock(shopId: s.shopId!, product: p, direction: direction, quantity: parseNum(q.text) ?? 0, reason: reason.text);
      await s.recorded();
      if (mounted) showMessage(context, tr('Stock adjusted.'));
    } on LocalValidationException catch (e) {
      if (mounted) showMessage(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(
        title: Text(tr('Stock')),
        actions: [
          IconButton(
            tooltip: tr('New purchase'),
            icon: const Icon(Icons.local_shipping),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CartScreen(mode: CartMode.purchase))),
          ),
          const SyncBadge(),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    decoration: InputDecoration(hintText: tr('Search product'), prefixIcon: Icon(Icons.search)),
                    onChanged: (v) => setState(() => _search = v),
                  ),
                ),
                const SizedBox(width: 8),
                FilterChip(label: Text(tr('Low')), selected: _lowOnly, onSelected: (v) => setState(() => _lowOnly = v)),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder(
              key: ValueKey('${s.revision}$_search$_lowOnly'),
              future: s.store.products(shopId: s.shopId!, search: _search, lowOnly: _lowOnly),
              builder: (context, snap) {
                final list = snap.data ?? [];
                if (snap.hasData && list.isEmpty) return EmptyState(icon: Icons.inventory_2, message: tr('No products found.'));
                return RefreshIndicator(
                  onRefresh: () => s.syncNow(),
                  child: ListView.separated(
                    itemCount: list.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (_, i) {
                      final p = list[i];
                      return ListTile(
                        title: Text(p.name),
                        subtitle: Text(
                          '${p.code} · ${tr('sell')} ${money(context, p.sellingPrice, symbol: false)} · ${tr('reorder')} ${qty(p.reorderLevel)}',
                        ),
                        trailing: Text(
                          '${qty(p.stock)} ${p.unit}',
                          style: TextStyle(fontWeight: FontWeight.bold, color: p.stock <= 0 ? Colors.red : (p.isLow ? Colors.orange.shade800 : null)),
                        ),
                        onLongPress: () => _adjust(p),
                        onTap: () => _adjust(p),
                      );
                    },
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
