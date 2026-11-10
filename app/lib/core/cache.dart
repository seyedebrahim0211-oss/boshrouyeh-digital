import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Tiny JSON cache so the app works offline / on weak village connections.
class JsonCache {
  static Future<void> put(String key, Object? value) async {
    final p = await SharedPreferences.getInstance();
    await p.setString('c:$key', jsonEncode({'t': DateTime.now().millisecondsSinceEpoch, 'v': value}));
  }

  static Future<Object?> get(String key) async {
    final p = await SharedPreferences.getInstance();
    final raw = p.getString('c:$key');
    if (raw == null) return null;
    try {
      return (jsonDecode(raw) as Map<String, dynamic>)['v'];
    } catch (_) {
      return null;
    }
  }
}
