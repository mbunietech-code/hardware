import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../data/local_store.dart';
import '../data/models.dart';
import '../l10n/l10n.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'cart_screen.dart';
import 'expense_screen.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key, required this.onNavigate});

  final ValueChanged<int> onNavigate;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    final shopId = s.shopId!;
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => s.syncNow(),
        child: FutureBuilder(
          key: ValueKey(s.revision),
          future: Future.wait([
            s.store.dayTotals(shopId, today()),
            s.store.products(shopId: shopId, lowOnly: true),
            s.store.notifications(),
            s.store.activity(shopId: shopId, date: today()),
          ]),
          builder: (context, snap) {
            if (!snap.hasData) return const Center(child: CircularProgressIndicator());
            final totals = snap.data![0] as Map<String, double>;
            final low = snap.data![1] as List<Product>;
            final notes = snap.data![2] as List<Map<String, Object?>>;
            final activity = snap.data![3] as List<QueueItem>;
            return ListView(
              padding: EdgeInsets.zero,
              children: [
                _Hero(totals: totals, onSell: () => onNavigate(1)),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _DayCard(session: s.todaySession),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: StatCard(
                              icon: Icons.receipt_long_rounded,
                              tone: Colors.amber,
                              label: tr('Expenses today'),
                              value: money(context, totals['expenses_total']!),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: StatCard(
                              icon: Icons.inventory_2_rounded,
                              tone: low.isEmpty ? Colors.teal : Colors.red,
                              label: tr('Low stock'),
                              value: '${low.length}',
                              sub: low.isEmpty ? tr('All good') : tr('Reorder soon'),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 22),
                      SectionTitle(tr('Quick actions')),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          _QuickAction(icon: Icons.local_shipping_rounded, color: Colors.blue, label: tr('Purchase'), onTap: () => _push(context, const CartScreen(mode: CartMode.purchase))),
                          _QuickAction(icon: Icons.receipt_long_rounded, color: Colors.orange, label: tr('Expense'), onTap: () => _push(context, const ExpenseScreen())),
                          _QuickAction(icon: Icons.payments_rounded, color: Colors.purple, label: tr('Debts'), onTap: () => onNavigate(3)),
                          _QuickAction(icon: Icons.inventory_rounded, color: Colors.teal, label: tr('Stock'), onTap: () => onNavigate(2)),
                        ],
                      ),
                      if (notes.isNotEmpty) ...[
                        const SizedBox(height: 22),
                        SectionTitle(tr('Alerts')),
                        const SizedBox(height: 10),
                        for (final n in notes.take(4))
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Card(
                              child: ListTile(
                                leading: const IconBubble(icon: Icons.notifications_active_rounded, color: Colors.orange),
                                title: Text('${n['title']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                                subtitle: Text('${n['message']}', style: const TextStyle(fontSize: 12.5)),
                              ),
                            ),
                          ),
                      ],
                      if (low.isNotEmpty) ...[
                        const SizedBox(height: 22),
                        SectionTitle(tr('Low stock ({n})', {'n': low.length}), action: tr('View'), onAction: () => onNavigate(2)),
                        const SizedBox(height: 10),
                        Card(
                          child: Column(
                            children: [
                              for (final p in low.take(5))
                                ListTile(
                                  leading: ProductAvatar(name: p.name),
                                  title: Text(p.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                                  subtitle: Text(p.code),
                                  trailing: Pill('${qty(p.stock)} ${p.unit}', color: Colors.red),
                                ),
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 22),
                      SectionTitle(tr("Today's activity")),
                      const SizedBox(height: 10),
                      if (activity.isEmpty)
                        Card(child: EmptyState(icon: Icons.event_note_rounded, message: tr('Nothing recorded yet today.')))
                      else
                        Card(
                          child: Column(
                            children: [
                              for (final a in activity.take(20)) ...[
                                ListTile(
                                  leading: IconBubble(icon: _icon(a.entity), color: _color(a.entity)),
                                  title: Text(
                                    '${_label(a.entity)}${a.reference != null ? ' · ${a.reference}' : ''}',
                                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                                  ),
                                  subtitle: Text(a.summary, maxLines: 1, overflow: TextOverflow.ellipsis),
                                  trailing: Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    crossAxisAlignment: CrossAxisAlignment.end,
                                    children: [
                                      if (a.amount > 0) Text(money(context, a.amount, symbol: false), style: const TextStyle(fontWeight: FontWeight.w800)),
                                      const SizedBox(height: 4),
                                      StatusChip(a.status),
                                    ],
                                  ),
                                ),
                                if (a != activity.take(20).last) const Divider(indent: 72),
                              ],
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  static void _push(BuildContext context, Widget screen) => Navigator.push(context, MaterialPageRoute(builder: (_) => screen));

  static IconData _icon(String e) => switch (e) {
    'sale' => Icons.shopping_cart_rounded,
    'purchase' => Icons.local_shipping_rounded,
    'expense' => Icons.receipt_long_rounded,
    'debt' || 'debt_payment' => Icons.payments_rounded,
    'stock_adjustment' => Icons.tune_rounded,
    'capital_entry' => Icons.savings_rounded,
    'daily_session_open' => Icons.lock_open_rounded,
    'daily_session_close' => Icons.lock_rounded,
    _ => Icons.person_add_alt_rounded,
  };

  static MaterialColor _color(String e) => switch (e) {
    'sale' => Colors.teal,
    'purchase' => Colors.blue,
    'expense' => Colors.orange,
    'debt' || 'debt_payment' => Colors.purple,
    'capital_entry' => Colors.indigo,
    _ => Colors.blueGrey,
  };

  static String _label(String e) => switch (e) {
    'sale' => tr('Sale'),
    'purchase' => tr('Purchase'),
    'expense' => tr('Expense'),
    'debt' => tr('Debt'),
    'debt_payment' => tr('Debt payment'),
    'stock_adjustment' => tr('Stock adjustment'),
    'capital_entry' => tr('Capital'),
    'daily_session_open' => tr('Day opened'),
    'daily_session_close' => tr('Day closed'),
    'customer' => tr('New customer'),
    'supplier' => tr('New supplier'),
    _ => e,
  };
}

/// Gradient header: greeting, today's sales and the main "Sell now" action.
class _Hero extends StatelessWidget {
  const _Hero({required this.totals, required this.onSell});
  final Map<String, double> totals;
  final VoidCallback onSell;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    final hour = DateTime.now().hour;
    final first = '${s.user?['name'] ?? ''}'.split(' ').first;
    final greeting = hour < 12
        ? tr('Good morning, {name}', {'name': first})
        : hour < 17
            ? tr('Good afternoon, {name}', {'name': first})
            : tr('Good evening, {name}', {'name': first});
    String date;
    try {
      date = DateFormat('EEEE, d MMMM y', L10n.lang).format(DateTime.now());
    } catch (_) {
      date = DateFormat('EEEE, d MMMM y').format(DateTime.now()); // locale data not loaded yet
    }
    return PhotoHeader(
      image: Photos.hero,
      radius: 30,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 12, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: .15), borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.white24)),
                    child: const Icon(Icons.storefront_rounded, color: Colors.white, size: 22),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      s.businessName,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16),
                    ),
                  ),
                  const LanguageButton(light: true),
                  const SizedBox(width: 6),
                  const SyncBadge(light: true),
                ],
              ),
              const SizedBox(height: 22),
              Text(date, style: TextStyle(color: Colors.white.withValues(alpha: .7), fontSize: 13)),
              const SizedBox(height: 4),
              Text('$greeting 👋', style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
              const SizedBox(height: 18),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                margin: const EdgeInsets.only(right: 8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .1),
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: Colors.white.withValues(alpha: .15)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tr('Sales today').toUpperCase(),
                      style: TextStyle(color: Colors.white.withValues(alpha: .7), fontSize: 11.5, fontWeight: FontWeight.w700, letterSpacing: 1),
                    ),
                    const SizedBox(height: 6),
                    FittedBox(
                      child: Text(
                        money(context, totals['sales_total']!),
                        style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${tr('{n} sale(s)', {'n': totals['sales_count']!.toInt()})} · ${tr('Purchases {amount}', {'amount': money(context, totals['purchases_total']!, symbol: false)})}',
                      style: TextStyle(color: Colors.white.withValues(alpha: .75), fontSize: 12.5),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: onSell,
                        style: FilledButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: Brand.ink,
                          padding: const EdgeInsets.symmetric(vertical: 17),
                          textStyle: const TextStyle(fontFamily: 'PlusJakartaSans', fontWeight: FontWeight.w800, fontSize: 16, letterSpacing: .5),
                        ),
                        icon: const Icon(Icons.add_shopping_cart_rounded),
                        label: Text(tr('Sell now').toUpperCase()),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DayCard extends StatelessWidget {
  const _DayCard({required this.session});
  final DailySession? session;

  @override
  Widget build(BuildContext context) {
    final open = session?.isOpen ?? false;
    final color = session == null ? Colors.amber : (open ? Colors.green : Colors.blueGrey);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            IconBubble(icon: open ? Icons.lock_open_rounded : Icons.lock_rounded, color: color, size: 46),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    session == null ? tr('Business day not opened') : (open ? tr('Business day is open') : tr('Business day closed')),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 2),
                  Text(today(), style: const TextStyle(color: Brand.muted, fontSize: 12.5)),
                ],
              ),
            ),
            FilledButton.tonal(
              onPressed: () => Navigator.pushNamed(context, '/day'),
              style: FilledButton.styleFrom(
                backgroundColor: Brand.teal100,
                foregroundColor: Brand.teal800,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              ),
              child: Text(session == null ? tr('Open day') : (open ? tr('Close day') : tr('View'))),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.color, required this.label, required this.onTap});
  final IconData icon;
  final MaterialColor color;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(color: color.shade50, borderRadius: BorderRadius.circular(18)),
              child: Icon(icon, color: color.shade700, size: 26),
            ),
            const SizedBox(height: 8),
            Text(label, textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    ),
  );
}
