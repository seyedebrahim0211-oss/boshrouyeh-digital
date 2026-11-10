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
}
