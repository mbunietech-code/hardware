import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hardware_bms/data/db.dart';
import 'package:hardware_bms/data/models.dart';
import 'package:hardware_bms/l10n/l10n.dart';
import 'package:hardware_bms/main.dart';
import 'package:hardware_bms/state/app_state.dart';
import 'package:provider/provider.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

import 'helpers.dart';

/// Scrolls [finder] into view clear of the bottom navigation bar, then taps it.
Future<void> tapVisible(WidgetTester tester, Finder finder) async {
  await tester.scrollUntilVisible(finder, 200);
  await tester.drag(find.byType(Scrollable).first, const Offset(0, -150));
  await tester.pumpAndSettle();
  await tester.tap(finder);
  await tester.pumpAndSettle();
}

void main() {
  setUp(() => L10n.lang = 'en');

  testWidgets('logged-in user can move through all main screens', (tester) async {
    late AppState state;
    await tester.runAsync(() async {
      sqfliteFfiInit();
      final db = await AppDb.open(factory: databaseFactoryFfiNoIsolate, path: inMemoryDatabasePath, singleInstance: false);
      await db.setKv('user', jsonEncode({
        'id': 2, 'name': 'Shop Admin', 'role': 'shop_admin', 'shop_id': 1, 'currency': 'TZS', 'business_name': 'Test Hardware',
        'permissions': {'adjust_stock': true},
      }));
      await db.setKv('server_url', 'http://127.0.0.1:9'); // unreachable: app must work offline
      final tokens = MemoryTokenStorage()..token = 'test';
      state = AppState(db: db, tokens: tokens, watchConnectivity: false);
      await state.store.applyPull(samplePull());
      await state.init();
      await state.store.openDay(1, openingCash: 1000);
      while (state.syncing) {
        await Future<void>.delayed(const Duration(milliseconds: 20));
      }
      await state.refresh();
    });

    await tester.pumpWidget(ChangeNotifierProvider.value(value: state, child: const HardwareApp()));
    await tester.pumpAndSettle();
    expect(find.text('Test Hardware'), findsOneWidget);
    expect(find.text('Business day is open'), findsOneWidget);

    await tester.tap(find.text('Sell'));
    await tester.pumpAndSettle();
    expect(find.text('Cement 50kg'), findsOneWidget);
    await tester.tap(find.text('Cement 50kg'));
    await tester.pumpAndSettle();
    expect(find.text('Checkout'), findsOneWidget);

    await tester.tap(find.text('Stock'));
    await tester.pumpAndSettle();
    expect(find.text('10 bag'), findsOneWidget);

    await tester.tap(find.text('Debts'));
    await tester.pumpAndSettle();
    expect(find.text('No outstanding debts.'), findsOneWidget);

    await tester.tap(find.text('More'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Sync status'), 200);
    expect(find.text('Sync status'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('SW'), -200);

    // Language switch on the More screen turns the whole app into Swahili.
    await tester.tap(find.text('SW'));
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();
    expect(find.text('Hali ya sync'), findsOneWidget);
    expect(find.text('Uza'), findsOneWidget);
    expect(find.text('Madeni'), findsWidgets);

    await tapVisible(tester, find.text('Hali ya sync'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Zinasubiri'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });

  testWidgets('login screen shows when not logged in', (tester) async {
    late AppState state;
    await tester.runAsync(() async {
      sqfliteFfiInit();
      final db = await AppDb.open(factory: databaseFactoryFfiNoIsolate, path: inMemoryDatabasePath, singleInstance: false);
      state = AppState(db: db, tokens: MemoryTokenStorage(), watchConnectivity: false);
      await state.init();
    });
    await tester.pumpWidget(ChangeNotifierProvider.value(value: state, child: const HardwareApp()));
    await tester.pumpAndSettle();
    expect(find.text('Log in'), findsOneWidget);
    expect(find.text('Email or phone'), findsOneWidget);

    // Switch to Swahili from the login screen.
    await tester.tap(find.text('SW'));
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();
    expect(find.text('Ingia'), findsOneWidget);
    expect(find.text('Barua pepe au simu'), findsOneWidget);
    expect(await tester.runAsync(() => state.db.getKv('language')), 'sw');
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });

  testWidgets('history, sale detail with receipt, reports offline, customers', (tester) async {
    late AppState state;
    await tester.runAsync(() async {
      sqfliteFfiInit();
      final db = await AppDb.open(factory: databaseFactoryFfiNoIsolate, path: inMemoryDatabasePath, singleInstance: false);
      await db.setKv('user', jsonEncode({'id': 2, 'name': 'Shop Admin', 'role': 'shop_admin', 'shop_id': 1, 'currency': 'TZS', 'business_name': 'Test Hardware',
        'permissions': {'process_returns': true}}));
      await db.setKv('server_url', 'http://127.0.0.1:9');
      state = AppState(db: db, tokens: MemoryTokenStorage()..token = 't', watchConnectivity: false);
      await state.store.applyPull(samplePull());
      await state.init();
      await state.store.openDay(1);
      final p = (await state.store.products(shopId: 1)).first;
      await state.store.recordSale(shopId: 1, lines: [CartLine(p, quantity: 2)], paymentMethod: 'cash');
      await state.store.addParty('customers', 'Mama Asha', '0754000111');
      while (state.syncing) {
        await Future<void>.delayed(const Duration(milliseconds: 20));
      }
      await state.refresh();
    });
    await tester.pumpWidget(ChangeNotifierProvider.value(value: state, child: const HardwareApp()));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(find.text('History'), 300);
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -250)); // clear the bottom navigation bar
    await tester.pumpAndSettle();
    await tester.tap(find.text('History'));
    await tester.pumpAndSettle();
    expect(find.text('Sales history'), findsOneWidget);
    await tester.tap(find.byType(ListTile).first);
    await tester.pumpAndSettle();
    expect(find.textContaining('TEST HARDWARE'), findsOneWidget); // receipt text
    await tester.scrollUntilVisible(find.text('Return items'), 200);
    expect(find.text('Share receipt (WhatsApp, SMS…)'), findsOneWidget);
    expect(find.text('Return items'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.pageBack();
    await tester.pumpAndSettle();

    await tester.tap(find.text('Reports'));
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 300)));
    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.wifi_off_rounded), findsOneWidget); // offline fallback with today's local totals
    expect(find.text('Sales today'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();

    await tester.tap(find.text('Customers'));
    await tester.pumpAndSettle();
    expect(find.text('Mama Asha'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });

  testWidgets('default password forces the change-password screen', (tester) async {
    late AppState state;
    await tester.runAsync(() async {
      sqfliteFfiInit();
      final db = await AppDb.open(factory: databaseFactoryFfiNoIsolate, path: inMemoryDatabasePath, singleInstance: false);
      await db.setKv('user', jsonEncode({'id': 2, 'name': 'Shop Admin', 'role': 'shop_admin', 'shop_id': 1, 'must_change_password': true}));
      state = AppState(db: db, tokens: MemoryTokenStorage()..token = 't', watchConnectivity: false);
      await state.init();
      while (state.syncing) {
        await Future<void>.delayed(const Duration(milliseconds: 20));
      }
    });
    await tester.pumpWidget(ChangeNotifierProvider.value(value: state, child: const HardwareApp()));
    await tester.pumpAndSettle();
    expect(find.text('Please choose your own password before continuing.'), findsOneWidget);
    expect(find.text('Sell'), findsNothing);
    await tester.pumpWidget(const SizedBox());
    state.dispose();
  });
}
