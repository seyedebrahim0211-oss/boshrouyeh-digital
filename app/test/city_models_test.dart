import 'package:boshrouyeh_man/models/models.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Directive parses dossier fields', () {
    final d = Directive.fromJson({
      'id': 5, 'code': '405100', 'issue': 'گودال', 'order': 'ترمیم', 'unit': 'عمران', 'deadline_fa': '۷ آذر', 'days_left': -2,
      'funding': 'عمرانی', 'funding_note': '', 'status': 'overdue', 'status_label': 'با تأخیر', 'overdue': true, 'progress': 40,
      'updated_fa': '۱۹ آبان', 'visit': {'id': 9, 'title': 'بازدید'}, 'log': [{'status_label': 'ابلاغ شد', 'progress': 0, 'note': '', 'date_fa': '۸ آبان'}],
    });
    expect(d.overdue, isTrue);
    expect(d.chipKind, 'closed');
    expect(d.daysLeft, -2);
    expect(d.visitId, 9);
    expect(d.log.length, 1);
  });

  test('Visit counts', () {
    final v = Visit.fromJson({'id': 1, 'title': 't', 'counts': {'total': 4, 'done': 1, 'overdue': 1}});
    expect(v.total, 4);
    expect(v.directives, isEmpty);
  });

  test('Bus status text', () {
    final b = Bus.fromJson({'id': 1, 'name': 'م ۱۰۱', 'online': true, 'lat': 33.8, 'lng': 57.4, 'at_stop': '', 'last': 'بازار', 'next': 'شهرداری', 'eta_min': 3});
    expect(b.statusText, contains('شهرداری'));
    final off = Bus.fromJson({'id': 2, 'name': 'x', 'online': false, 'lat': null});
    expect(off.statusText, contains('هنوز'));
    final r = BusRoute.fromJson({'id': 1, 'name': 'خط ۱', 'color': '#16737A', 'stops': [{'name': 'a', 'lat': 33.1, 'lng': 57.1}], 'vehicles': [{'id': 1, 'name': 'v'}]});
    expect(r.stops.single.name, 'a');
    expect(r.buses.single.name, 'v');
  });

  test('RequestItem unit + referral', () {
    final r = RequestItem.fromJson({'status': 'review', 'unit': 'خدمات شهری', 'timeline': [
      {'kind': 'referral', 'status_label': 'ارجاع به خدمات شهری', 'note': '', 'date_fa': ''}
    ]});
    expect(r.unit, 'خدمات شهری');
    expect(r.timeline.single.referral, isTrue);
  });
}
