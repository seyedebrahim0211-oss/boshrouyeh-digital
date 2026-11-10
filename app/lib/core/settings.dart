import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// User preferences (theme, neighborhood).
class AppSettings extends ChangeNotifier {
  ThemeMode themeMode = ThemeMode.system;
  String neighborhood = '';

  Future<void> load() async {
    final p = await SharedPreferences.getInstance();
    themeMode = ThemeMode.values[(p.getInt('theme') ?? 0).clamp(0, 2).toInt()];
    neighborhood = p.getString('hood') ?? '';
    notifyListeners();
  }

  Future<void> setTheme(ThemeMode m) async {
    themeMode = m;
    notifyListeners();
    (await SharedPreferences.getInstance()).setInt('theme', m.index);
  }

  Future<void> setNeighborhood(String slug) async {
    neighborhood = slug;
    notifyListeners();
    (await SharedPreferences.getInstance()).setString('hood', slug);
  }
}
