// Renders phone-sized screenshots of the main screens for design review:
//   flutter test test/screenshots --update-goldens
// PNGs are written next to this file (goldens/). Skipped in normal runs unless SCREENSHOTS=1.
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hardware_bms/data/db.dart';
import 'package:hardware_bms/data/models.dart';
import 'package:hardware_bms/l10n/l10n.dart';
import 'package:hardware_bms/main.dart';
import 'package:hardware_bms/state/app_state.dart';
import 'package:hardware_bms/theme.dart';
import 'package:provider/provider.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

import '../helpers.dart';

Future<void> _loadFonts() async {
  final jakarta = FontLoader('PlusJakartaSans');
  for (final w in ['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold']) {
    final bytes = File('assets/fonts/PlusJakartaSans-$w.ttf').readAsBytesSync();
    jakarta.addFont(Future.value(ByteData.view(Uint8List.fromList(bytes).buffer)));
  }
  await jakarta.load();
  final flutterRoot = Platform.environment['FLUTTER_ROOT'] ?? '${Platform.environment['HOME']}/tools/flutter';
  final icons = FontLoader('MaterialIcons')
    ..addFont(Future.value(ByteData.view(Uint8List.fromList(File('$flutterRoot/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf').readAsBytesSync()).buffer)));
  await icons.load();
}

Future<AppState> _state({bool loggedIn = true, String lang = 'sw'}) async {
  sqfliteFfiInit();
  final db = await AppDb.open(factory: databaseFactoryFfiNoIsolate, path: inMemoryDatabasePath, singleInstance: false);
  await db.setKv('language', lang);
  if (loggedIn) {
    await db.setKv('user', jsonEncode({
      'id': 2, 'name': 'Juma Mussa', 'role': 'shop_admin', 'shop_id': 1, 'currency': 'TZS', 'business_name': 'Mussa Hardware',
      'shop': {'id': 1, 'name': 'Main Shop'}, 'permissions': {'adjust_stock': true},
    }));
  }
  await db.setKv('server_url', 'http://127.0.0.1:9');
  final state = AppState(db: db, tokens: MemoryTokenStorage()..token = loggedIn ? 't' : null, watchConnectivity: false);
  final pull = samplePull(cementQty: 120);
  (pull['products'] as List).addAll(<Map<String, Object>>[
    {'id': 3, 'category_id': 1, 'code': 'RB-12', 'name': 'Rebar Y12 (12m)', 'unit': 'pcs', 'cost_price': '21000', 'selling_price': '25000', 'reorder_level': '30', 'is_active': true},
    {'id': 4, 'category_id': 2, 'code': 'IRS-28', 'name': 'Iron sheet G28 3m', 'unit': 'pcs', 'cost_price': '24000', 'selling_price': '28500', 'reorder_level': '25', 'is_active': true},
    {'id': 5, 'category_id': 2, 'code': 'PNT-W4', 'name': 'White emulsion 4L', 'unit': 'tin', 'cost_price': '28000', 'selling_price': '35000', 'reorder_level': '6', 'is_active': true},
    {'id': 6, 'category_id': 1, 'code': 'PVC-1', 'name': 'PVC pipe 1 inch', 'unit': 'pcs', 'cost_price': '6500', 'selling_price': '8500', 'reorder_level': '15', 'is_active': true},
  ]);
  (pull['categories'] as List<Map<String, Object>>).add({'id': 2, 'name': 'Roofing', 'is_active': true});
  (pull['stock'] as List).addAll(<Map<String, Object>>[
    {'shop_id': 1, 'product_id': 3, 'quantity': '133'}, {'shop_id': 1, 'product_id': 4, 'quantity': '12'},
    {'shop_id': 1, 'product_id': 5, 'quantity': '40'}, {'shop_id': 1, 'product_id': 6, 'quantity': '142'},
  ]);
  await state.store.applyPull(pull);
  await state.init();
  if (loggedIn) {
    state.store.userId = 2;
    await state.store.openDay(1, openingCash: 50000);
    final products = await state.store.products(shopId: 1);
    for (final p in products.take(4)) {
      await state.store.recordSale(shopId: 1, lines: [CartLine(p, quantity: 2)], paymentMethod: 'cash');
    }
    await state.store.recordExpense(shopId: 1, categoryId: 1, categoryName: 'Rent', amount: 15000, reason: 'Usafiri');
    await state.refresh();
  }
  while (state.syncing) {
    await Future<void>.delayed(const Duration(milliseconds: 20));
  }
  return state;
}

void main() {
  final enabled = Platform.environment['SCREENSHOTS'] == '1';

  Future<void> shoot(WidgetTester tester, AppState state, String name, [Future<void> Function()? act]) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    await tester.pumpWidget(ChangeNotifierProvider.value(value: state, child: const HardwareApp()));
    await tester.pumpAndSettle();
    if (act != null) await act();
    // Decode the bundled photos for real so they appear in the screenshot.
    final ctx = tester.element(find.byType(Scaffold).first);
    await tester.runAsync(() async {
      for (final p in [Photos.shop, Photos.hero, Photos.cement]) {
        await precacheImage(AssetImage(p), ctx);
      }
    });
    await tester.pumpAndSettle();
    await expectLater(find.byType(HardwareApp), matchesGoldenFile('goldens/$name.png'));
    await tester.pumpWidget(const SizedBox());
    state.dispose();
    tester.view.reset();
  }

  testWidgets('login', (tester) async {
    late AppState s;
    await tester.runAsync(() async {
      await _loadFonts();
      s = await _state(loggedIn: false);
    });
    L10n.lang = 'sw';
    await shoot(tester, s, 'login');
  }, skip: !enabled);

  testWidgets('dashboard', (tester) async {
    late AppState s;
    await tester.runAsync(() async {
      await _loadFonts();
      s = await _state();
    });
    await shoot(tester, s, 'dashboard');
  }, skip: !enabled);

  testWidgets('sell', (tester) async {
    late AppState s;
    await tester.runAsync(() async {
      await _loadFonts();
      s = await _state();
    });
    await shoot(tester, s, 'sell', () async {
      await tester.tap(find.text('Uza').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cement 50kg'));
      await tester.tap(find.text('Cement 50kg'));
      await tester.tap(find.text('Rebar Y12 (12m)'));
      await tester.pumpAndSettle();
    });
  }, skip: !enabled);
}
