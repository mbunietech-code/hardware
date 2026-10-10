import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'data/db.dart';
import 'screens/change_password_screen.dart';
import 'screens/day_screen.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'screens/sync_screen.dart';
import 'state/app_state.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final db = await AppDb.open();
  final state = AppState(db: db);
  await state.init();
  runApp(ChangeNotifierProvider.value(value: state, child: const HardwareApp()));
}

class HardwareApp extends StatelessWidget {
  const HardwareApp({super.key});

  @override
  Widget build(BuildContext context) {
    final language = context.select<AppState, String>((s) => s.language);
    return MaterialApp(
      title: 'Hardware BMS',
      debugShowCheckedModeBanner: false,
      locale: Locale(language),
      supportedLocales: const [Locale('en'), Locale('sw')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: buildTheme(),
      routes: {'/sync': (_) => const SyncScreen(), '/day': (_) => const DayScreen()},
      home: const _Root(),
    );
  }
}

class _Root extends StatelessWidget {
  const _Root();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    if (!s.loggedIn || s.sessionExpired) return const LoginScreen();
    if (s.mustChangePassword) return const ChangePasswordScreen(forced: true);
    if (s.shopId == null) return const ShopPicker();
    return const HomeShell();
  }
}
