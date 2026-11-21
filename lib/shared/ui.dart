import 'package:flutter/material.dart';
import 'ph.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:intl/intl.dart';

import '../core/api.dart';
import '../core/theme.dart';
import 'motion.dart';

final _kes = NumberFormat('#,##0', 'en_US');

/// "13450.00" -> "KES 13,450"
String kes(dynamic v, {bool symbol = true}) {
  final n = double.tryParse('$v') ?? 0;
  final s = n == n.roundToDouble() ? _kes.format(n) : NumberFormat('#,##0.00', 'en_US').format(n);
  return symbol ? 'KES $s' : s;
}

String shortDate(String? iso) {
  if (iso == null) return '';
  final d = DateTime.tryParse(iso)?.toLocal();
  return d == null ? iso : DateFormat('d MMM').format(d);
}

String dateTime(String? iso) {
  if (iso == null) return '';
  final d = DateTime.tryParse(iso)?.toLocal();
  return d == null ? iso : DateFormat('d MMM, HH:mm').format(d);
}

String initials(String? name) {
  final parts = (name ?? '').trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
  if (parts.isEmpty) return '?';
  return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
}

/// Photo URLs from the server, minus anything that is not a real picture address (older servers sent
/// ".../storage/[]" for a repair without photos, which 404'd and showed up in App health).
List<String> photoUrls(dynamic photos) => (photos is List ? photos : const [])
    .whereType<String>()
    .map((p) => p.trim())
    .where((p) => p.startsWith('http') && !p.endsWith('/storage/') && !p.contains('%5B') && !p.endsWith('[]') && !p.endsWith('/null'))
    .toList();

/// A small network photo that shows a placeholder instead of throwing when the file is gone.
class NetPhoto extends StatelessWidget {
  const NetPhoto(this.url, {super.key, this.size = 56});
  final String url;
  final double size;
  @override
  Widget build(BuildContext context) => Image.network(url, width: size, height: size, cacheWidth: (size * 3).round(), fit: BoxFit.cover,
      errorBuilder: (context, error, stack) => Container(
            width: size,
            height: size,
            color: Sc.blush,
            alignment: Alignment.center,
            child: const Icon(PhosphorIconsLight.image, color: Sc.muted, size: 20),
          ));
}

void toast(BuildContext context, Object error) {
  final msg = error is ApiException ? error.message : '$error';
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
}

/// scharge's logo, or the white label's logo from the server.
class BrandLogo extends StatelessWidget {
  const BrandLogo({super.key, this.onRed = true, this.width = 150, this.logoUrl});
  final bool onRed;
  final double width;
  final String? logoUrl;
  @override
  Widget build(BuildContext context) {
    if (logoUrl != null) {
      return Image.network(logoUrl!, width: width, errorBuilder: (_, _, _) => const SizedBox.shrink());
    }
    return SvgPicture.asset(onRed ? 'assets/images/scharge-logo-white.svg' : 'assets/images/scharge-logo-red.svg',
        width: width, semanticsLabel: 'scharge');
  }
}

/// The section label above a list, as on the canvas ("Building notices").
class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing, this.icon});
  final String text;
  final Widget? trailing;
  final IconData? icon;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(2, 16, 2, 6),
        child: Row(children: [
          if (icon != null) ...[Icon(icon, size: 18, color: Sc.brand), const SizedBox(width: 8)],
          Expanded(child: Text(text, style: Sc.body(14, weight: 700))),
          ?trailing,
        ]),
      );
}

/// White card with the soft red border used across the app canvas.
class Tile extends StatelessWidget {
  const Tile({super.key, required this.child, this.onTap, this.filled = false, this.padding = const EdgeInsets.all(12)});
  final Widget child;
  final VoidCallback? onTap;
  final bool filled;
  final EdgeInsets padding;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Material(
          color: filled ? Sc.blush : Colors.white,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12), side: filled ? BorderSide.none : const BorderSide(color: Sc.blush2)),
          child: InkWell(
              borderRadius: BorderRadius.circular(12), onTap: onTap, child: Padding(padding: padding, child: child)),
        ),
      );
}

