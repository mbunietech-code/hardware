import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import '../l10n/l10n.dart';
import '../theme.dart';
import 'sale_detail_screen.dart';

enum CartMode { sale, purchase }

/// Fast product search + cart for sales (selling price) and purchases (unit cost).
class CartScreen extends StatefulWidget {
  const CartScreen({super.key, required this.mode});
  final CartMode mode;

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  final _search = TextEditingController();
  final List<CartLine> _cart = [];
  List<Product> _products = [];
  List<Map<String, Object?>> _categories = [];
  int? _category;
  int _loadedRevision = -1;

  bool get _isSale => widget.mode == CartMode.sale;

  Future<void> _load() async {
    final s = context.read<AppState>();
    final list = await s.store.products(shopId: s.shopId!, search: _search.text);
    final cats = await s.store.categories();
    if (mounted) {
      setState(() {
        _products = list;
        _categories = cats;
      });
    }
  }

  double get _total => _cart.fold(0, (sum, l) => sum + (_isSale ? l.total : l.quantity * l.price));

  void _add(Product p) {
    setState(() {
      final existing = _cart.where((l) => l.product.id == p.id).firstOrNull;
      if (existing != null) {
        existing.quantity += 1;
      } else {
        _cart.add(CartLine(p, price: _isSale ? p.sellingPrice : p.costPrice));
      }
    });
  }

