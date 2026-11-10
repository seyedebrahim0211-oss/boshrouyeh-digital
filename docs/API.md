# REST API — `/wp-json/bmn/v1/`

همهٔ پاسخ‌ها JSON و فارسی‌اند. مسیرهای عمومی کش ۲ تا ۶۰ دقیقه دارند.

## عمومی
| روش | مسیر | توضیح |
|---|---|---|
| GET | `config` | نام، تماس، شماره‌های اضطراری، ویژگی‌ها، حداقل نسخهٔ اپ |
| GET | `home` | تاریخ امروز، خدمات میانبر، اطلاعیه‌ها، اخبار |
| GET | `search?q=` | جست‌وجوی یکپارچه (خدمات اول) |
| GET | `services?category=` · `services/{id}` · `service-categories` | بانک خدمات |
| GET | `news?page=` · `notices?neighborhood=` · `events` · `tenders` · `content/{id}` | محتوا |
| GET | `places` · `places/{id}` | گردشگری (راهنمای صوتی، مختصات) |
| GET | `neighborhoods` | محله‌ها و برنامهٔ پسماند (۰=شنبه) |
| GET | `deceased?q=` | جست‌وجوی متوفیان (محدودیت نرخ) |

## احراز هویت
| روش | مسیر | بدنه |
|---|---|---|
| POST | `auth/otp` | `{phone}` |
| POST | `auth/verify` | `{phone, code, mode: "token"\|"cookie", device}` → `{access_token, refresh_token, expires_in, user}` |
| POST | `auth/refresh` | `{refresh}` (چرخشی) |
| POST | `auth/logout` | `{refresh}` |

سرآیند: `Authorization: Bearer <access_token>`؛ پاسخ ۴۰۱ با کد `bmn_token_invalid` یعنی refresh لازم است.

## شهروند (نیازمند ورود)
| روش | مسیر | توضیح |
|---|---|---|
| GET/POST | `me` | پروفایل و آمار / ویرایش نام و محله |
| GET | `me/requests` · `me/payments` | پرونده‌ها و پرداخت‌ها |
| POST | `requests` (multipart) | `type=report137\|request\|appointment\|feedback`، `description`، `category`، `lat`، `lng`، `address`، `photo`، `service_id`، `subject`، `preferred_date`، `kind`، `rating` → `{tracking}` |
| GET | `requests/track?code=&phone4=` | پیگیری (مالک بدون phone4) |
| GET | `requests/{id}/photo` | تصویر خصوصی گزارش |
| POST | `dues/inquiry` | `{type: renovation\|vehicle\|business, identifier}` → صورت‌حساب |
| POST | `payments/start` | `{bill_id, return: "web"\|"app"}` → `{token, go, url, method, fields}` |
| GET | `payments/{token}` | وضعیت/رسید |

## صفحه‌ساز — `/wp-json/kb/v1/` (ویرایشگران)
`layout/{id}` (GET/POST)، `render`، `templates`، `templates/{key}`، `disable/{id}`.
