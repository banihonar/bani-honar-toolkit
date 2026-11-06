# Bani Honar Toolkit

A modular WordPress plugin by **Bani Honar Advertising Agency** (کانون آگهی و تبلیغات بانی هنر پارس): visitor statistics, a link shortener on your own domain, a QR code generator, a post duplicator with safe editing of live pages, a page shield and local profile pictures. Persian (RTL) and English screens.

- Version: **1.0.0** (first public release)
- Requires: WordPress 5.8+, PHP 7.4+
- License: GPL-2.0-or-later
- Website: <https://banihonarpars.ir> · Contact: it@banihonarpars.ir

## Install

1. Download `Bani-Honar-Toolkit-1.0.0-with-Persian.zip` from the [Releases](../../releases) page. (`Bani-Honar-Toolkit-1.0.0.zip` is the same plugin without the Persian translation files; it is the package for the WordPress.org directory, which delivers translations itself.)
2. In WordPress open **Plugins → Add New → Upload Plugin**, choose the zip and press **Install Now**, then **Activate**.
3. Open **Bani Honar Toolkit** in the admin menu. Switch modules on or off on the dashboard.

Do not use the green "Code → Download ZIP" button of GitHub: that file contains the whole repository, and WordPress needs only the `bani-honar-toolkit` folder.

## Modules

| Module | What it does | Default |
| --- | --- | --- |
| Link Shortener | Short links on your own domain (`example.com/Ab33`), random or custom case-sensitive codes, click counter. | on |
| QR Code Generator | QR codes for links and text, PNG/SVG, drawn in the browser. | on |
| Visitor Stats | Visits with country, IP address, source, device brand and model, system, browser; filters, CSV, IP blocking. | **off** |
| Post Duplicator | Duplicate posts, pages and custom content; **Edit safely** through a hidden draft. | on |
| Page Shield | Blocks the right-click menu, developer shortcuts and image dragging; hides WordPress version details. No effect on search engines. | **off** |
| Profile Pictures | Local profile pictures, no Gravatar. | on |
| Contact | Suggestions, criticism, problem reports and module requests to the IT team: opens Gmail or your mail program, or copies the message. | always |

### Page Shield

Page Shield cannot make a page impossible to copy: a browser must receive the HTML, CSS and JavaScript of a page, so a determined person can always read them. It only stops casual copying, and every option is off until you tick it. It offers only options that have **no effect on search engines**: the right-click menu (still works in text fields), F12 / Ctrl+U / Ctrl+S / Ctrl+Shift+I, J, C, image dragging, and hiding WordPress version details. Options that could lower your search ranking, such as blocking text selection, fake code in the page source or covering the page, are deliberately not offered.

### Privacy

- Visitor Stats is off by default and sets no cookies. IP addresses can be stored in full or shortened.
- The online country lookup (ipwho.is or GeoJS) is **off by default**. Without it, no address leaves your server. What is sent, and the terms and privacy policy of each service, are listed under "External services" in `readme.txt`.
- The toolkit adds suggested text to the WordPress privacy policy guide and supports **Tools → Export / Erase Personal Data**.
- The Contact tab sends nothing from your website; its buttons open Gmail in a new tab or the e-mail program of your computer, or copy the message.

## Security

Please report security problems privately: see [SECURITY.md](SECURITY.md).

## Development

```bash
# Automated checks (a small stand-in for WordPress with PHP + SQLite; not a real WordPress site)
bash tests/run.sh

# Build the zip for the WordPress upload screen
bash bin/build-zip.sh
```

The checks are not a replacement for testing on a real WordPress site. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License and brand

The code is released under **GPL-2.0-or-later** (see [LICENSE](LICENSE)). The names "Bani Honar", "Bani Honar Pars" and the logo are brand marks of Bani Honar Advertising Agency and are not licensed with the code: if you publish a modified version, give it another name and logo. See [NOTICE.md](NOTICE.md).

