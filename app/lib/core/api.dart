import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'auth_store.dart';
import 'cache.dart';
import 'config.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.status = 0, this.data});
  final String message;
  final int status;
  final Map<String, dynamic>? data;
  @override
  String toString() => message;
}

/// REST client for /wp-json/bmn/v1 with Bearer auth + transparent token refresh.
class Api {
  Api(this.auth, {http.Client? client}) : _client = client ?? http.Client();

  final AuthStore auth;
  final http.Client _client;
  static const _timeout = Duration(seconds: 20);
  Completer<bool>? _refreshing;

  Uri uri(String path, [Map<String, String>? query]) {
    final u = Uri.parse(AppConfig.apiBase + path);
    return query == null ? u : u.replace(queryParameters: {...u.queryParameters, ...query});
  }

  Future<Map<String, String>> _headers({bool authed = false, bool json = true}) async {
    final h = <String, String>{'Accept': 'application/json'};
    if (json) h['Content-Type'] = 'application/json';
    if (authed && auth.isLoggedIn) {
      if (!auth.accessValid) await _refresh();
      if (auth.accessToken != null) h['Authorization'] = 'Bearer ${auth.accessToken}';
    }
    return h;
  }

  dynamic _decode(http.Response r) {
    dynamic body;
    try {
      body = r.body.isEmpty ? null : jsonDecode(utf8.decode(r.bodyBytes));
    } catch (_) {
      body = null;
    }
    if (r.statusCode >= 200 && r.statusCode < 300) return body;
    final map = body is Map<String, dynamic> ? body : null;
    final code = map?['code'] as String?;
    if (code == 'rest_no_route' || (body == null && r.statusCode == 404)) {
      throw ApiException('این سرویس روی سرور در دسترس نیست؛ لطفاً اپ را بروزرسانی کنید یا بعداً تلاش کنید.', status: r.statusCode, data: map);
    }
    throw ApiException((map?['message'] as String?) ?? 'خطای ارتباط (${r.statusCode})', status: r.statusCode, data: map);
  }

  Future<dynamic> _send(Future<http.Response> Function(Map<String, String> h) call, {bool authed = false, bool json = true}) async {
    try {
      var r = await call(await _headers(authed: authed, json: json)).timeout(_timeout);
      if (r.statusCode == 401 && authed && auth.isLoggedIn) {
        if (await _refresh(force: true)) {
          r = await call(await _headers(authed: authed, json: json)).timeout(_timeout);
        }
      }
      return _decode(r);
    } on SocketException {
      throw ApiException('اتصال اینترنت برقرار نیست.');
    } on TimeoutException {
      throw ApiException('پاسخ سرور طول کشید؛ دوباره تلاش کنید.');
    } on http.ClientException {
      throw ApiException('اتصال به سرور ممکن نشد.');
    }
  }

  Future<dynamic> get(String path, {Map<String, String>? query, bool authed = false}) =>
      _send((h) => _client.get(uri(path, query), headers: h), authed: authed);

  Future<dynamic> post(String path, Map<String, dynamic> body, {bool authed = false}) =>
      _send((h) => _client.post(uri(path), headers: h, body: jsonEncode(body)), authed: authed);

  /// GET with offline fallback to the last good response.
  Future<dynamic> getCached(String path, {Map<String, String>? query, bool authed = false}) async {
    final key = '$path?${query ?? ''}';
    try {
      final d = await get(path, query: query, authed: authed);
      unawaited(JsonCache.put(key, d));
      return d;
    } on ApiException catch (e) {
      if (e.status == 0) {
        final c = await JsonCache.get(key);
        if (c != null) return c;
      }
      rethrow;
    }
  }

  Future<dynamic> multipart(String path, Map<String, String> fields, {File? file, String fileField = 'photo'}) async {
    return _send((h) async {
      final req = http.MultipartRequest('POST', uri(path))
        ..headers.addAll(h)
        ..fields.addAll(fields);
      if (file != null) {
        req.files.add(await http.MultipartFile.fromPath(fileField, file.path));
      }
      return http.Response.fromStream(await _client.send(req));
    }, authed: true, json: false);
  }

  Future<bool> _refresh({bool force = false}) async {
    if (!force && auth.accessValid) return true;
    if (auth.refreshToken == null) return false;
    if (_refreshing != null) return _refreshing!.future;
    final c = _refreshing = Completer<bool>();
    try {
      final r = await _client
          .post(uri('auth/refresh'), headers: {'Content-Type': 'application/json'}, body: jsonEncode({'refresh': auth.refreshToken}))
          .timeout(_timeout);
      if (r.statusCode == 200) {
        await auth.saveTokens(jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>);
        c.complete(true);
      } else {
        if (r.statusCode == 401) await auth.clear();
        c.complete(false);
      }
    } catch (_) {
      c.complete(false);
    } finally {
      _refreshing = null;
    }
    return c.future;
  }

  /* ---------- Auth ---------- */
  Future<Map<String, dynamic>> requestOtp(String phone) async =>
      Map<String, dynamic>.from(await post('auth/otp', {'phone': phone}) as Map);

  Future<void> verifyOtp(String phone, String code) async {
    final d = await post('auth/verify', {'phone': phone, 'code': code, 'mode': 'token', 'device': Platform.operatingSystem});
    await auth.saveTokens(Map<String, dynamic>.from(d as Map));
  }

  Future<void> logout() async {
    try {
      await post('auth/logout', {'refresh': auth.refreshToken ?? ''});
    } catch (_) {}
    await auth.clear();
  }
}
