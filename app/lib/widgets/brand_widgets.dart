import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../theme/brand.dart';
import 'k_icon.dart';

/// Arched icon frame ("طاقچه") — the badgir slit shape.
class Taqche extends StatelessWidget {
  const Taqche({super.key, required this.icon, this.size = 52, this.active = false, this.color, this.iconColor});

  final String icon;
  final double size;
  final bool active;
  final Color? color;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final bg = color ?? (active ? Brand.qanat : (dark ? const Color(0xFF2A3756) : Brand.tint));
    final fg = iconColor ?? (active ? Colors.white : (dark ? Brand.kahgel : Brand.khesht));
    return Container(
      width: size,
      height: size * 1.1,
      decoration: BoxDecoration(color: bg, borderRadius: Brand.taqche),
      alignment: Alignment.center,
      child: KIcon(icon, size: size * 0.46, color: fg),
    );
  }
}

/// Faint 8-point-star (girih) pattern painter for dark panels.
class GirihPainter extends CustomPainter {
  GirihPainter({this.color = const Color(0x12FFFFFF), this.cell = 64});
  final Color color;
  final double cell;

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    for (double y = 0; y < size.height + cell; y += cell) {
      for (double x = 0; x < size.width + cell; x += cell) {
        canvas.drawPath(star8(Offset(x, y), cell * 0.33), p);
      }
    }
  }

  static Path star8(Offset c, double r) {
    final path = Path();
    for (var i = 0; i < 16; i++) {
      final rr = i.isEven ? r : r * 0.74;
      final a = -math.pi / 2 + i * math.pi / 8;
      final pt = Offset(c.dx + rr * math.cos(a), c.dy + rr * math.sin(a));
      i == 0 ? path.moveTo(pt.dx, pt.dy) : path.lineTo(pt.dx, pt.dy);
    }
    return path..close();
  }

  @override
  bool shouldRepaint(covariant GirihPainter old) => old.color != color || old.cell != cell;
}

/// Night-blue panel with girih pattern.
class NightPanel extends StatelessWidget {
  const NightPanel({super.key, required this.child, this.color = Brand.night, this.padding = const EdgeInsets.all(18), this.radius = 20});
  final Widget child;
  final Color color;
  final EdgeInsetsGeometry padding;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(radius)),
      clipBehavior: Clip.antiAlias,
      child: CustomPaint(
        painter: GirihPainter(),
        child: Padding(
          padding: padding,
          child: DefaultTextStyle.merge(style: const TextStyle(color: Brand.milk), child: child),
        ),
      ),
    );
  }
}

/// Skyline illustration strip (domes + badgirs).
class Skyline extends StatelessWidget {
  const Skyline({super.key, this.height = 120});
  final double height;
  @override
  Widget build(BuildContext context) =>
      SvgPicture.asset('assets/images/skyline.svg', height: height, width: double.infinity, fit: BoxFit.cover, alignment: Alignment.bottomCenter, excludeFromSemantics: true);
}

/// Section title with the sun-coloured taqche mark.
class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.action, this.onAction});
  final String text;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, top: 6),
      child: Row(
        children: [
          Container(width: 9, height: 13, decoration: const BoxDecoration(color: Brand.sun, borderRadius: BorderRadius.vertical(top: Radius.circular(6), bottom: Radius.circular(2)))),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 21))),
          if (action != null) TextButton(onPressed: onAction, child: Text(action!)),
        ],
      ),
    );
  }
}

/// Status chip.
class StatusChip extends StatelessWidget {
  const StatusChip(this.label, {super.key, this.kind = 'review'});
  final String label;
  final String kind;

  @override
  Widget build(BuildContext context) {
    const map = {
      'done': (Color(0xFFDDF0F1), Color(0xFF0E5C62)),
      'online': (Color(0xFFDDF0F1), Brand.qanat),
      'review': (Color(0xFFF1E5D3), Color(0xFF6E4B32)),
      'need': (Color(0xFFF9DEDC), Color(0xFF912018)),
      'fast': (Color(0xFFF6E9CC), Color(0xFF7A5413)),
      'closed': (Color(0xFFECE7E1), Color(0xFF5C5048)),
    };
    final c = map[kind] ?? map['review']!;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(color: c.$1, borderRadius: BorderRadius.circular(99)),
      child: Text(label, style: TextStyle(color: c.$2, fontWeight: FontWeight.w700, fontSize: 12)),
    );
  }
}

/// 4-step progress bar.
class StepProgress extends StatelessWidget {
  const StepProgress(this.step, {super.key, this.total = 4});
  final int step;
  final int total;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'مرحلهٔ $step از $total',
      child: Row(
        children: List.generate(
          total,
          (i) => Expanded(
            child: Container(
              height: 6,
              margin: EdgeInsetsDirectional.only(end: i == total - 1 ? 0 : 5),
              decoration: BoxDecoration(color: i < step ? Brand.qanat : Theme.of(context).dividerTheme.color, borderRadius: BorderRadius.circular(4)),
            ),
          ),
        ),
      ),
    );
  }
}

/// Loading / error / empty states.
class StateView extends StatelessWidget {
  const StateView.loading({super.key}) : message = null, onRetry = null, icon = null;
  const StateView.error(this.message, {super.key, this.onRetry}) : icon = 'info';
  const StateView.empty(this.message, {super.key, this.icon = 'star8'}) : onRetry = null;

  final String? message;
  final VoidCallback? onRetry;
  final String? icon;

  @override
  Widget build(BuildContext context) {
    if (message == null) {
      return const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator(color: Brand.qanat)));
    }
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Taqche(icon: icon ?? 'info', size: 56),
            const SizedBox(height: 12),
            Text(message!, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
            if (onRetry != null) ...[
              const SizedBox(height: 12),
              OutlinedButton(onPressed: onRetry, child: const Text('تلاش دوباره')),
            ],
          ],
        ),
      ),
    );
  }
}

void showMsg(BuildContext context, String msg) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
}
