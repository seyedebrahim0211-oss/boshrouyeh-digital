import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/updater.dart';

import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import '../widgets/k_icon.dart';
import 'home_screen.dart';
import 'profile_screen.dart';
import 'report_screen.dart';
import 'services_screen.dart';
import 'tourism_screen.dart';

/// Bottom navigation with the 8-point-star 137 button in the centre (from the brand book).
class Shell extends StatefulWidget {
  const Shell({super.key});
  @override
  State<Shell> createState() => ShellState();
}

class ShellState extends State<Shell> with WidgetsBindingObserver {
  int index = 0;
  void go(int i) => setState(() => index = i);

  DateTime? _lastUpdateCheck;
  Timer? _updateTimer;
  bool _checking = false;
  bool _foreground = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Start the native Android periodic notifier, then check the website now.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(Updater.syncBackgroundChecks());
      unawaited(_checkForUpdates());
    });
    // Also detect a newly uploaded APK while the app remains in the foreground.
    _updateTimer = Timer.periodic(const Duration(minutes: 5), (_) => _checkForUpdates());
  }

  Future<void> _checkForUpdates() async {
    if (!mounted || !_foreground || _checking) return;
    final now = DateTime.now();
    if (_lastUpdateCheck != null &&
        now.difference(_lastUpdateCheck!) < const Duration(minutes: 4)) {
      return;
    }
    _lastUpdateCheck = now;
    _checking = true;
    try {
      await Updater.checkOnStart(context, context.read<Api>());
    } finally {
      _checking = false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (_foreground) _checkForUpdates();
  }

  @override
  void dispose() {
    _updateTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      HomeScreen(onNavigate: go),
      const ServicesScreen(),
      const ReportScreen(),
      const TourismScreen(),
      const ProfileScreen(),
    ];
    return Scaffold(
      body: IndexedStack(index: index, children: pages),
      bottomNavigationBar: _BottomBar(index: index, onTap: go),
    );
  }
}

class _BottomBar extends StatelessWidget {
  const _BottomBar({required this.index, required this.onTap});
  final int index;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final items = [('home', 'خانه'), ('grid', 'خدمات'), ('137', '۱۳۷'), ('badgir', 'گردشگری'), ('user', 'من')];
    return Container(
      decoration: BoxDecoration(
        color: dark ? Brand.nightSurface : Colors.white,
        boxShadow: const [BoxShadow(color: Color(0x1A1F2A44), blurRadius: 16, offset: Offset(0, -4))],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 72,
          child: Row(
            children: List.generate(items.length, (i) {
              final selected = i == index;
              if (i == 2) {
                return Expanded(
                  child: Semantics(
                    button: true,
                    selected: selected,
                    label: 'گزارش به سامانه ۱۳۷',
                    child: GestureDetector(
                      onTap: () => onTap(2),
                      child: Transform.translate(
                        offset: const Offset(0, -18),
                        child: Column(mainAxisSize: MainAxisSize.min, children: [
                          CustomPaint(
                            painter: _StarPainter(),
                            child: const SizedBox(width: 64, height: 64, child: Center(child: KIcon('137', size: 26, color: Colors.white))),
                          ),
                          Text(items[i].$2, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: dark ? Brand.kahgel : Brand.qanat)),
                        ]),
                      ),
                    ),
                  ),
                );
              }
              final color = selected ? Brand.qanat : (dark ? const Color(0xFFBFAF9A) : Brand.muted);
              return Expanded(
                child: InkResponse(
                  onTap: () => onTap(i),
                  child: Semantics(
                    selected: selected,
                    button: true,
                    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      KIcon(items[i].$1, size: 24, color: color),
                      const SizedBox(height: 4),
                      Text(items[i].$2, style: TextStyle(fontSize: 11, fontWeight: selected ? FontWeight.w800 : FontWeight.w500, color: color)),
                    ]),
                  ),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }
}

class _StarPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final c = size.center(Offset.zero);
    final path = GirihPainter.star8(c, size.width / 2);
    canvas.drawShadow(path, const Color(0x6616737A), 6, false);
    canvas.drawPath(path, Paint()..color = Brand.qanat);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
