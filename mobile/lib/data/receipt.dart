import 'dart:convert';

import 'package:intl/intl.dart';

import '../l10n/l10n.dart';
import 'models.dart';

/// Plain-text receipt for sharing through WhatsApp, SMS or any app.
String buildReceipt({
  required QueueItem sale,
  required Map<int, String> productNames,
  required String businessName,
  required String shopName,
  required String currency,
  String? footer,
}) {
  final p = jsonDecode(sale.payload) as Map<String, dynamic>;
  final f = NumberFormat('#,##0.##');
  final items = (p['items'] as List).cast<Map<String, dynamic>>();
  final b = StringBuffer()
    ..writeln(businessName.toUpperCase())
    ..writeln(shopName)
    ..writeln('--------------------------------')
    ..writeln('${tr('Receipt')}: ${sale.reference ?? sale.localUuid.substring(0, 8).toUpperCase()}')
    ..writeln(DateFormat('dd/MM/yyyy HH:mm').format(sale.createdAt.toLocal()))
    ..writeln('--------------------------------');
  for (final i in items) {
    final qty = toDouble(i['quantity']);
    final price = toDouble(i['unit_price']);
    final disc = toDouble(i['discount']);
    b
      ..writeln(productNames[i['product_id']] ?? '#${i['product_id']}')
      ..writeln('  ${f.format(qty)} x ${f.format(price)}${disc > 0 ? ' - ${f.format(disc)}' : ''} = ${f.format(qty * price - disc)}');
  }
  final discount = toDouble(p['discount']);
  final paid = toDouble(p['amount_paid']);
  b.writeln('--------------------------------');
  if (discount > 0) b.writeln('${tr('Discount')}: -${f.format(discount)}');
  b
    ..writeln('${tr('TOTAL')}: $currency ${f.format(sale.amount)}')
    ..writeln('${tr('Paid')} (${tr(p['payment_method'] as String)}): ${f.format(paid)}');
  if (sale.amount - paid > 0.004) b.writeln('${tr('Balance')}: ${f.format(sale.amount - paid)}');
  if (footer != null && footer.isNotEmpty) {
    b
      ..writeln('--------------------------------')
      ..writeln(footer);
  }
  return b.toString();
}
