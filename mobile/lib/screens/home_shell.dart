import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/app_state.dart';
import 'cart_screen.dart';
import 'dashboard_screen.dart';
import 'debts_screen.dart';
import 'more_screen.dart';
import 'stock_screen.dart';
import '../l10n/l10n.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    context.select<AppState, String>((s) => s.language); // rebuild labels when the language changes
    final pages = [
      DashboardScreen(onNavigate: (i) => setState(() => _index = i)),
      const CartScreen(mode: CartMode.sale),
      StockScreen(),
      DebtsScreen(),
      MoreScreen(),
    ];
    return Scaffold(
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: tr('Home')),
          NavigationDestination(icon: Icon(Icons.point_of_sale_outlined), selectedIcon: Icon(Icons.point_of_sale), label: tr('Sell')),
          NavigationDestination(icon: Icon(Icons.inventory_2_outlined), selectedIcon: Icon(Icons.inventory_2), label: tr('Stock')),
          NavigationDestination(
            icon: Icon(Icons.account_balance_wallet_outlined),
            selectedIcon: Icon(Icons.account_balance_wallet),
            label: tr('Debts'),
          ),
          NavigationDestination(icon: Icon(Icons.menu), label: tr('More')),
        ],
      ),
    );
  }
}

/// Super Admins have no fixed shop on mobile: they choose one.
class ShopPicker extends StatefulWidget {
  const ShopPicker({super.key});

  @override
  State<ShopPicker> createState() => _ShopPickerState();
}

class _ShopPickerState extends State<ShopPicker> {
  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      appBar: AppBar(title: Text(tr('Choose shop'))),
      body: FutureBuilder(
        future: s.store.shops(),
        builder: (context, snap) {
          final shops = snap.data ?? [];
          if (shops.isEmpty) {
            return Center(
              child: FilledButton(onPressed: () => s.syncNow().then((_) => setState(() {})), child: Text(tr('Download shops'))),
            );
          }
          return ListView(
            children: [
              for (final shop in shops)
                ListTile(
                  leading: const Icon(Icons.store),
                  title: Text(shop['name'] as String),
                  subtitle: Text((shop['location'] ?? '') as String),
                  onTap: () => s.selectShop(shop['id'] as int),
                ),
            ],
          );
        },
      ),
    );
  }
}
