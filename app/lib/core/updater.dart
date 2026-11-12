import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:ota_update/ota_update.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../theme/brand.dart';
import '../widgets/brand_widgets.dart';
import 'api.dart';
import 'app_version.dart';
import 'format.dart';

/// Info returned by GET /bmn/v1/app/version.
class UpdateInfo {
  UpdateInfo({
    required this.current,
    required this.latest,
    required this.min,
    required this.url,
    required this.notes,
    required this.stores,
    this.currentCode = 0,
    this.latestCode = 0,
    this.sha256 = '',
    this.size = 0,
  });
  final String current;
  final String latest;
  final String min;
  final String url;
  final String notes;
  final Map<String, String> stores;

  /// Build numbers (Android versionCode); 0 = unknown, then version names are compared.
  final int currentCode;
  final int latestCode;

  /// SHA-256 of the APK on the site (verified after download when present).
  final String sha256;
  final int size;

  /// Parses the server response (new fields first, older ones as fallback).
  factory UpdateInfo.fromJson(Map<String, dynamic> d, {required String current, int currentCode = 0}) {
    final stores = <String, String>{};
    // WordPress can encode an empty PHP array as [] instead of {}.
    // Ignore that legacy form rather than failing the entire update check.
    final rawStores = d['stores'];
    if (rawStores is Map) {
      rawStores.forEach((k, v) {
        if (v != null && '$v'.trim().isNotEmpty) stores['$k'] = '$v';
      });
    }
    String s(String k) => d[k] == null ? '' : '${d[k]}';
    final apk = s('apk').isNotEmpty ? s('apk') : s('url');
    final latest = s('versionName').isNotEmpty ? s('versionName') : s('latest');
    return UpdateInfo(
      current: current,
      currentCode: currentCode,
      latest: latest,
      latestCode: int.tryParse(s('versionCode')) ?? 0,
      min: s('min'),
      url: apk,
      notes: s('notes'),
      stores: stores,
      sha256: RegExp(r'^[a-fA-F0-9]{64}$').hasMatch(s('sha256')) ? s('sha256').toLowerCase() : '',
      size: int.tryParse(s('size')) ?? 0,
    );
  }

  bool get hasUpdate {
    if (latestCode > 0 && currentCode > 0) return latestCode > currentCode;
    return latest.isNotEmpty && compareVersions(latest, current) > 0;
  }

  /// The APK can be downloaded and installed inside the app (Android, https link).
  bool get canInstallInApp => url.startsWith('https://');
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
  static const _alertsKey = 'update_alerts_enabled';
  static const _backgroundChannel = MethodChannel('bmn.app/update-notifications');

  /// Sync the user's choice with Android WorkManager when the app starts.
  /// Native periodic checks run independently of the Flutter UI while allowed
  /// by the operating system; they do not promise immediate delivery.
  static Future<void> syncBackgroundChecks() async {
    if (!Platform.isAndroid) return;
    await _configureBackground(await alertsEnabled());
  }

  /// Returns true once when an Android background notification opens the app.
  static Future<bool> consumeNotificationLaunch() async {
    if (!Platform.isAndroid) return false;
    try {
      return await _backgroundChannel.invokeMethod<bool>('consumeUpdateTap') ?? false;
    } on MissingPluginException {
      return false;
    } on PlatformException {
      return false;
    }
  }

  static Future<void> _configureBackground(bool enabled) async {
    if (!Platform.isAndroid) return;
    try {
      await _backgroundChannel.invokeMethod<void>('setEnabled', {'enabled': enabled});
    } on MissingPluginException {
      // Older host builds have no native notifier.
    } on PlatformException {
      // Foreground update checks stay usable even if background scheduling fails.
    }
  }

