import 'package:boshrouyeh_man/models/staff.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('citizen has no staff access', () {
    expect(StaffInfo.fromJson(null).isStaff, isFalse);
    expect(StaffInfo.fromJson({'is_staff': false}).canApprove, isFalse);
  });

  test('staff caps are parsed', () {
    final s = StaffInfo.fromJson({
      'is_staff': true,
      'role': 'مدیر کل',
      'caps': {'approve': true, 'requests': true, 'payments': false, 'sms': true},
    });
    expect(s.isStaff, isTrue);
    expect(s.role, 'مدیر کل');
    expect(s.canApprove, isTrue);
    expect(s.canPayments, isFalse);
    expect(s.canSms, isTrue);
  });

  test('admin summary and pending items', () {
    final sum = AdminSummary.fromJson({
      'tiles': [
        {'label': 'درخواست‌های باز', 'value': 12, 'icon': 'doc-check'},
        'bad',
      ],
      'app_version': '1.0.12',
    });
    expect(sum.tiles.length, 1);
    expect(sum.tiles.first.$2, '12');
    expect(sum.appVersion, '1.0.12');
    final p = PendingItem.fromJson({'id': 7, 'title': 'خبر', 'kind': 'خبر', 'status': 'پیش‌نویس', 'date_fa': '۲۱ آبان', 'url': 'https://x'});
    expect(p.id, 7);
    expect(p.date, '۲۱ آبان');
  });
}
