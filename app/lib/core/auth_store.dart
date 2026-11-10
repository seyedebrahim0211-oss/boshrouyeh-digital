import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Holds tokens (secure storage) and the signed-in profile.
class AuthStore extends ChangeNotifier {
  AuthStore({FlutterSecureStorage? storage}) : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;
  String? accessToken;
  String? refreshToken;
  DateTime? accessExpiry;
  Map<String, dynamic>? user;

  bool get isLoggedIn => refreshToken != null;
  String get displayName {
    final n = (user?['name'] as String?) ?? '';
    return n.isEmpty || n == 'شهروند' ? 'شهروند گرامی' : n;
  }

  Future<void> load() async {
    try {
      accessToken = await _storage.read(key: 'access');
      refreshToken = await _storage.read(key: 'refresh');
      final exp = await _storage.read(key: 'access_exp');
      accessExpiry = exp == null ? null : DateTime.tryParse(exp);
      final u = await _storage.read(key: 'user');
      user = u == null ? null : jsonDecode(u) as Map<String, dynamic>;
    } catch (_) {
      await clear();
    }
    notifyListeners();
  }

  Future<void> saveTokens(Map<String, dynamic> data) async {
    accessToken = data['access_token'] as String?;
    refreshToken = data['refresh_token'] as String? ?? refreshToken;
    final seconds = (data['expires_in'] as num?)?.toInt() ?? 3600;
    accessExpiry = DateTime.now().add(Duration(seconds: seconds - 60));
    if (data['user'] is Map) {
      user = Map<String, dynamic>.from(data['user'] as Map);
    }
    await _storage.write(key: 'access', value: accessToken);
    await _storage.write(key: 'refresh', value: refreshToken);
    await _storage.write(key: 'access_exp', value: accessExpiry!.toIso8601String());
    if (user != null) await _storage.write(key: 'user', value: jsonEncode(user));
    notifyListeners();
  }

  Future<void> setUser(Map<String, dynamic> u) async {
    user = u;
    await _storage.write(key: 'user', value: jsonEncode(u));
    notifyListeners();
  }

  bool get accessValid => accessToken != null && accessExpiry != null && DateTime.now().isBefore(accessExpiry!);

  Future<void> clear() async {
    accessToken = null;
    refreshToken = null;
    accessExpiry = null;
    user = null;
    await _storage.deleteAll();
    notifyListeners();
  }
}
