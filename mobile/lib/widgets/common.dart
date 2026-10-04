import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../state/app_state.dart';
import '../l10n/l10n.dart';
import '../theme.dart';

String money(BuildContext context, num v, {bool symbol = true}) {
  final f = NumberFormat('#,##0.##');
  return symbol ? '${context.read<AppState>().currency} ${f.format(v)}' : f.format(v);
}

String qty(num v) => v == v.roundToDouble() ? v.toStringAsFixed(0) : NumberFormat('#,##0.###').format(v);

void showMessage(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), backgroundColor: error ? Colors.red.shade700 : null, behavior: SnackBarBehavior.floating));
}

/// Online / offline / pending indicator shown in app bars (Doc 17: show sync status).
class SyncBadge extends StatelessWidget {
  const SyncBadge({super.key, this.light = false});

  /// White variant for use on the gradient header.
  final bool light;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    final color = s.sessionExpired ? Colors.red : (s.online ? const Color(0xFF22C55E) : Colors.orange);
    final label = s.syncing
        ? tr('Syncing…')
        : s.sessionExpired
        ? tr('Log in')
        : '${s.online ? tr('Online') : tr('Offline')}${s.unsynced > 0 ? ' · ${s.unsynced}' : ''}';
    return Padding(
      padding: const EdgeInsets.only(right: 8, left: 2),
      child: Material(
        color: light ? Colors.white.withValues(alpha: .12) : Colors.white,
        shape: StadiumBorder(side: BorderSide(color: light ? Colors.white.withValues(alpha: .2) : Brand.line)),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: () => Navigator.of(context).pushNamed('/sync'),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                s.syncing
                    ? SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 2, color: light ? Colors.white : Brand.teal600))
                    : Container(width: 9, height: 9, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
                const SizedBox(width: 7),
                Text(label, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: light ? Colors.white : Brand.ink)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key});
  final String status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'synced' || 'paid' || 'accepted' || 'closed' => Colors.green,
      'pending' || 'syncing' || 'partial' || 'open' || 'retry' => Colors.orange,
      'rejected' || 'conflict' || 'unpaid' || 'cancelled' => Colors.red,
      _ => Colors.blueGrey,
    };
    return Pill(tr(status), color: color);
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.message});
  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(color: const Color(0xFFEEF2F6), borderRadius: BorderRadius.circular(20)),
            child: Icon(icon, size: 30, color: const Color(0xFF94A3B8)),
          ),
          const SizedBox(height: 14),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Brand.muted, fontSize: 13.5),
          ),
        ],
      ),
    ),
  );
}

/// Shown on transaction screens when the business day is not open.
class NeedsOpenDay extends StatelessWidget {
  const NeedsOpenDay({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    if (s.todaySession?.isOpen ?? false) return const SizedBox.shrink();
    return MaterialBanner(
      backgroundColor: Colors.amber.shade50,
      content: Text(s.todaySession == null ? tr('Today is not open yet.') : tr('Today is closed.')),
      actions: [if (s.todaySession == null) TextButton(onPressed: () => Navigator.of(context).pushNamed('/day'), child: Text(tr('OPEN DAY')))],
    );
  }
}

Future<double?> askNumber(BuildContext context, String title, {double? initial, String? hint}) async {
  final c = TextEditingController(text: initial == null ? '' : qty(initial));
  return showDialog<double>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: c,
        autofocus: true,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(hintText: hint),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text(tr('Cancel'))),
        FilledButton(onPressed: () => Navigator.pop(ctx, double.tryParse(c.text.replaceAll(',', ''))), child: Text(tr('OK'))),
      ],
    ),
  );
}

double? parseNum(String s) => double.tryParse(s.replaceAll(',', '').trim());

/// EN / SW switch. Changes every screen immediately and is remembered on the phone.
class LanguageSwitch extends StatelessWidget {
  const LanguageSwitch({super.key});

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return SegmentedButton<String>(
      showSelectedIcon: false,
      style: const ButtonStyle(visualDensity: VisualDensity.compact),
      segments: const [
        ButtonSegment(value: 'en', label: Text('EN')),
        ButtonSegment(value: 'sw', label: Text('SW')),
      ],
      selected: {s.language},
      onSelectionChanged: (v) => s.setLanguage(v.first),
    );
  }
}

/// Small app-bar button that flips between English and Swahili.
class LanguageButton extends StatelessWidget {
  const LanguageButton({super.key, this.light = false});

