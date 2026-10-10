import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/api_client.dart';
import '../l10n/l10n.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';

/// New product (needs internet so the code is checked against all shops).
class AddProductScreen extends StatefulWidget {
  const AddProductScreen({super.key});

  @override
  State<AddProductScreen> createState() => _AddProductScreenState();
}

class _AddProductScreenState extends State<AddProductScreen> {
  final _form = GlobalKey<FormState>();
  final _code = TextEditingController();
  final _name = TextEditingController();
  final _unit = TextEditingController(text: 'pcs');
  final _cost = TextEditingController();
  final _price = TextEditingController();
  final _reorder = TextEditingController(text: '0');
  int? _category;
  bool _busy = false;

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await context.read<AppState>().addProductOnline({
        'code': _code.text.trim(),
        'name': _name.text.trim(),
        'unit': _unit.text.trim(),
        'category_id': _category,
        'cost_price': parseNum(_cost.text) ?? 0,
        'selling_price': parseNum(_price.text) ?? 0,
        'reorder_level': parseNum(_reorder.text) ?? 0,
      });
      if (!mounted) return;
      showMessage(context, tr('Product created. Use a purchase or stock adjustment to add opening stock.'));
      Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.isNetwork ? tr('Adding a product needs internet.') : e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String? _required(String? v) => (v ?? '').trim().isEmpty ? tr('Required') : null;
  String? _number(String? v) => parseNum(v ?? '') == null ? tr('Enter a number') : null;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(title: Text(tr('New product'))),
      body: Form(
        key: _form,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          TextFormField(controller: _code, decoration: InputDecoration(labelText: '${tr('Code / SKU')} *'), validator: _required),
          const SizedBox(height: 12),
          TextFormField(controller: _name, decoration: InputDecoration(labelText: tr('Name *')), validator: _required),
          const SizedBox(height: 12),
          FutureBuilder(
            future: s.store.categories(),
            builder: (context, snap) => DropdownButtonFormField<int?>(
              initialValue: _category,
              decoration: InputDecoration(labelText: tr('Category')),
              items: [
                DropdownMenuItem(value: null, child: Text(tr('None'))),
                for (final c in snap.data ?? const <Map<String, Object?>>[]) DropdownMenuItem(value: c['id'] as int, child: Text(c['name'] as String)),
              ],
              onChanged: (v) => setState(() => _category = v),
            ),
          ),
          const SizedBox(height: 12),
          TextFormField(controller: _unit, decoration: InputDecoration(labelText: '${tr('Unit')} *'), validator: _required),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: TextFormField(controller: _cost, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: '${tr('Cost price')} *'), validator: _number)),
            const SizedBox(width: 12),
            Expanded(child: TextFormField(controller: _price, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: '${tr('Selling price')} *'), validator: _number)),
          ]),
          const SizedBox(height: 12),
          TextFormField(controller: _reorder, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: tr('Reorder level')), validator: _number),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _save, child: _busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text(tr('Save product'))),
        ]),
      ),
    );
  }
}
