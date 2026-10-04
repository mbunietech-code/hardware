import 'dart:convert';

import 'package:hardware_bms/data/db.dart';
import 'package:hardware_bms/data/local_store.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

Future<AppDb> memoryDb() async {
  sqfliteFfiInit();
  return AppDb.open(factory: databaseFactoryFfi, path: inMemoryDatabasePath, singleInstance: false);
}

/// A pull payload shaped like the Laravel /sync/pull response.
Map<String, dynamic> samplePull({double cementQty = 10}) => {
      'server_time': '2026-10-04T10:00:00+03:00',
      'full': true,
      'shops': [
        {'id': 1, 'name': 'Main Shop', 'code': 'MAIN', 'is_active': true},
      ],
      'settings': {'require_open_day': true, 'negative_stock_policy': 'block', 'sell_below_cost_policy': 'warn', 'discounts_enabled': true, 'auto_debt_from_credit': true},
      'categories': [
        {'id': 1, 'name': 'Cement', 'is_active': true},
      ],
      'expense_categories': [
        {'id': 1, 'name': 'Rent', 'is_active': true},
      ],
      'products': [
        {'id': 1, 'category_id': 1, 'code': 'CEM-50', 'name': 'Cement 50kg', 'unit': 'bag', 'cost_price': '16000.00', 'selling_price': '19000.00', 'reorder_level': '5.000', 'is_active': true},
        {'id': 2, 'category_id': 1, 'code': 'NAIL', 'name': 'Nails 1kg', 'unit': 'kg', 'cost_price': '4000.00', 'selling_price': '5500.00', 'reorder_level': '0', 'is_active': true},
      ],
      'customers': [],
      'suppliers': [],
      'stock': [
        {'shop_id': 1, 'product_id': 1, 'quantity': '$cementQty'},
        {'shop_id': 1, 'product_id': 2, 'quantity': '3'},
      ],
      'debts': [],
      'daily_sessions': [],
      'notifications': [],
    };

Future<LocalStore> seededStore({double cementQty = 10}) async {
  final store = LocalStore(await memoryDb());
  store.userId = 2;
  await store.applyPull(samplePull(cementQty: cementQty));
  return store;
}

Map<String, dynamic> decode(String s) => jsonDecode(s) as Map<String, dynamic>;
