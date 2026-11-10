import 'package:flutter/material.dart';

/// Khesht palette (from the brand book).
class Brand {
  static const milk = Color(0xFFF6EEE1);
  static const kahgel = Color(0xFFE2C9A3);
  static const khesht = Color(0xFFA0673F);
  static const brick = Color(0xFF6E3B23);
  static const qanat = Color(0xFF16737A);
  static const night = Color(0xFF1F2A44);
  static const sun = Color(0xFFD9A441);
  static const ink = Color(0xFF2A211C);
  static const muted = Color(0xFF6B5A4E);
  static const tint = Color(0xFFF3E6D2);
  static const line = Color(0xFFEADBC4);
  static const nightBg = Color(0xFF151C2E);
  static const nightSurface = Color(0xFF1F2A44);

  static const radiusCard = 14.0;
  static const taqche = BorderRadius.only(
    topLeft: Radius.circular(40),
    topRight: Radius.circular(40),
    bottomLeft: Radius.circular(10),
    bottomRight: Radius.circular(10),
  );

  static ThemeData theme(Brightness b) {
    final dark = b == Brightness.dark;
    final scheme = ColorScheme(
      brightness: b,
      primary: qanat,
      onPrimary: Colors.white,
      secondary: sun,
      onSecondary: night,
      error: const Color(0xFFB42318),
      onError: Colors.white,
      surface: dark ? nightSurface : Colors.white,
      onSurface: dark ? const Color(0xFFEFE5D6) : ink,
      tertiary: brick,
      onTertiary: Colors.white,
    );
    final base = ThemeData(useMaterial3: true, colorScheme: scheme, brightness: b, fontFamily: 'Vazirmatn');
    final text = base.textTheme.apply(
      fontFamily: 'Vazirmatn',
      bodyColor: scheme.onSurface,
      displayColor: dark ? kahgel : brick,
    );
    return base.copyWith(
      scaffoldBackgroundColor: dark ? nightBg : milk,
      textTheme: text.copyWith(
        headlineLarge: text.headlineLarge?.copyWith(fontFamily: 'Lalezar', fontWeight: FontWeight.w400, color: dark ? kahgel : brick),
        headlineMedium: text.headlineMedium?.copyWith(fontFamily: 'Lalezar', fontWeight: FontWeight.w400, color: dark ? kahgel : brick),
        headlineSmall: text.headlineSmall?.copyWith(fontFamily: 'Lalezar', fontWeight: FontWeight.w400, color: dark ? kahgel : brick),
        titleLarge: text.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: dark ? milk : night),
        titleMedium: text.titleMedium?.copyWith(fontWeight: FontWeight.w700, color: dark ? milk : night),
        bodySmall: text.bodySmall?.copyWith(color: dark ? const Color(0xFFBFAF9A) : muted),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: dark ? nightBg : milk,
        foregroundColor: dark ? milk : night,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(fontFamily: 'Lalezar', fontSize: 22, color: dark ? kahgel : brick),
      ),
      cardTheme: CardThemeData(
        color: dark ? nightSurface : Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusCard),
          side: BorderSide(color: dark ? const Color(0xFF33405F) : line),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: qanat,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(50),
          textStyle: const TextStyle(fontFamily: 'Vazirmatn', fontWeight: FontWeight.w700, fontSize: 15),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: dark ? kahgel : brick,
          side: BorderSide(color: dark ? kahgel : brick, width: 1.5),
          minimumSize: const Size.fromHeight(48),
          textStyle: const TextStyle(fontFamily: 'Vazirmatn', fontWeight: FontWeight.w700),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: dark ? nightSurface : Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: line)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: dark ? const Color(0xFF33405F) : line, width: 1.5),
        ),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: qanat, width: 2)),
      ),
      chipTheme: base.chipTheme.copyWith(
        backgroundColor: dark ? const Color(0xFF2A3756) : tint,
        selectedColor: qanat,
        labelStyle: TextStyle(fontFamily: 'Vazirmatn', fontWeight: FontWeight.w700, color: dark ? kahgel : brick),
        secondaryLabelStyle: const TextStyle(color: Colors.white, fontFamily: 'Vazirmatn', fontWeight: FontWeight.w700),
        shape: const StadiumBorder(),
        side: BorderSide.none,
      ),
      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: night,
        contentTextStyle: TextStyle(fontFamily: 'Vazirmatn', color: Colors.white),
      ),
      dividerTheme: DividerThemeData(color: dark ? const Color(0xFF33405F) : line, thickness: 1),
    );
  }
}
