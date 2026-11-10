import 'package:boshrouyeh_man/models/models.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Service parses API payload', () {
    final s = Service.fromJson({
      'id': 12,
      'title': 'عوارض نوسازی و سطح شهر',
      'short_title': 'پرداخت عوارض',
      'icon': 'payment',
      'mode': 'online',
      'categories': [
        {'slug': 'dues', 'name': 'عوارض و پرداخت'}
      ],
      'steps': ['الف', 'ب'],
      'faq': [
        {'q': 'سوال؟', 'a': 'پاسخ'}
      ],
    });
    expect(s.shortTitle, 'پرداخت عوارض');
    expect(s.modeLabel, 'آنلاین');
    expect(s.categories, ['dues']);
    expect(s.faq.first.a, 'پاسخ');
  });

  test('RequestItem chip kind', () {
    final r = RequestItem.fromJson({'status': 'done', 'progress': 4, 'timeline': [
      {'status_label': 'ثبت شد', 'note': '', 'date_fa': '۱۹ آبان'}
    ]});
    expect(r.chipKind, 'done');
    expect(r.timeline.length, 1);
  });

  test('Bill totals', () {
    final b = Bill.fromJson({'bill_id': 'x', 'items': [
      {'label': 'a', 'amount': 1000}
    ], 'discount': 100, 'total': 900, 'demo': true});
    expect(b.total, 900);
    expect(b.demo, isTrue);
  });
}
