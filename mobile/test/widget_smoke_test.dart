import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hardware_bms/data/db.dart';
import 'package:hardware_bms/l10n/l10n.dart';
import 'package:hardware_bms/main.dart';
import 'package:hardware_bms/state/app_state.dart';
import 'package:provider/provider.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

import 'helpers.dart';

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
    expect(find.text('Sync status'), findsOneWidget);

    // Language switch on the More screen turns the whole app into Swahili.
    await tester.tap(find.text('SW'));
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();
    expect(find.text('Hali ya sync'), findsOneWidget);
    expect(find.text('Uza'), findsOneWidget);
    expect(find.text('Madeni'), findsWidgets);

    await tester.tap(find.text('Hali ya sync'));
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
}