  Future<void> _editLine(CartLine line) async {
    final q = TextEditingController(text: qty(line.quantity));
    final pr = TextEditingController(text: qty(line.price));
    final d = TextEditingController(text: qty(line.discount));
    final discounts = await context.read<AppState>().store.settingBool('discounts_enabled', fallback: true);
    if (!mounted) return;
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(line.product.name),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: q,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(labelText: tr('Quantity ({unit})', {'unit': line.product.unit})),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: pr,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(labelText: _isSale ? tr('Unit price') : tr('Unit cost')),
            ),
            if (_isSale && discounts) ...[
              const SizedBox(height: 12),
              TextField(
                controller: d,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: tr('Line discount')),
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, 'remove'),
            child: Text(tr('Remove'), style: TextStyle(color: Colors.red)),
          ),
          FilledButton(onPressed: () => Navigator.pop(ctx, 'ok'), child: Text(tr('OK'))),
        ],
      ),
    );
    setState(() {
      if (result == 'remove') {
        _cart.remove(line);
      } else if (result == 'ok') {
        line.quantity = parseNum(q.text) ?? line.quantity;
        line.price = parseNum(pr.text) ?? line.price;
        line.discount = parseNum(d.text) ?? 0;
      }
    });
  }

  Future<void> _checkout() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => ChangeNotifierProvider.value(
        value: context.read<AppState>(),
        child: _CheckoutSheet(mode: widget.mode, lines: _cart, subtotal: _total),
      ),
    );
    if (saved == true) {
      setState(_cart.clear);
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    if (_loadedRevision != s.revision) {
      _loadedRevision = s.revision;
      WidgetsBinding.instance.addPostFrameCallback((_) => _load());
    }
    final visible = _category == null ? _products : _products.where((p) => p.categoryId == _category).toList();
    final count = _cart.fold<double>(0, (a, l) => a + l.quantity);
    return Scaffold(
      appBar: AppBar(title: Text(_isSale ? tr('New sale') : tr('New purchase')), actions: const [SyncBadge()]),
      body: Column(
        children: [
          const NeedsOpenDay(),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: TextField(
              controller: _search,
              decoration: InputDecoration(
                hintText: tr('Search product name or code'),
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () {
                          _search.clear();
                          _load();
                        },
                      ),
              ),
              onChanged: (_) => _load(),
            ),
          ),
          if (_categories.isNotEmpty)
            SizedBox(
              height: 44,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  _CategoryChip(label: tr('All'), selected: _category == null, onTap: () => setState(() => _category = null)),
                  for (final c in _categories)
                    _CategoryChip(
                      label: c['name'] as String,
                      selected: _category == c['id'],
                      onTap: () => setState(() => _category = c['id'] as int),
                    ),
                ],
              ),
            ),
          Expanded(
            child: visible.isEmpty
                ? EmptyState(icon: Icons.search_off_rounded, message: tr('No products. Pull down on Home to download the catalogue.'))
                : GridView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                    gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                      maxCrossAxisExtent: 220,
                      mainAxisExtent: 156,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                    ),
                    itemCount: visible.length,
                    itemBuilder: (_, i) {
                      final p = visible[i];
                      final inCart = _cart.where((l) => l.product.id == p.id).firstOrNull;
                      return _ProductTile(
                        product: p,
                        price: _isSale ? p.sellingPrice : p.costPrice,
                        inCart: inCart?.quantity,
                        onTap: () => _add(p),
                      );
                    },
                  ),
          ),
          if (_cart.isNotEmpty)
            Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
                boxShadow: [BoxShadow(color: Color(0x1A0F172A), blurRadius: 24, offset: Offset(0, -6))],
              ),
              child: SafeArea(
                top: false,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 190),
                      child: ListView(
                        shrinkWrap: true,
                        padding: const EdgeInsets.fromLTRB(16, 12, 8, 0),
                        children: [
                          for (final l in _cart)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: InkWell(
                                      onTap: () => _editLine(l),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(l.product.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                                          Text(
                                            '${money(context, l.price, symbol: false)} · ${money(context, _isSale ? l.total : l.quantity * l.price, symbol: false)}',
                                            style: const TextStyle(color: Brand.muted, fontSize: 12.5),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                  _Stepper(
                                    value: l.quantity,
                                    onMinus: () => setState(() {
                                      l.quantity -= 1;
                                      if (l.quantity <= 0) _cart.remove(l);
                                    }),
                                    onPlus: () => setState(() => l.quantity += 1),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 14),
                      child: Row(
                        children: [
                          IconButton(
                            tooltip: tr('Clear'),
                            onPressed: () => setState(_cart.clear),
                            icon: const Icon(Icons.delete_outline_rounded, color: Colors.red),
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: FilledButton(
                              onPressed: _checkout,
                              style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16)),
                              child: Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(8)),
                                    child: Text(qty(count), style: const TextStyle(fontWeight: FontWeight.w800)),
                                  ),
                                  const SizedBox(width: 10),
                                  Text(_isSale ? tr('Checkout') : tr('Save')),
                                  const Spacer(),
                                  Text(money(context, _total), style: const TextStyle(fontWeight: FontWeight.w800)),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(right: 8, bottom: 6),
    child: ChoiceChip(
      label: Text(label),
      selected: selected,
      showCheckmark: false,
      labelStyle: TextStyle(color: selected ? Colors.white : Brand.ink, fontWeight: FontWeight.w700),
      onSelected: (_) => onTap(),
    ),
  );
}

class _ProductTile extends StatelessWidget {
  const _ProductTile({required this.product, required this.price, required this.inCart, required this.onTap});
  final Product product;
  final double price;
  final double? inCart;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final selected = inCart != null;
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: selected ? Brand.teal500 : const Color(0xFFE9EDF2), width: selected ? 2 : 1),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ProductAvatar(name: product.name, size: 40),
                  const Spacer(),
                  if (selected)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                      decoration: BoxDecoration(color: Brand.teal600, borderRadius: BorderRadius.circular(999)),
                      child: Text(qty(inCart!), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
                    ),
                ],
              ),
              const SizedBox(height: 10),
              Text(product.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, height: 1.2)),
              const Spacer(),
              Row(
                children: [
                  Expanded(
                    child: FittedBox(
                      alignment: Alignment.centerLeft,
                      fit: BoxFit.scaleDown,
                      child: Text(money(context, price, symbol: false), style: const TextStyle(color: Brand.teal700, fontWeight: FontWeight.w800, fontSize: 15)),
                    ),
                  ),
                  Pill('${qty(product.stock)} ${product.unit}', color: product.stock <= 0 ? Colors.red : (product.isLow ? Colors.orange : Colors.blueGrey)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Stepper extends StatelessWidget {
  const _Stepper({required this.value, required this.onMinus, required this.onPlus});
  final double value;
  final VoidCallback onMinus;
  final VoidCallback onPlus;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(color: Brand.canvas, borderRadius: BorderRadius.circular(14)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(visualDensity: VisualDensity.compact, onPressed: onMinus, icon: const Icon(Icons.remove_rounded, size: 18)),
        SizedBox(width: 28, child: Text(qty(value), textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w800))),
        IconButton(visualDensity: VisualDensity.compact, onPressed: onPlus, icon: const Icon(Icons.add_rounded, size: 18)),
      ],
    ),
  );
}

class _CheckoutSheet extends StatefulWidget {
  const _CheckoutSheet({required this.mode, required this.lines, required this.subtotal});
  final CartMode mode;
  final List<CartLine> lines;
  final double subtotal;

  @override
  State<_CheckoutSheet> createState() => _CheckoutSheetState();
}

class _CheckoutSheetState extends State<_CheckoutSheet> {
  String _method = 'cash';
  final _paid = TextEditingController();
  final _discount = TextEditingController();
  final _newParty = TextEditingController();
  final _phone = TextEditingController();
  final _invoice = TextEditingController();
  Party? _party;
  List<Party> _parties = [];
  bool _busy = false;

  bool get _isSale => widget.mode == CartMode.sale;
  double get _total => (widget.subtotal - (parseNum(_discount.text) ?? 0)).clamp(0, double.infinity).toDouble();
  double get _balance {
    final paid = _paid.text.trim().isEmpty ? (_method == 'credit' ? 0 : _total) : (parseNum(_paid.text) ?? 0);
    return (_total - paid).clamp(0, double.infinity).toDouble();
  }

