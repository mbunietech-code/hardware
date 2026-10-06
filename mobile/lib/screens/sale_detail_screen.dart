import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../data/receipt.dart';
import '../l10n/l10n.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// One sale: items, totals, receipt sharing and customer returns.
class SaleDetailScreen extends StatelessWidget {
  const SaleDetailScreen({super.key, required this.uuid});
  final String uuid;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return FutureBuilder(
      key: ValueKey(s.revision),
      future: Future.wait([s.store.queueItem(uuid), s.store.productNames(), s.store.returnedQty(uuid), s.store.settings(), s.store.shops()]),
      builder: (context, snap) {
        if (!snap.hasData) return const Scaffold(body: Center(child: CircularProgressIndicator()));
        final sale = snap.data![0] as QueueItem?;
        if (sale == null) return Scaffold(appBar: AppBar(), body: EmptyState(icon: Icons.search_off_rounded, message: tr('Nothing here.')));
        final names = snap.data![1] as Map<int, String>;
        final returned = snap.data![2] as Map<int, double>;
        final settings = snap.data![3] as Map<String, dynamic>;
        final shops = snap.data![4] as List<Map<String, Object?>>;
        final p = jsonDecode(sale.payload) as Map<String, dynamic>;
        final items = (p['items'] as List).cast<Map<String, dynamic>>();
        final paid = toDouble(p['amount_paid']);
        final shopName = (shops.firstWhere((x) => x['id'] == p['shop_id'], orElse: () => {'name': ''})['name'] ?? '') as String;
        final receipt = buildReceipt(
          sale: sale,
          productNames: names,
          businessName: s.businessName,
          shopName: shopName,
          currency: s.currency,
          footer: settings['receipt_footer'] as String?,
        );
        final canReturn = sale.status != 'rejected' && s.can('process_returns') &&
            items.any((i) => toDouble(i['quantity']) - (returned[i['product_id']] ?? 0) > 0.0005);

        return Scaffold(
          appBar: AppBar(title: Text(sale.reference ?? tr('Sale'))),
          body: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        Expanded(child: Text(money(context, sale.amount), style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800))),
                        StatusChip(sale.status),
                      ]),
                      const SizedBox(height: 4),
                      Text('${sale.businessDate} · ${tr(p['payment_method'] as String)}', style: const TextStyle(color: Brand.muted)),
                      if (sale.amount - paid > 0.004)
                        Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Pill('${tr('Balance')} ${money(context, sale.amount - paid, symbol: false)}', color: Colors.orange),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Card(
                child: Column(children: [
                  for (final i in items)
                    ListTile(
                      leading: ProductAvatar(name: names[i['product_id']] ?? '?', size: 38),
                      title: Text(names[i['product_id']] ?? '#${i['product_id']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                      subtitle: Text([
                        '${qty(toDouble(i['quantity']))} × ${money(context, toDouble(i['unit_price']), symbol: false)}',
                        if ((returned[i['product_id']] ?? 0) > 0) tr('{n} returned', {'n': qty(returned[i['product_id']]!)}),
                      ].join(' · ')),
                      trailing: Text(money(context, toDouble(i['quantity']) * toDouble(i['unit_price']) - toDouble(i['discount']), symbol: false),
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                    ),
                ]),
              ),
              const SizedBox(height: 16),
              SectionTitle(tr('Receipt')),
              const SizedBox(height: 8),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(receipt, style: const TextStyle(fontFamily: 'monospace', fontSize: 12.5, height: 1.4)),
                ),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () => SharePlus.instance.share(ShareParams(text: receipt, subject: tr('Receipt'))),
                icon: const Icon(Icons.share_rounded),
                label: Text(tr('Share receipt (WhatsApp, SMS…)')),
              ),
              if (canReturn) ...[
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () => showModalBottomSheet(
                    context: context,
                    isScrollControlled: true,
                    builder: (_) => ChangeNotifierProvider.value(
                      value: s,
                      child: _ReturnSheet(sale: sale, items: items, names: names, returned: returned),
                    ),
                  ),
                  icon: const Icon(Icons.assignment_return_rounded),
                  label: Text(tr('Return items')),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _ReturnSheet extends StatefulWidget {
  const _ReturnSheet({required this.sale, required this.items, required this.names, required this.returned});
  final QueueItem sale;
  final List<Map<String, dynamic>> items;
  final Map<int, String> names;
  final Map<int, double> returned;

  @override
  State<_ReturnSheet> createState() => _ReturnSheetState();
}

class _ReturnSheetState extends State<_ReturnSheet> {
  final Map<int, double> _qty = {};
  final Set<int> _damaged = {};
  final _reason = TextEditingController();
  String _method = 'cash';
  bool _busy = false;

  late final Map<int, double> _units = LocalStore.unitValues(jsonDecode(widget.sale.payload) as Map<String, dynamic>);

  double get _value => _qty.entries.fold(0, (a, e) => a + e.value * (_units[e.key] ?? 0));

  Future<void> _save() async {
    final s = context.read<AppState>();
    setState(() => _busy = true);
    try {
      final r = await s.store.recordReturn(sale: widget.sale, quantities: _qty, damaged: _damaged, reason: _reason.text, refundMethod: _method);
      await s.recorded();
      if (!mounted) return;
      Navigator.pop(context);
      showMessage(context, tr('Return saved. Refund {amount}.', {'amount': money(context, r.value)}));
    } on LocalValidationException catch (e) {
      showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(tr('Return items'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            for (final i in widget.items) ...[
              Builder(builder: (context) {
                final id = i['product_id'] as int;
                final left = toDouble(i['quantity']) - (widget.returned[id] ?? 0);
                if (left <= 0.0005) return const SizedBox.shrink();
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(widget.names[id] ?? '#$id', style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(tr('Can return {n} · {amount} each', {'n': qty(left), 'amount': money(context, _units[id] ?? 0, symbol: false)}),
                            style: const TextStyle(color: Brand.muted, fontSize: 12.5)),
                        const SizedBox(height: 8),
                        Row(children: [
                          SizedBox(
                            width: 110,
                            child: TextField(
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: InputDecoration(labelText: tr('Quantity')),
                              onChanged: (v) => setState(() => _qty[id] = parseNum(v) ?? 0),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: CheckboxListTile(
                              dense: true,
                              contentPadding: EdgeInsets.zero,
                              value: !_damaged.contains(id),
                              onChanged: (v) => setState(() => v == true ? _damaged.remove(id) : _damaged.add(id)),
                              title: Text(tr('Back to stock (item is OK)'), style: const TextStyle(fontSize: 13)),
                            ),
                          ),
                        ]),
                      ],
                    ),
                  ),
                );
              }),
              const SizedBox(height: 6),
            ],
            const SizedBox(height: 6),
            TextField(controller: _reason, decoration: InputDecoration(labelText: tr('Reason *'))),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: 'cash', label: Text(tr('Cash'))),
                ButtonSegment(value: 'mobile_money', label: Text(tr('M-Money'))),
                ButtonSegment(value: 'bank', label: Text(tr('Bank'))),
              ],
              selected: {_method},
              onSelectionChanged: (v) => setState(() => _method = v.first),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy || _value <= 0 ? null : _save,
              child: Text('${tr('Save return')} · ${money(context, _value)}'),
            ),
            const SizedBox(height: 6),
            Text(tr('On a credit sale the server takes the value off the customer debt first.'), style: const TextStyle(color: Brand.muted, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
