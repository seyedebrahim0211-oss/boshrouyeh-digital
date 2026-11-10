import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'shell.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..forward();

  @override
  void initState() {
    super.initState();
    Future.delayed(const Duration(milliseconds: 1900), () {
      if (!mounted) return;
      Navigator.of(context).pushReplacement(PageRouteBuilder<void>(
        pageBuilder: (_, __, ___) => const Shell(),
        transitionsBuilder: (_, a, __, child) => FadeTransition(opacity: a, child: child),
        transitionDuration: const Duration(milliseconds: 500),
      ));
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final fade = CurvedAnimation(parent: _c, curve: Curves.easeOutCubic);
    return Scaffold(
      backgroundColor: Brand.night,
      body: CustomPaint(
        painter: GirihPainter(color: const Color(0x14FFFFFF)),
        child: SafeArea(
          child: Column(
            children: [
              const Spacer(flex: 3),
              FadeTransition(
                opacity: fade,
                child: ScaleTransition(
                  scale: Tween(begin: .85, end: 1.0).animate(fade),
                  child: SvgPicture.asset('assets/images/logo-milk.svg', width: 150, height: 150, colorFilter: const ColorFilter.mode(Brand.kahgel, BlendMode.srcIn), semanticsLabel: 'نشان شهرداری بشرویه'),
                ),
              ),
              const SizedBox(height: 28),
              FadeTransition(
                opacity: fade,
                child: Column(children: const [
                  Text('شهرداری بشرویه', style: TextStyle(fontFamily: 'Lalezar', fontSize: 34, color: Brand.milk)),
                  SizedBox(height: 6),
                  Text('شهرِ خشت، باد و قنات', style: TextStyle(fontFamily: 'Amiri', fontSize: 22, color: Brand.sun)),
                ]),
              ),
              const Spacer(flex: 2),
              const Skyline(height: 150),
            ],
          ),
        ),
      ),
    );
  }
}