class Avatar extends StatelessWidget {
  const Avatar(this.name, {super.key, this.size = 44, this.light = true});
  final String? name;
  final double size;
  final bool light;
  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(shape: BoxShape.circle, color: light ? Sc.blush : Colors.white),
        child: Text(initials(name), style: Sc.body(size * .3, weight: 600)),
      );
}

/// Small status chip. Alerts are always words plus colour, never colour alone.
class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.tone = 'neutral'});
  final String text;
  final String tone; // neutral | ok | alert | brand
  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (tone) {
      'ok' => (const Color(0xFFDCFCE7), Sc.ok),
      'alert' => (Sc.blush2, Sc.alert),
      'brand' => (Sc.brand, Colors.white),
      _ => (Sc.blush, Sc.muted),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(text, style: Sc.body(12, weight: 600, color: fg)),
    );
  }
}

/// A notice row with the coloured bar (canvas: "Building notices").
class Notice extends StatelessWidget {
  const Notice(this.title, this.text, {super.key, this.alert = false});
  final String title;
  final String text;
  final bool alert;
  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(border: Border.all(color: Sc.blush2), borderRadius: BorderRadius.circular(12)),
        child: IntrinsicHeight(
          child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(width: 8, decoration: BoxDecoration(color: alert ? Sc.alert : Sc.brand, borderRadius: BorderRadius.circular(4))),
            const SizedBox(width: 12),
            Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: Sc.body(14, weight: 600)),
              const SizedBox(height: 3),
              Text(text, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(9.5, color: Sc.muted)),
            ])),
          ]),
        ),
      );
}

class EmptyState extends StatelessWidget {
  const EmptyState(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 24),
        child: Text(text, textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
      );
}

/// Loads data from the server, shows a spinner, an error with Retry, and pull-to-refresh.
class Loader<T> extends StatefulWidget {
  const Loader({super.key, required this.load, required this.builder});
  final Future<T> Function() load;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;
  @override
  State<Loader<T>> createState() => _LoaderState<T>();
}

class _LoaderState<T> extends State<Loader<T>> {
  late Future<T> _future = widget.load();

  T? _last;

  Future<void> _reload() async {
    final f = Api.fresh(widget.load); // pull-to-refresh and "Try again" always ask the server
    setState(() => _future = f);
    try {
      await f;
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<T>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            // Refreshing: keep what is on screen instead of flashing a spinner.
            if (_last != null) return RefreshIndicator(onRefresh: _reload, child: widget.builder(context, _last as T, _reload));
            return const Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator(strokeWidth: 2.5)));
          }
          if (snap.hasError) {
            final e = snap.error;
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(e is ApiException ? e.message : 'Something went wrong.', textAlign: TextAlign.center, style: Sc.body(15)),
                  const SizedBox(height: 16),
                  OutlinedButton(onPressed: _reload, child: const Text('Try again')),
                ]),
              ),
            );
          }
          _last = snap.data;
          return RefreshIndicator(onRefresh: _reload, child: widget.builder(context, snap.data as T, _reload));
        },
      );
}

/// A big red summary card (canvas: tenant "Pay now" card, manager "COLLECTED" card).
class HeroCard extends StatelessWidget {
  const HeroCard({super.key, required this.children, this.accent = false});
  final List<Widget> children;

  /// Staff screens: the role's secondary colour (tenants keep red).
  final bool accent;
  @override
  Widget build(BuildContext context) => Appear(child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: accent ? Sc.accent : Sc.brand, borderRadius: BorderRadius.circular(16)),
        child: DefaultTextStyle(
            style: Sc.body(13, color: accent ? Sc.onAccent : Colors.white),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children)),
      ));
}

