/// Persian digits & small formatting helpers.
const _fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

String faDigits(Object? input) {
  final s = '${input ?? ''}';
  final b = StringBuffer();
  for (final r in s.runes) {
    final c = String.fromCharCode(r);
    final d = int.tryParse(c);
    b.write(d != null && c.codeUnitAt(0) < 128 ? _fa[d] : c);
  }
  return b.toString();
}

String enDigits(String input) {
  final b = StringBuffer();
  for (final r in input.runes) {
    final c = String.fromCharCode(r);
    final i = _fa.indexOf(c);
    if (i >= 0) {
      b.write(i);
    } else if (r >= 0x0660 && r <= 0x0669) {
      b.write(r - 0x0660);
    } else {
      b.write(c);
    }
  }
  return b.toString();
}

/// 1250000 → ۱٬۲۵۰٬۰۰۰
String money(int amount) {
  final s = amount.abs().toString();
  final out = StringBuffer();
  for (var i = 0; i < s.length; i++) {
    if (i > 0 && (s.length - i) % 3 == 0) out.write('٬');
    out.write(s[i]);
  }
  return faDigits('${amount < 0 ? '-' : ''}$out');
}

/// Normalises an Iranian mobile number to 09xxxxxxxxx or returns null.
String? normalizeMobile(String raw) {
  var p = enDigits(raw).replaceAll(RegExp(r'\D'), '');
  if (p.startsWith('0098')) p = '0${p.substring(4)}';
  if (p.startsWith('98') && p.length == 12) p = '0${p.substring(2)}';
  if (p.startsWith('9') && p.length == 10) p = '0$p';
  return RegExp(r'^09\d{9}$').hasMatch(p) ? p : null;
}

/// Gregorian → Jalali.
List<int> toJalali(int gy, int gm, int gd) {
  const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
  final gy2 = gm > 2 ? gy + 1 : gy;
  var days = 355666 + 365 * gy + (gy2 + 3) ~/ 4 - (gy2 + 99) ~/ 100 + (gy2 + 399) ~/ 400 + gd + gdm[gm - 1];
  var jy = -1595 + 33 * (days ~/ 12053);
  days %= 12053;
  jy += 4 * (days ~/ 1461);
  days %= 1461;
  if (days > 365) {
    jy += (days - 1) ~/ 365;
    days = (days - 1) % 365;
  }
  final jm = days < 186 ? 1 + days ~/ 31 : 7 + (days - 186) ~/ 30;
  final jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);
  return [jy, jm, jd];
}

const jalaliMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const weekdaysSatFirst = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

/// "سه‌شنبه ۱۹ آبان"
String jalaliToday([DateTime? now]) {
  final d = now ?? DateTime.now();
  final j = toJalali(d.year, d.month, d.day);
  final wd = weekdaysSatFirst[(d.weekday + 1) % 7]; // DateTime.weekday: Mon=1 … Sun=7.
  return '$wd ${faDigits(j[2])} ${jalaliMonths[j[1] - 1]}';
}

/// Index of today in a Saturday-first week (0 = Saturday).
int todaySatIndex([DateTime? now]) => ((now ?? DateTime.now()).weekday + 1) % 7;

String stripHtml(String html) => html
    .replaceAll(RegExp(r'<(script|style)[^>]*>[\s\S]*?</\1>'), '')
    .replaceAll(RegExp(r'<br\s*/?>|</p>|</li>|</h\d>'), '\n')
    .replaceAll(RegExp(r'<[^>]+>'), '')
    .replaceAll('&nbsp;', ' ')
    .replaceAll('&amp;', '&')
    .replaceAll('&quot;', '"')
    .replaceAll('&#8211;', '–')
    .replaceAll('&#8230;', '…')
    .replaceAll(RegExp(r'\n{3,}'), '\n\n')
    .trim();