  @override
  void initState() {
    super.initState();
    context.read<AppState>().store.parties(_isSale ? 'customers' : 'suppliers').then((p) => mounted ? setState(() => _parties = p) : null);
  }

  Future<void> _save() async {
    final s = context.read<AppState>();
    setState(() => _busy = true);
    try {
      final paid = _paid.text.trim().isEmpty ? null : parseNum(_paid.text);
      if (_isSale) {
        final r = await s.store.recordSale(
          shopId: s.shopId!,
          lines: widget.lines,
          paymentMethod: _method,
          discount: parseNum(_discount.text) ?? 0,
          amountPaid: paid,
          customer: _party,
          customerName: _newParty.text.trim().isEmpty ? null : _newParty.text.trim(),
          customerPhone: _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        );
        await s.recorded();
        if (!mounted) return;
        final messenger = ScaffoldMessenger.of(context);
        final nav = Navigator.of(context, rootNavigator: true);
        Navigator.pop(context, true);
        final debt = r.balance > 0 ? ' (${tr('debt {amount}', {'amount': money(context, r.balance)})})' : '';
        final warnings = r.warnings.isEmpty ? '' : ' · ${r.warnings.join(' ')}';
        messenger
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
            content: Text(tr('Sale saved: {amount}', {'amount': money(context, r.total)}) + debt + warnings),
            duration: const Duration(seconds: 6),
            action: SnackBarAction(label: tr('Receipt'), onPressed: () => nav.push(MaterialPageRoute(builder: (_) => SaleDetailScreen(uuid: r.uuid)))),
          ));
      } else {
        await s.store.recordPurchase(
          shopId: s.shopId!,
          lines: widget.lines,
          paymentMethod: _method,
          amountPaid: paid,
          supplier: _party,
          supplierName: _newParty.text.trim().isEmpty ? null : _newParty.text.trim(),
          invoiceNumber: _invoice.text.trim().isEmpty ? null : _invoice.text.trim(),
        );
        await s.recorded();
        if (!mounted) return;
        Navigator.pop(context, true);
        showMessage(context, tr('Purchase saved and stock increased.'));
      }
    } on LocalValidationException catch (e) {
      showMessage(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final label = _isSale ? tr('Customer') : tr('Supplier');
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(_isSale ? tr('Checkout') : tr('Save purchase'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: 'cash', label: Text(tr('Cash'))),
                ButtonSegment(value: 'mobile_money', label: Text(tr('M-Money'))),
                ButtonSegment(value: 'bank', label: Text(tr('Bank'))),
                ButtonSegment(value: 'credit', label: Text(tr('Credit'))),
              ],
              selected: {_method},
              onSelectionChanged: (v) => setState(() => _method = v.first),
            ),
            const SizedBox(height: 12),
            if (_isSale) ...[
              TextField(
                controller: _discount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: tr('Sale discount')),
                onChanged: (_) => setState(() {}),
              ),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _paid,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: tr('Amount paid'),
                hintText: _method == 'credit' ? '0' : money(context, _total, symbol: false),
                helperText: tr('Leave empty for full payment'),
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<Party?>(
              initialValue: _party,
              isExpanded: true,
              decoration: InputDecoration(labelText: tr('{label} (existing)', {'label': label})),
              items: [
                DropdownMenuItem(value: null, child: Text(_isSale ? tr('Walk-in customer') : tr('None'))),
                for (final p in _parties) DropdownMenuItem(value: p, child: Text(p.name)),
              ],
              onChanged: (v) => setState(() => _party = v),
            ),
            if (_party == null) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _newParty,
                      decoration: InputDecoration(labelText: tr('or new {label} name', {'label': label})),
                    ),
                  ),
                  if (_isSale) ...[
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: _phone,
                        keyboardType: TextInputType.phone,
                        decoration: InputDecoration(labelText: tr('Phone')),
                      ),
                    ),
                  ],
                ],
              ),
            ],
            if (!_isSale) ...[
              const SizedBox(height: 12),
              TextField(
                controller: _invoice,
                decoration: InputDecoration(labelText: tr('Supplier invoice #')),
              ),
            ],
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(tr('Total'), style: Theme.of(context).textTheme.titleMedium),
                Text(money(context, _total), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
              ],
            ),
            if (_balance > 0)
              Text(
                _isSale
                    ? tr('Balance {amount} will be recorded as customer debt.', {'amount': money(context, _balance)})
                    : tr('Balance {amount} will be recorded as supplier debt.', {'amount': money(context, _balance)}),
                style: TextStyle(color: Colors.orange.shade800),
              ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy ? null : _save,
              style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
              child: Text(_isSale ? tr('Save sale') : tr('Save purchase')),
            ),
          ],
        ),
      ),
    );
  }
}
