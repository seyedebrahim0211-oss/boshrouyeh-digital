import 'dart:io';

import 'package:package_info_plus/package_info_plus.dart';

/// Installed app version, cached once per run.
class AppVersion {
  static Future<PackageInfo>? _info;

  static Future<PackageInfo> info() => _info ??= PackageInfo.fromPlatform();

  /// Version name, e.g. "1.0.42".
  static Future<String> name() async => (await info()).version;

  /// Build number (Android versionCode), e.g. 42. 0 when unknown.
  static Future<int> code() async => int.tryParse((await info()).buildNumber) ?? 0;

  /// Sent with every API request so the website knows which app version is calling
  /// (the site's update button and statistics read "BoshrouyehApp/<version>").
  static Future<String> userAgent() async {
    try {
      final i = await info();
      final os = Platform.isAndroid ? 'Android' : (Platform.isIOS ? 'iOS' : Platform.operatingSystem);
      return 'BoshrouyehApp/${i.version} ($os; build ${i.buildNumber})';
    } catch (_) {
      return 'BoshrouyehApp/0';
    }
  }
}
