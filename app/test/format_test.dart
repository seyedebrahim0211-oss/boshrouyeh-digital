import 'package:boshrouyeh_man/core/format.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Persian digits', () {
    expect(faDigits('1405/08/19'), '۱۴۰۵/۰۸/۱۹');
    expect(enDigits('۰۹۱۲٣'), '09123');
  });

  test('money separators', () {
    expect(money(1152000), '۱٬۱۵۲٬۰۰۰');
    expect(money(0), '۰');
  });

  test('mobile normalisation', () {
    expect(normalizeMobile('۰۹۱۲۳۴۵۶۷۸۹'), '09123456789');
    expect(normalizeMobile('+98 912 345 6789'), '09123456789');
    expect(normalizeMobile('9123456789'), '09123456789');
    expect(normalizeMobile('12345'), isNull);
  });

  test('Jalali conversion', () {
    expect(toJalali(2026, 11, 10), [1405, 8, 19]);
    expect(toJalali(2025, 3, 21), [1404, 1, 1]);
    expect(toJalali(2024, 3, 20), [1403, 1, 1]);
  });

  test('today label uses Saturday-first weekdays', () {
    // 2026-11-10 is a Tuesday (سه‌شنبه).
    expect(jalaliToday(DateTime(2026, 11, 10)), 'سه‌شنبه ۱۹ آبان');
    expect(todaySatIndex(DateTime(2026, 11, 7)), 0); // Saturday.
  });

  test('stripHtml', () {
    expect(stripHtml('<p>سلام</p><p>بشرویه&nbsp;</p><script>x()</script>'), 'سلام\nبشرویه');
  });
}