  /// User-controlled automatic update notice; enabled by default.
  static Future<bool> alertsEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_alertsKey) ?? true;
  }

  static Future<void> setAlertsEnabled(bool enabled) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_alertsKey, enabled);
    await _configureBackground(enabled);
  }

  static Future<String> currentVersion() => AppVersion.name();

  static Future<UpdateInfo?> fetch(Api api) async {
    try {
      final d = Map<String, dynamic>.from(await api.get('app/version', query: {'platform': Platform.isIOS ? 'ios' : 'android'}) as Map);
      return UpdateInfo.fromJson(d, current: await currentVersion(), currentCode: await AppVersion.code());
    } catch (_) {
      return null;
    }
  }

  /// Called once on start: shows a dialog when a newer build exists (max once a day unless forced).
  static Future<void> checkOnStart(BuildContext context, Api api) async {
    if (!await alertsEnabled()) return;
    final info = await fetch(api);
    if (info == null || !info.hasUpdate || !info.canDownload || !context.mounted) return;
    final prefs = await SharedPreferences.getInstance();
    final snooze = prefs.getString(_snoozeKey) ?? '';
    final today = DateTime.now().toIso8601String().substring(0, 10);
    if (!info.forced && snooze == '${info.latest}@$today') return;
    if (!context.mounted) return;
    await showUpdateDialog(context, info);
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
            if (!info.forced) TextButton(
              onPressed: () async {
                final prefs = await SharedPreferences.getInstance();
                final today = DateTime.now().toIso8601String().substring(0, 10);
                await prefs.setString(_snoozeKey, '${info.latest}@$today');
                if (c.mounted) Navigator.pop(c);
              },
              child: const Text('بعداً'),
            ),
            if (info.stores['bazaar'] != null) TextButton(onPressed: () => _open(info.stores['bazaar']!), child: const Text('کافه‌بازار')),
            if (info.stores['myket'] != null) TextButton(onPressed: () => _open(info.stores['myket']!), child: const Text('مایکت')),
            if (info.url.isNotEmpty)
              FilledButton(
                onPressed: () {
                  if (!info.forced) Navigator.pop(c);
                  install(context, info);
                },
                child: Text(info.size > 0 ? 'بروزرسانی (${faDigits((info.size / 1048576).toStringAsFixed(1))} مگ)' : 'بروزرسانی'),
              ),
          ],
        ),
      ),
    );
  }

  /// Downloads the APK inside the app (progress bar + SHA-256 check) and opens the Android
  /// installer. Falls back to the browser when in-app install is not possible.
  static Future<void> install(BuildContext context, UpdateInfo info) async {
    if (!Platform.isAndroid || !info.canInstallInApp) {
      await _open(info.url);
      return;
    }
    final status = ValueNotifier<String>('در حال آماده‌سازی…');
    final pct = ValueNotifier<double?>(null);
    final failed = ValueNotifier<bool>(false);
    StreamSubscription<OtaEvent>? sub;
    var open = true;
    void close(BuildContext c) {
      if (open && Navigator.of(c).canPop()) Navigator.of(c).pop();
      open = false;
    }

    unawaited(showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (c) => PopScope(
        canPop: false,
        child: AlertDialog(
          title: Text('دریافت نسخهٔ ${faDigits(info.latest)}'),
          content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            ValueListenableBuilder<double?>(valueListenable: pct, builder: (_, v, __) => LinearProgressIndicator(value: v, minHeight: 8, borderRadius: BorderRadius.circular(8))),
            const SizedBox(height: 12),
            ValueListenableBuilder<String>(valueListenable: status, builder: (_, v, __) => Text(v, style: const TextStyle(height: 1.7))),
          ]),
          actions: [
            ValueListenableBuilder<bool>(
              valueListenable: failed,
              builder: (_, f, __) => f
                  ? TextButton(onPressed: () { close(c); _open(info.url); }, child: const Text('دانلود با مرورگر'))
                  : const SizedBox.shrink(),
            ),
            TextButton(
              onPressed: () {
                sub?.cancel();
                try {
                  OtaUpdate().cancel();
                } catch (_) {}
                close(c);
              },
              child: const Text('بستن'),
            ),
          ],
        ),
      ),
    ).then((_) => open = false));

    void fail(String msg) {
      status.value = msg;
      pct.value = 0;
      failed.value = true;
    }

    try {
      sub = OtaUpdate()
          .execute(info.url, destinationFilename: 'boshrouyeh-${info.latestCode > 0 ? info.latestCode : info.latest}.apk', sha256checksum: info.sha256.isEmpty ? null : info.sha256)
          .listen((e) {
        switch (e.status) {
          case OtaStatus.DOWNLOADING:
            final v = double.tryParse(e.value ?? '');
            pct.value = v == null ? null : (v / 100).clamp(0, 1).toDouble();
            status.value = 'در حال دانلود… ${faDigits('${v?.round() ?? 0}')}٪';
            break;
          case OtaStatus.INSTALLING:
            pct.value = 1;
            status.value = 'دانلود کامل شد. در پنجرهٔ اندروید «نصب» یا «بروزرسانی» را بزنید.';
            break;
          case OtaStatus.PERMISSION_NOT_GRANTED_ERROR:
            fail('اجازهٔ نصب داده نشد. در تنظیمات گوشی، «نصب برنامه‌های ناشناس» را برای «بشرویهٔ من» فعال کنید و دوباره بروزرسانی را بزنید.');
            break;
          case OtaStatus.CHECKSUM_ERROR:
            fail('فایل دانلودشده سالم نیست (هش یکسان نیست). دوباره تلاش کنید.');
            break;
          default:
            fail('دانلود انجام نشد. اتصال اینترنت را بررسی کنید یا با مرورگر دانلود کنید.');
        }
      }, onError: (_) => fail('دانلود انجام نشد. اتصال اینترنت را بررسی کنید یا با مرورگر دانلود کنید.'));
    } catch (_) {
      fail('بروزرسانی درون‌برنامه‌ای ممکن نشد؛ با مرورگر دانلود کنید.');
    }
  }

  static Future<void> _open(String url) async {
    final u = Uri.tryParse(url);
    if (u == null || !(u.scheme == 'https' || u.scheme == 'http' || u.scheme == 'bazaar' || u.scheme == 'myket')) return;
    await launchUrl(u, mode: LaunchMode.externalApplication);
  }
}