/// Number pad for PINs (canvas: "Create your PIN").
class Keypad extends StatelessWidget {
  const Keypad({super.key, required this.onDigit, required this.onDelete, this.light = true});
  final void Function(String) onDigit;
  final VoidCallback onDelete;
  final bool light;
  @override
  Widget build(BuildContext context) {
    final keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'del'];
    final bg = light ? Sc.blush : Colors.white.withValues(alpha: .14);
    final fg = light ? Sc.ink : Colors.white;
    Widget key(String k) => Expanded(
          child: Padding(
            padding: const EdgeInsets.all(5),
            child: k.isEmpty
                ? const SizedBox(height: 52)
                : Material(
                    color: bg,
                    borderRadius: BorderRadius.circular(16),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(16),
                      onTap: () => k == 'del' ? onDelete() : onDigit(k),
                      child: SizedBox(
                        height: 52,
                        child: Center(
                            child: k == 'del'
                                ? Icon(PhosphorIconsLight.backspace, color: fg, semanticLabel: 'Delete')
                                : Text(k, style: Sc.body(22, weight: 600, color: fg))),
                      ),
                    ),
                  ),
          ),
        );
    return Column(mainAxisSize: MainAxisSize.min, children: [
      for (var r = 0; r < 4; r++) Row(children: [for (var c = 0; c < 3; c++) key(keys[r * 3 + c])]),
    ]);
  }
}

class PinDots extends StatelessWidget {
  const PinDots(this.filled, {super.key, this.light = true, this.length = 4});
  final int filled;
  final bool light;
  final int length;
  @override
  Widget build(BuildContext context) => Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          for (var i = 0; i < length; i++)
            AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              curve: Curves.easeOutBack,
              margin: EdgeInsets.symmetric(horizontal: length > 4 ? 6 : 9),
              width: i < filled ? 20 : 18,
              height: i < filled ? 20 : 18,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: i < filled ? (light ? Sc.brand : Colors.white) : Colors.transparent,
                border: Border.all(color: light ? const Color(0xFFFCA5A5) : Colors.white70, width: 2),
              ),
            ),
        ],
      );
}

/// Filter pills (canvas: repair categories): on = red with white text, off = blush with ink text, always readable.
class PillTabs<T> extends StatelessWidget {
  const PillTabs({super.key, required this.options, required this.selected, required this.onSelect, this.multi = const {}});
  final List<(T, String)> options;
  final T? selected;
  final void Function(T) onSelect;

  /// Extra on/off pills (e.g. "Mine"), shown after the options.
  final Map<String, (bool, VoidCallback)> multi;

  Widget _pill(String label, bool on, VoidCallback tap) => Material(
        color: on ? Sc.brand : Sc.blush,
        borderRadius: BorderRadius.circular(999),
        child: InkWell(
          borderRadius: BorderRadius.circular(999),
          onTap: tap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: Text(label, maxLines: 1, style: Sc.body(12, weight: on ? 600 : 400, color: on ? Colors.white : Sc.ink)),
          ),
        ),
      );

  /// One line, like the repair categories; swipes sideways when it does not fit.
  @override
  Widget build(BuildContext context) => SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        clipBehavior: Clip.none,
        child: Row(children: [
          for (final o in options) Padding(padding: const EdgeInsets.only(right: 6), child: _pill(o.$2, o.$1 == selected, () => onSelect(o.$1))),
          for (final m in multi.entries) Padding(padding: const EdgeInsets.only(right: 6), child: _pill(m.key, m.value.$1, m.value.$2)),
        ]),
      );
}

/// A full empty page: icon, what this is for, and the next step.
class EmptyPanel extends StatelessWidget {
  const EmptyPanel({super.key, required this.icon, required this.title, required this.text, this.action, this.onAction});
  final IconData icon;
  final String title;
  final String text;
  final String? action;
  final VoidCallback? onAction;
  @override
  Widget build(BuildContext context) => Appear(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 12),
          child: Column(children: [
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(color: Sc.blush, shape: BoxShape.circle),
              child: Icon(icon, size: 32, color: Sc.brand),
            ),
            const SizedBox(height: 18),
            Text(title, textAlign: TextAlign.center, style: Sc.title(17)),
            const SizedBox(height: 8),
            Text(text, textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis, style: Sc.body(10.5, color: Sc.muted)),
            if (action != null && onAction != null) ...[
              const SizedBox(height: 20),
              FilledButton.icon(onPressed: onAction, icon: const Icon(PhosphorIconsLight.plus), label: Text(action!)),
            ],
          ]),
        ),
      );
}