---

## فارسی

**ابزارهای بانی هنر** یک افزونه‌ی ماژولار وردپرس از **کانون آگهی و تبلیغات بانی هنر پارس** است: آمار بازدید، کوتاه‌کننده‌ی لینک با دامنه‌ی خودتان، ساخت کیو آر کد، تکثیر نوشته و برگه با «ویرایش امن» صفحه‌های زنده، محافظ صفحه و تصویر پروفایل محلی. رابط فارسی (راست‌به‌چپ) و انگلیسی.

### نصب

۱. فایل `Bani-Honar-Toolkit-1.0.0-with-Persian.zip` را از صفحه‌ی [Releases](../../releases) دانلود کنید. (فایل `Bani-Honar-Toolkit-1.0.0.zip` همین افزونه بدون فایل‌های ترجمه‌ی فارسی است و برای فهرست وردپرس‌دات‌ارگ ساخته شده، چون خود وردپرس‌دات‌ارگ ترجمه را می‌رساند.)
۲. در وردپرس به **افزونه‌ها ← افزودن ← بارگذاری افزونه** بروید، فایل را انتخاب کنید و **نصب** و سپس **فعال‌سازی** را بزنید.
۳. از منوی **Bani Honar Toolkit** ماژول‌ها را روشن یا خاموش کنید.

از دکمه‌ی «Download ZIP» خودِ گیت‌هاب استفاده نکنید؛ آن فایل کل مخزن را دارد و وردپرس فقط پوشه‌ی `bani-honar-toolkit` را لازم دارد.

### محافظ صفحه

هیچ ابزاری نمی‌تواند کپی‌کردن صفحه را غیرممکن کند، چون مرورگر باید HTML و CSS و جاوااسکریپت صفحه را دریافت کند. محافظ صفحه فقط جلوی کپی ساده را می‌گیرد و همه‌ی گزینه‌هایش تا وقتی تیک نزنید خاموش‌اند. فقط گزینه‌هایی در آن هست که **هیچ اثری روی موتورهای جستجو ندارند**: منوی راست‌کلیک (داخل فیلدهای متنی کار می‌کند)، میانبرهای F12 و Ctrl+U و Ctrl+S و Ctrl+Shift+I/J/C، کشیدن تصویر، و پنهان‌کردن نسخه‌ی وردپرس. گزینه‌هایی که می‌توانند رتبه‌ی سایت را پایین بیاورند (مثل جلوگیری از انتخاب متن، کد جعلی در سورس صفحه یا پوشاندن صفحه) عمداً در افزونه نیستند.

### حریم خصوصی

آمار بازدید پیش‌فرض خاموش است و کوکی نمی‌سازد. پیدا کردن کشور از راه سرویس آنلاین نیز **پیش‌فرض خاموش** است؛ بدون آن هیچ آدرسی از سرور شما بیرون نمی‌رود. افزونه با ابزارهای «برون‌بری و پاک‌سازی داده‌های شخصی» وردپرس هم کار می‌کند.

### تماس و گزارش مشکل

پیشنهاد، انتقاد، گزارش مشکل یا درخواست ماژول جدید را از تب **تماس با ما** داخل افزونه بفرستید (جیمیل یا برنامه‌ی ایمیل باز می‌شود، یا متن را کپی می‌کنید) یا به **it@banihonarpars.ir** ایمیل بزنید. مشکلات امنیتی را لطفاً عمومی منتشر نکنید؛ فایل [SECURITY.md](SECURITY.md) را ببینید.

### مجوز و نام تجاری

کد تحت مجوز **GPL-2.0-or-later** منتشر شده است. نام «بانی هنر» و لوگو علامت تجاری کانون آگهی و تبلیغات بانی هنر پارس است و همراه کد مجوز داده نشده؛ اگر نسخه‌ی تغییریافته‌ای منتشر می‌کنید، نام و لوگوی دیگری بگذارید.
