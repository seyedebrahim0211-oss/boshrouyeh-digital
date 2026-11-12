import 'package:boshrouyeh_man/core/updater.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('compareVersions', () {
    expect(compareVersions('1.0.10', '1.0.9'), greaterThan(0));
    expect(compareVersions('v1.2.0', '1.2'), 0);
    expect(compareVersions('1.0.0', '1.0.1'), lessThan(0));
    expect(compareVersions('2.0.0+5', '1.9.9'), greaterThan(0));
  });

  test('UpdateInfo flags', () {
    final i = UpdateInfo(current: '1.0.3', latest: '1.0.7', min: '1.0.5', url: 'https://x/app.apk', notes: '', stores: const {});
    expect(i.hasUpdate, isTrue);
    expect(i.forced, isTrue);
    expect(i.canDownload, isTrue);
    final j = UpdateInfo(current: '1.0.7', latest: '1.0.7', min: '1.0.0', url: '', notes: '', stores: const {});
    expect(j.hasUpdate, isFalse);
    expect(j.forced, isFalse);
  });

  test('fromJson prefers site-hosted APK fields and build numbers', () {
    final sha = 'A' * 64;
    final i = UpdateInfo.fromJson({
      'latest': '1.0.40',
      'url': 'https://github.com/x.apk',
      'versionName': '1.0.42',
      'versionCode': 42,
      'apk': 'https://varsin-bsh.ir/wp-content/uploads/bmn-app/boshrouyeh-42-ab.apk',
      'sha256': sha,
      'size': 20971520,
      'min': '1.0.0',
      'stores': {'bazaar': '', 'myket': 'https://myket.ir/app/x'},
    }, current: '1.0.41', currentCode: 41);
    expect(i.latest, '1.0.42');
    expect(i.url, contains('varsin-bsh.ir'));
    expect(i.sha256, 'a' * 64);
    expect(i.size, 20971520);
    expect(i.stores.keys, ['myket']);
    expect(i.hasUpdate, isTrue);
    expect(i.canInstallInApp, isTrue);
  });

  test('build number wins over version name when both are known', () {
    final same = UpdateInfo.fromJson({'versionName': '1.0.42', 'versionCode': 42, 'apk': 'https://x/a.apk'}, current: '1.0.9', currentCode: 42);
    expect(same.hasUpdate, isFalse);
    final older = UpdateInfo.fromJson({'latest': '1.0.5', 'url': 'http://x/a.apk', 'sha256': 'bad'}, current: '1.0.4');
    expect(older.hasUpdate, isTrue);
    expect(older.sha256, '');
    expect(older.canInstallInApp, isFalse);
  });
}
