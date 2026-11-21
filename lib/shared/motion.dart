import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Motion for the app: short, calm, and off when the phone asks for less animation.
bool reduceMotion(BuildContext context) => MediaQuery.maybeOf(context)?.disableAnimations ?? false;

/// Fades and slides a child up when it first appears. [order] staggers lists (capped so long lists stay quick).
class Appear extends StatelessWidget {
  const Appear({super.key, required this.child, this.order = 0, this.offset = 8});
  final Widget child;
  final int order;
  final double offset;

  @override
  Widget build(BuildContext context) {
    if (reduceMotion(context)) return child;
    final delay = math.min(order, 6) * 0.04;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 260 + (delay * 1000).round()),
      curve: Interval(delay / (0.26 + delay), 1, curve: Curves.easeOutCubic),
      builder: (context, t, child) => Opacity(opacity: t, child: Transform.translate(offset: Offset(0, (1 - t) * offset), child: child)),
      child: child,
    );
  }
}

/// A KES amount that counts up to its value.
class CountUp extends StatelessWidget {
  const CountUp({super.key, required this.value, required this.format, required this.style});
  final double value;
  final String Function(double) format;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    if (reduceMotion(context)) return Text(format(value), style: style);
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: value),
      duration: const Duration(milliseconds: 600),
      curve: Curves.easeOutCubic,
      builder: (context, v, _) => Text(format(v.roundToDouble() == value.roundToDouble() ? value : v.roundToDouble()), style: style),
    );
  }
}

/// Shrinks slightly while pressed (cards, tiles).
class Pressable extends StatefulWidget {
  const Pressable({super.key, required this.child, this.enabled = true});
  final Widget child;
  final bool enabled;
  @override
  State<Pressable> createState() => _PressableState();
}

class _PressableState extends State<Pressable> {
  bool _down = false;
  @override
  Widget build(BuildContext context) => Listener(
        onPointerDown: widget.enabled ? (_) => setState(() => _down = true) : null,
        onPointerUp: (_) => setState(() => _down = false),
        onPointerCancel: (_) => setState(() => _down = false),
        child: AnimatedScale(
          scale: _down && !reduceMotion(context) ? 0.985 : 1,
          duration: const Duration(milliseconds: 120),
          curve: Curves.easeOut,
          child: widget.child,
        ),
      );
}

/// A soft repeating pulse (the fingerprint button).
class Pulse extends StatefulWidget {
  const Pulse({super.key, required this.child});
  final Widget child;
  @override
  State<Pulse> createState() => _PulseState();
}

class _PulseState extends State<Pulse> with SingleTickerProviderStateMixin {
  late final _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1600))..repeat();
  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (reduceMotion(context)) return widget.child;
    return AnimatedBuilder(
      animation: _c,
      builder: (context, child) {
        final t = Curves.easeOut.transform(_c.value);
        return Stack(alignment: Alignment.center, children: [
          Transform.scale(
            scale: 1 + t * .35,
            child: Opacity(
              opacity: (1 - t) * .5,
              child: Container(width: 104, height: 104, decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2))),
            ),
          ),
          child!,
        ]);
      },
      child: widget.child,
    );
  }
}

/// Shakes its child sideways when [trigger] changes (a wrong PIN).
class Shake extends StatelessWidget {
  const Shake({super.key, required this.trigger, required this.child});
  final int trigger;
  final Widget child;
  @override
  Widget build(BuildContext context) {
    if (trigger == 0 || reduceMotion(context)) return child;
    return TweenAnimationBuilder<double>(
      key: ValueKey(trigger),
      tween: Tween(begin: 0, end: 1),
      duration: const Duration(milliseconds: 420),
      builder: (context, t, child) => Transform.translate(offset: Offset(math.sin(t * math.pi * 5) * 10 * (1 - t), 0), child: child),
      child: child,
    );
  }
}