  final bool light;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    final fg = light ? Colors.white : Brand.teal700;
    return Material(
      color: light ? Colors.white.withValues(alpha: .12) : Colors.white,
      shape: StadiumBorder(side: BorderSide(color: light ? Colors.white.withValues(alpha: .2) : Brand.line)),
      child: InkWell(
        customBorder: const StadiumBorder(),
        onTap: s.toggleLanguage,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.translate_rounded, size: 16, color: fg),
              const SizedBox(width: 5),
              Text(s.language == 'sw' ? 'SW' : 'EN', style: TextStyle(color: fg, fontWeight: FontWeight.w800, fontSize: 12.5)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Small rounded label with a coloured dot (statuses, stock levels).
class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, required this.color});
  final String text;
  final MaterialColor color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
    decoration: BoxDecoration(color: color.shade50, borderRadius: BorderRadius.circular(999)),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 6, height: 6, decoration: BoxDecoration(color: color.shade500, shape: BoxShape.circle)),
        const SizedBox(width: 5),
        Text(text, style: TextStyle(color: color.shade800, fontSize: 11.5, fontWeight: FontWeight.w700)),
      ],
    ),
  );
}

/// Rounded square with a tinted background holding an icon.
class IconBubble extends StatelessWidget {
  const IconBubble({super.key, required this.icon, required this.color, this.size = 42});
  final IconData icon;
  final MaterialColor color;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(color: color.shade50, borderRadius: BorderRadius.circular(size * .32)),
    child: Icon(icon, color: color.shade700, size: size * .5),
  );
}

/// Initials tile used for products in lists and the selling grid.
class ProductAvatar extends StatelessWidget {
  const ProductAvatar({super.key, required this.name, this.size = 42});
  final String name;
  final double size;

  @override
  Widget build(BuildContext context) {
    final letters = name.split(' ').where((w) => w.isNotEmpty && RegExp('[A-Za-z]').hasMatch(w[0])).take(2).map((w) => w[0].toUpperCase()).join();
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Brand.teal50,
        borderRadius: BorderRadius.circular(size * .3),
      ),
      child: Text(letters, style: TextStyle(color: Brand.teal700, fontWeight: FontWeight.w800, fontSize: size * .34)),
    );
  }
}

/// White card with a label, a big number and an icon bubble.
class StatCard extends StatelessWidget {
  const StatCard({super.key, required this.icon, required this.tone, required this.label, required this.value, this.sub});
  final IconData icon;
  final MaterialColor tone;
  final String label;
  final String value;
  final String? sub;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBubble(icon: icon, color: tone, size: 40),
          const SizedBox(height: 12),
          Text(label, style: const TextStyle(color: Brand.muted, fontSize: 12.5, fontWeight: FontWeight.w600)),
          const SizedBox(height: 2),
          FittedBox(child: Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
          if (sub != null) Text(sub!, style: const TextStyle(color: Brand.muted, fontSize: 11.5)),
        ],
      ),
    ),
  );
}

/// Section heading with an optional action link.
class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.action, this.onAction});
  final String text;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(child: Text(text, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800))),
      if (action != null) TextButton(onPressed: onAction, child: Text(action!)),
    ],
  );
}

/// Header with a real photo darkened by a plain shade so white text stays readable.
/// Falls back to a solid dark colour if the photo is missing.
class PhotoHeader extends StatelessWidget {
  const PhotoHeader({super.key, required this.image, required this.child, this.radius = 30, this.shade = .62, this.creditOnTop = false});
  final String image;
  final Widget child;
  final double radius;
  final double shade;
  final bool creditOnTop;

  @override
  Widget build(BuildContext context) {
    final credit = Photos.credits[image];
    return ClipRRect(
      borderRadius: BorderRadius.vertical(bottom: Radius.circular(radius)),
      child: Stack(
        children: [
          Positioned.fill(child: Container(color: Brand.night)),
          Positioned.fill(
            child: Image.asset(image, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
          ),
          Positioned.fill(child: Container(color: Colors.black.withValues(alpha: shade))),
          SizedBox(width: double.infinity, child: child),
          if (credit != null)
            Positioned(
              right: creditOnTop ? null : 14,
              left: creditOnTop ? 16 : null,
              bottom: creditOnTop ? null : 6,
              top: creditOnTop ? MediaQuery.of(context).padding.top + 4 : null,
              child: Text(credit, style: TextStyle(color: Colors.white.withValues(alpha: .45), fontSize: 9)),
            ),
        ],
      ),
    );
  }
}

/// Rounded photo card (used as a page banner).
class PhotoCard extends StatelessWidget {
  const PhotoCard({super.key, required this.image, required this.child, this.height = 150});
  final String image;
  final Widget child;
  final double height;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(22),
    child: SizedBox(
      height: height,
      child: Stack(
        children: [
          Positioned.fill(child: Container(color: Brand.night)),
          Positioned.fill(child: Image.asset(image, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink())),
          Positioned.fill(child: Container(color: Colors.black.withValues(alpha: .55))),
          Positioned(left: 18, right: 18, bottom: 18, child: child),
          if (Photos.credits[image] != null)
            Positioned(right: 12, top: 8, child: Text(Photos.credits[image]!, style: TextStyle(color: Colors.white.withValues(alpha: .45), fontSize: 9))),
        ],
      ),
    ),
  );
}
