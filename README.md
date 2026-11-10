# شهرداری بشرویه — سایت، صفحه‌ساز و اپ «بشرویهٔ من»

> شهرِ خشت، باد و قنات

پیاده‌سازی کامل سند هویت بصری و دیجیتال شهرداری بشرویه: **قالب بلوکی وردپرس «خشت»**، **سه افزونه** (هسته، خدمات الکترونیک، صفحه‌ساز خشت‌ساز) و **اپ Flutter «بشرویهٔ من»**.

| بخش | مسیر | خلاصه |
|---|---|---|
| قالب خشت (FSE) | `wordpress/themes/boshrouyeh-khesht` | theme.json با پالت کاهگل/قنات، قلم‌های لاله‌زار/وزیرمتن/امیری، الگوها و قالب‌های همهٔ صفحات، حالت شب کویر، نوار دسترس‌پذیری، RTL، واکنش‌گرا |
| Boshrouyeh Core | `wordpress/plugins/boshrouyeh-core` | ۷ نوع نوشته (خدمت، اطلاعیه، مناقصه، جاذبه، رویداد، شفافیت، متوفیان)، فیلدهای سفارشی بدون ACF، تاریخ شمسی، ۲۲ بلوک پویا، REST API اپ، اسکیما (GovernmentOrganization/Service/FAQ/Event/TouristAttraction)، PWA، سخت‌سازی امنیتی، درون‌ریزی ۳۸ خدمت |
| Boshrouyeh E-Services | `wordpress/plugins/boshrouyeh-eservices` | ورود با کد پیامکی (کاوه‌نگار/SMS.ir)، پیشخوان شهروند، ۱۳۷ با عکس و نقشه، پیگیری با کد، استعلام و پرداخت عوارض (زرین‌پال/سامان/آزمایشی) با رسید، نوبت ملاقات، انتقاد و پیشنهاد، JWT برای اپ، شاخص‌های داشبورد |
| Khesht Builder | `wordpress/plugins/khesht-builder` | صفحه‌ساز مستقل کشیدنی: بخش/ستون، ۴۰ ویجت هویتی و پویا، ۶ قالب آماده، پیش‌نمایش زندهٔ دسکتاپ/تبلت/موبایل، واگرد/ازنو، رندر امن سمت سرور |
| اپ «بشرویهٔ من» | `app/` | Flutter (اندروید/iOS): خانه، خدمات، پرداخت، ۱۳۷، پیگیری، گردشگری با راهنمای صوتی، محلهٔ من، پیشخوان، شب کویر، کش آفلاین |

## نصب سریع (Docker)
```bash
cp .env.example .env   # رمزها را تغییر دهید
docker compose up -d   # http://localhost:8080
```
سرویس `cli` وردپرس را نصب، قالب و افزونه‌ها را فعال و محتوای پایه (دسته‌ها، ۳۸ خدمت، جاذبه‌ها، برگه‌ها) را درون‌ریزی می‌کند.

## نصب روی هاست
1. پوشهٔ `wordpress/themes/boshrouyeh-khesht` را در `wp-content/themes` و سه افزونه را در `wp-content/plugins` کپی کنید (یا zip هر پوشه را از پیشخوان بارگذاری کنید).
2. ترتیب فعال‌سازی: **Boshrouyeh Core ← E-Services ← Khesht Builder**، سپس قالب «خشت».
3. «شهرداری بشرویه ← تنظیمات» را تکمیل و «درون‌ریزی محتوای پایه» را بزنید.
4. «شهرداری بشرویه ← خدمات الکترونیک»: سرویس پیامک و درگاه بانکی را تنظیم کنید (پیش‌فرض: Log و درگاه آزمایشی).
5. در `wp-config.php`:
   ```php
   define( 'BMN_JWT_SECRET', 'یک رشتهٔ تصادفی ۶۴ نویسه‌ای' );
   define( 'BMN_PRIVATE_DIR', '/home/USER/private-uploads' ); // بیرون از public_html
   define( 'DISALLOW_FILE_EDIT', true );
   ```
6. افزونه‌های پیشنهادی سند: WP-Parsidate (اختیاری؛ تاریخ شمسی داخلی هم هست)، Rank Math، Wordfence، UpdraftPlus، LiteSpeed Cache، Polylang (نسخهٔ انگلیسی گردشگری).

جزئیات: [docs/INSTALL.md](docs/INSTALL.md) · API: [docs/API.md](docs/API.md) · صفحه‌ساز: [docs/BUILDER.md](docs/BUILDER.md) · امنیت: [SECURITY.md](SECURITY.md)

## توسعه و تست
```bash
tests/e2e/setup-wp.sh                       # وردپرس موقت با SQLite
php -S localhost:8088 -t .wp/site tests/e2e/router.php
node tests/e2e/e2e.js && node tests/e2e/builder.js   # Playwright
php bin/sync-icons.php                      # هم‌سان‌سازی آیکن‌ها (قالب ← افزونه ← اپ)
```
CI (GitHub Actions): بررسی نحوی PHP ۸٫۰ تا ۸٫۳، قوانین امنیتی WPCS، تست‌های end-to-end روی وردپرس واقعی، و `flutter analyze/test/build apk` (فایل APK به‌صورت artifact).

## نکات مهم پیش از انتشار
- مبالغ، نام‌ها و تاریخ‌های محتوای نمونه فرضی‌اند؛ فهرست نهایی خدمات، مدارک و هزینه‌ها باید با واحدها و مصوبات شورا تطبیق داده شود.
- ارائه‌دهندهٔ عوارض پیش‌فرض «آزمایشی» است؛ اتصال به سامانهٔ درآمد شهرداری از طریق فیلتر `bmn_dues_provider` (پیاده‌سازی `BMN_Dues_Provider`) انجام می‌شود.
- درگاه‌های زرین‌پال و سامان طبق مستندات عمومی پیاده شده‌اند؛ پیش از انتشار با ترمینال واقعی در محیط آزمایشی بانک تست شوند.
- نشان SVG از روی فایل سند بازترسیم (vectorize) شده؛ نسخهٔ رسمی برداری را در `brand/` جایگزین کنید.

مجوز: GPL-2.0-or-later — قلم‌ها: SIL OFL 1.1 — Leaflet: BSD-2-Clause.
