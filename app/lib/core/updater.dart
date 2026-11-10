import 'dart:io';

import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'api.dart';
import 'format.dart';

/// Info returned by GET /bmn/v1/app/version.
class UpdateInfo {
  UpdateInfo({required this.current, required this.latest, required this.min, required this.url, required this.notes, required this.stores});
  final String current;
  final String latest;
  final String min;
  final String url;
  final String notes;
  final Map<String, String> stores;

  bool get hasUpdate => latest.isNotEmpty && compareVersions(latest, current) > 0;
  bool get forced => min.isNotEmpty && compareVersions(min, current) > 0;
  bool get canDownload => url.isNotEmpty || stores.isNotEmpty;
}

/// Semver-ish compare: returns <0, 0, >0. Non-numeric parts count as 0.
int compareVersions(String a, String b) {
  List<int> parts(String v) => v.replaceAll(RegExp(r'^[vV]'), '').split(RegExp(r'[.+-]')).map((p) => int.tryParse(p) ?? 0).toList();
  final x = parts(a), y = parts(b);
  for (var i = 0; i < 4; i++) {
    final d = (i < x.length ? x[i] : 0) - (i < y.length ? y[i] : 0);
    if (d != 0) return d;
  }
  return 0;
}

class Updater {
  static const _snoozeKey = 'update_snooze';

  static Future<String> currentVersion() async => (await PackageInfo.fromPlatform()).version;

  static Future<UpdateInfo?> fetch(Api api) async {
    try {
      final d = Map<String, dynamic>.from(await api.get('app/version', query: {'platform': Platform.isIOS ? 'ios' : 'android'}) as Map);
      final stores = <String, String>{};
      (d['stores'] as Map? ?? const {}).forEach((k, v) => stores['$k'] = '$v');
      return UpdateInfo(
        current: await currentVersion(),
        latest: '${d['latest'] ?? ''}',
        min: '${d['min'] ?? ''}',
        url: '${d['url'] ?? ''}',
        notes: '${d['notes'] ?? ''}',
        stores: stores,
      );
    } catch (_) {
      return null;
    }
  }

  /// Called once on start: shows a dialog when a newer build exists (max once a day unless forced).
  static Future<void> checkOnStart(BuildContext context, Api api) async {
    final info = await fetch(api);
    if (info == null || !info.hasUpdate || !info.canDownload || !context.mounted) return;
    final prefs = await SharedPreferences.getInstance();
    final snooze = prefs.getString(_snoozeKey) ?? '';
    final today = DateTime.now().toIso8601String().substring(0, 10);
    if (!info.forced && snooze == '${info.latest}@$today') return;
    if (!context.mounted) return;
    await showUpdateDialog(context, info);
    if (!info.forced) await prefs.setString(_snoozeKey, '${info.latest}@$today');
  }

  /// Manual check from settings.
  static Future<void> checkManually(BuildContext context, Api api) async {
    final info = await fetch(api);
    if (!context.mounted) return;
    if (info == null) {
      showMsg(context, 'بررسی نسخه ممکن نشد؛ اتصال را بررسی کنید.');
    } else if (!info.hasUpdate) {
      showMsg(context, 'نسخهٔ شما (${faDigits(info.current)}) به‌روز است.');
    } else {
      await showUpdateDialog(context, info);
    }
  }

  static Future<void> showUpdateDialog(BuildContext context, UpdateInfo info) {
    return showDialog<void>(
      context: context,
      barrierDismissible: !info.forced,
      builder: (c) => PopScope(
        canPop: !info.forced,
        child: AlertDialog(
          title: Text(info.forced ? 'بروزرسانی ضروری' : 'نسخهٔ جدید آماده است'),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('نسخهٔ ${faDigits(info.latest)} منتشر شده است (نسخهٔ فعلی: ${faDigits(info.current)}).'),
              if (info.forced) const Padding(padding: EdgeInsets.only(top: 8), child: Text('برای ادامهٔ استفاده لازم است اپ را بروزرسانی کنید.', style: TextStyle(color: Brand.brick, fontWeight: FontWeight.w700))),
              if (info.notes.trim().isNotEmpty) ...[
                const SizedBox(height: 12),
                const Text('تغییرات:', style: TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text(info.notes.trim(), style: const TextStyle(height: 1.7)),
              ],
            ]),
          ),
          actions: [
            if (!info.forced) TextButton(onPressed: () => Navigator.pop(c), child: const Text('بعداً')),
            if (info.stores['bazaar'] != null) TextButton(onPressed: () => _open(info.stores['bazaar']!), child: const Text('کافه‌بازار')),
            if (info.stores['myket'] != null) TextButton(onPressed: () => _open(info.stores['myket']!), child: const Text('مایکت')),
            if (info.url.isNotEmpty) FilledButton(onPressed: () => _open(info.url), child: const Text('دانلود و نصب')),
          ],
        ),
      ),
    );
  }

  static Future<void> _open(String url) async {
    final u = Uri.tryParse(url);
    if (u == null || !(u.scheme == 'https' || u.scheme == 'http' || u.scheme == 'bazaar' || u.scheme == 'myket')) return;
    await launchUrl(u, mode: LaunchMode.externalApplication);
  }
}
