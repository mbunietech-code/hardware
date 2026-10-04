import 'package:flutter/material.dart';

/// Brand palette shared with the web admin (deep teal + warm amber).
class Brand {
  static const night = Color(0xFF0F172A);
  static const teal50 = Color(0xFFEFFCF9);
  static const teal100 = Color(0xFFC9F5EC);
  static const teal400 = Color(0xFF2BBAA9);
  static const teal500 = Color(0xFF129D8F);
  static const teal600 = Color(0xFF0B7E74);
  static const teal700 = Color(0xFF0D655E);
  static const teal800 = Color(0xFF10504C);
  static const teal900 = Color(0xFF0F3F3C);
  static const teal950 = Color(0xFF042624);
  static const amber = Color(0xFFFBBF24);
  static const amberDark = Color(0xFFF59E0B);
  static const canvas = Color(0xFFF4F6F8);
  static const ink = Color(0xFF0F172A);
  static const muted = Color(0xFF64748B);
  static const line = Color(0xFFE2E8F0);

}

ThemeData buildTheme() {
  final scheme = ColorScheme.fromSeed(
    seedColor: Brand.teal600,
    primary: Brand.teal600,
    secondary: Brand.amber,
    surface: Colors.white,
  );
  final radius = BorderRadius.circular(14);
  final base = ThemeData(useMaterial3: true, colorScheme: scheme, fontFamily: 'PlusJakartaSans');
  return base.copyWith(
    scaffoldBackgroundColor: Brand.canvas,
    textTheme: base.textTheme.apply(bodyColor: Brand.ink, displayColor: Brand.ink).copyWith(
          titleLarge: base.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700, color: Brand.ink),
          titleMedium: base.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700, color: Brand.ink),
        ),
    appBarTheme: const AppBarTheme(
      backgroundColor: Brand.canvas,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(fontFamily: 'PlusJakartaSans', fontSize: 20, fontWeight: FontWeight.w800, color: Brand.ink),
      iconTheme: IconThemeData(color: Brand.ink),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      surfaceTintColor: Colors.transparent,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18), side: const BorderSide(color: Color(0xFFE9EDF2))),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(borderRadius: radius, borderSide: const BorderSide(color: Brand.line)),
      enabledBorder: OutlineInputBorder(borderRadius: radius, borderSide: const BorderSide(color: Brand.line)),
      focusedBorder: OutlineInputBorder(borderRadius: radius, borderSide: const BorderSide(color: Brand.teal500, width: 1.6)),
      labelStyle: const TextStyle(color: Brand.muted),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: Brand.teal600,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
        shape: RoundedRectangleBorder(borderRadius: radius),
        textStyle: const TextStyle(fontFamily: 'PlusJakartaSans', fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: radius),
        side: const BorderSide(color: Brand.line),
        textStyle: const TextStyle(fontFamily: 'PlusJakartaSans', fontWeight: FontWeight.w600),
      ),
    ),
    segmentedButtonTheme: SegmentedButtonThemeData(
      style: ButtonStyle(
        shape: WidgetStatePropertyAll(RoundedRectangleBorder(borderRadius: radius)),
        side: const WidgetStatePropertyAll(BorderSide(color: Brand.line)),
        backgroundColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? Brand.teal100 : Colors.white),
        foregroundColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? Brand.teal800 : Brand.muted),
      ),
    ),
    chipTheme: base.chipTheme.copyWith(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      side: const BorderSide(color: Brand.line),
      backgroundColor: Colors.white,
      selectedColor: Brand.teal600,
      labelStyle: const TextStyle(fontFamily: 'PlusJakartaSans', fontWeight: FontWeight.w600, fontSize: 13),
      secondaryLabelStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
      checkmarkColor: Colors.white,
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      height: 70,
      indicatorColor: Brand.teal100,
      indicatorShape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      labelTextStyle: WidgetStateProperty.resolveWith((s) => TextStyle(
            fontFamily: 'PlusJakartaSans',
            fontSize: 12,
            fontWeight: s.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
            color: s.contains(WidgetState.selected) ? Brand.teal800 : Brand.muted,
          )),
      iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(color: s.contains(WidgetState.selected) ? Brand.teal700 : Brand.muted)),
    ),
    floatingActionButtonTheme: FloatingActionButtonThemeData(
      backgroundColor: Brand.teal600,
      foregroundColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: Brand.ink,
      shape: RoundedRectangleBorder(borderRadius: radius),
    ),
    dialogTheme: DialogThemeData(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)), backgroundColor: Colors.white),
    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: Colors.white,
      showDragHandle: true,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
    ),
    listTileTheme: const ListTileThemeData(iconColor: Brand.teal700),
    dividerTheme: const DividerThemeData(color: Color(0xFFEEF1F5), space: 1),
  );
}

/// Stock photos bundled with the app (Vecteezy free licence: the photographer is credited on screen).
class Photos {
  static const shop = 'assets/images/shop.jpg';
  static const hero = 'assets/images/hero.jpg';
  static const cement = 'assets/images/cement.jpg';

  static const credits = {
    shop: 'Photo: Yelena Akulova / Vecteezy',
    hero: 'Photo: Vitalii Borkovskyi / Vecteezy',
    cement: 'Photo: papan saenkutrueang / Vecteezy',
  };
}
