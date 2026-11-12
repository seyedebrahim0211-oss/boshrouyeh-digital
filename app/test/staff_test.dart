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

  test('sections from server are grouped; full dashboard kept apart', () {
    final list = StaffSection.listFrom([
      {'key': 'wp', 'label': 'پیشخوان کامل وردپرس', 'icon': 'home', 'path': '', 'group': 'مدیر کل'},
      {'key': 'pr', 'label': 'میز روابط عمومی', 'icon': 'bell', 'path': 'admin.php?page=bmnm-hub', 'group': 'روابط عمومی'},
      {'key': 'news', 'label': 'خبر تازه', 'icon': 'plus', 'path': 'post-new.php', 'group': 'روابط عمومی'},
      {'key': 'requests', 'label': 'درخواست‌ها', 'icon': '137', 'path': 'edit.php?post_type=bmn_request', 'group': 'رسیدگی'},
      'bad',
    ]);
    expect(list.length, 4);
    expect(list.first.isFullDashboard, isTrue);
    final g = StaffSection.grouped(list);
    expect(g.keys.toList(), ['روابط عمومی', 'رسیدگی']);
    expect(g['روابط عمومی']!.map((s) => s.key), ['pr', 'news']);
    expect(StaffInfo.fromJson({'is_staff': true, 'caps': {'admin': true}}).canAdmin, isTrue);
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
