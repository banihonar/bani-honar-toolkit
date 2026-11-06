=== Bani Honar Toolkit ===
Contributors: banihonar
Tags: visitor statistics, duplicate post, url shortener, qr code, page shield
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visitor statistics, link shortener, QR codes, post duplicator with safe editing and page shield in one modular plugin. Persian and English.

== Description ==

Bani Honar Toolkit is a small, modular plugin. Every feature is a module that you can switch on or off from one dashboard, and developers can add their own modules.

**Included modules**

* **Link Shortener** - turn long addresses into short links on your own domain, for example `banihonar.com/Ab33`.
  * Random codes made of lowercase letters, uppercase letters and numbers. You choose the length, from 1 to 32 characters.
  * Or type your own custom code. Codes are case-sensitive: `Ab` and `ab` are different links.
  * Click counter and last-click time for every link, search, edit and delete.
  * Redirect type (302, 307 or 301) and an optional path prefix such as `/go/`.
  * A short link never hides a real page of your site. It only works when WordPress finds nothing else at that address.
* **QR Code Generator** - turn a link or any text into a QR code and download it as PNG or SVG.
  * Choose the size, error-correction level, border and colors. Persian text works too.
  * Pick one of your short links, or press the QR button next to a link in the Link Shortener list.
  * The code is created in your browser. Nothing is uploaded to an outside service or stored.
* **Visitor Stats** (off by default) - see every visit to your website: how many, where from, from which IP address and country, and on which device.
  * Overview with a chart, totals (page views, visitors, visits, online now) and lists of countries, sources, device types, phone and tablet models, operating systems, browsers, campaigns and most viewed pages.
  * Sources are recognised: direct, search engines, social networks and messengers (Instagram, Telegram, Eitaa, Rubika, Bale and more), other websites and `utm_` campaigns.
  * Device details: brand and model (for example Samsung Galaxy A54 5G), system and version, browser and version, screen size, language and time zone. Chrome on Android hides the phone model from websites, so it is read through Client Hints; an iPhone model cannot be read by any website, so its family is shown.
  * Visit log with search, filters (click any value to filter by it), details for every visit, and CSV download that opens correctly in Excel.
  * Words visitors typed into the search box of your website, and the member (name and e-mail) behind a visit when the visitor is logged in to your site.
  * Block an IP address or an IPv4 range for an hour, a day, a week, a month or until you remove it. Your own address and logged-in administrators are never blocked.
  * Persian (Jalali) or Gregorian dates, a retention period, and a list of IP addresses that are never counted.
  * Works with page-caching plugins and sets no cookies. Your own visits are not counted while you are logged in as an administrator.
* **Post Duplicator** - copy any post, page or custom content with one click, and edit a live page safely.
  * "Duplicate" under every title in the lists, in the bulk actions menu, in the admin bar and in a box in the editor. The copy is a draft.
  * Copies the content, excerpt, featured image, categories, tags and other taxonomies (in their saved order), custom fields, page template and post format. Comments, sub-pages (up to 100 posts), password, address, date, author and status are optional.
  * Safe with page builders: backslashes in blocks and Elementor data are kept exactly, edit locks and page-builder caches are not copied, and copying does not put a page in the translation group of Polylang.
  * **Edit safely**: for a page that is already live, work on a hidden draft while visitors keep seeing the live page, then press "Publish into the live page". The address, date, status, author and comments of the live page stay, and its old text is saved as a revision. If the live page was changed after the draft was made, you are asked before anything is overwritten.
  * A title pattern with `{title}`, `{n}` and `{date}`, a choice of who may duplicate, the content types it works for, and lists of custom fields and taxonomies that are never copied. Its own tab with a switch that turns it fully on or off.
  * Nobody can copy what they are not allowed to read; people who may not publish get "pending review" copies. WooCommerce products and orders are left to WooCommerce, because they keep data in extra tables.
* **Page Shield** (off by default) - makes casual copying of your pages harder. It has its own tab with a switch that turns it fully on or off. Every option is off until you tick it, and none of them has any effect on search engines.
  * Blocks the right-click menu (it still works in text fields), F12, Ctrl+U, Ctrl+S and Ctrl+Shift+I / J / C, and dragging images out of the page.
  * Hides WordPress version details from the page head.
  * Can also protect logged-in administrators (off by default, so you can inspect your own site).
  * These are deterrents, not a lock: a browser must receive a page's HTML, CSS and JavaScript, so a determined person can always read them. Your PHP code never reaches the browser. The Elementor editor is not affected, and search engines see exactly the same page as everyone else. Options that could lower your search ranking, such as blocking text selection or covering the page, are deliberately not offered.
* **Contact the IT team** - a tab where you can send a suggestion, criticism, a problem report, a security report or a request for a new module to it@banihonarpars.ir. Nothing is sent by your website: each card opens Gmail in a new tab, or the e-mail program of your computer, with a ready subject and text, and has a button that copies the whole message so you can send it from any e-mail service. A problem report adds only the versions of the toolkit, WordPress and PHP, the language and the list of active modules.
* **Profile Pictures** - every user uploads a profile picture from the WordPress profile screen. No Gravatar account is needed, and nothing is requested from Gravatar.
  * JPG, PNG, GIF and WebP, up to 2 MB. Pictures are cropped to a small square automatically.

**Two languages, one switch**

The plugin screens can be shown in **Persian (RTL)** or **English (LTR)**, whatever your site language is. Change it under Bani Honar Toolkit > Settings.

**For developers**

Extend `BHTK_Module` and register your module on the `bhtk_register_modules` action. It gets a dashboard card, an on/off switch and its own section on the Settings screen.

`add_action( 'bhtk_register_modules', function ( $plugin ) { $plugin->register_module( new My_Module() ); } );`

Use the `bhtk_capability` filter to change the capability needed to use the toolkit (default: `manage_options`).

**Third-party code**

The QR Code Generator uses the MIT-licensed "QR Code Generator for JavaScript" by Kazuhiko Arase (version 2.0.4), included in `assets/js/vendor/`. QR Code is a registered trademark of DENSO WAVE INCORPORATED.

**Privacy and external services**

The Post Duplicator stores no personal data. It marks copies and safe-edit drafts with four small custom fields (`_bhtk_clone_of`, `_bhtk_fork_of`, `_bhtk_open_fork`, `_bhtk_fork_time`) that hold post numbers and a time; they are removed when you delete all toolkit data.

The link shortener stores, for each link: the code, the destination, an optional note, the click count, the time of the last click, who created it and when. Visitors are not identified or logged by it.

The Visitor Stats module is off by default. While it is on, one small request is sent from the visitor's browser to your own website for every page opened (at most 600 views a minute are recorded for the whole site; developers can change this with the `bhtk_visits_max_per_minute` filter), and your site stores: the date and time, the IP address (or the address with its last part removed, if you choose so), the country, the page address and title, the referring website, campaign tags (`utm_`), the browser's User-Agent text and the device details derived from it, screen size, language and time zone. A random session code lives in the browser's sessionStorage; no cookies are set. Visits older than the retention period you choose (365 days by default) are deleted automatically, and you can delete everything or download it at any time. An IP address can be personal data in some countries: if your visitors are protected by such a law, mention the counting in your privacy policy.

A visit from a logged-in member also stores that member's user id; the name and e-mail address are read from the WordPress user when you open the details. Words typed into the search box of your website (`?s=`) are stored with the visit. Visitor Stats never reads or stores a computer name, a file path or an e-mail address from the visitor's device: browsers do not allow websites to read them.

The country is read from your CDN or server first (Cloudflare, ArvanCloud, CloudFront and others) and from earlier visits of the same address. The option "Find the country online when the server does not know it" is **off by default**. Only when you switch it on, and only when the country cannot be read in another way, the visitor IP address is sent to an outside lookup service. See the section "External services" below for what is sent, to whom, and the terms and privacy policy of each service. Without that option no address leaves your server. Nothing else is sent to outside services.

The toolkit adds suggested text to the privacy policy guide (Settings > Privacy), and connects Visitor Stats to the WordPress tools Tools > Export Personal Data and Tools > Erase Personal Data: visits of a member are found by e-mail address. When a user account is deleted, the visits of that user stay as anonymous statistics.

The Contact tab sends nothing from your website; it only opens the e-mail program of the person who presses a button.

**فارسی**

افزونه چندمنظوره، ماژولار و قابل توسعه برای وردپرس. آمار کامل بازدید وب‌سایت (چند بازدید، از کجا، با چه آدرس IP و از کدام کشور، با چه دستگاه و چه مدلی)، کوتاه‌کننده‌ی لینک با دامنه‌ی خودتان، ساخت کیو آر کد برای لینک و متن، محافظ صفحه با تب و کلید روشن/خاموش جداگانه، تکثیر نوشته و برگه با یک کلیک و «ویرایش امن» صفحه‌های زنده از راه پیش‌نویس پنهان، آپلود مستقیم تصویر پروفایل بدون گراواتار، و رابط فارسی/انگلیسی که از تنظیمات خود افزونه عوض می‌شود.

== External services ==

Bani Honar Toolkit can connect to **one kind of outside service, and only if you switch it on**: an IP-address-to-country lookup used by the Visitor Stats module. The Link Shortener, QR Code Generator, Post Duplicator, Page Shield and Profile Pictures modules never contact an outside service: QR codes are drawn in the browser and profile pictures are stored on your own site.

**IP-address-to-country lookup (Visitor Stats, optional, off by default)**

* What it is and what it is for: a free web service that tells which country an IP address belongs to. It is used only to fill in the country of a visit when your CDN or server did not provide it and the address was not already known from an earlier visit.
* When it runs: only after an administrator ticks "Find the country online when the server does not know it" under Bani Honar Toolkit > Visitor Stats > Settings. Then one request is made for a visit from a public IP address whose country is unknown, and an hourly background task retries up to 20 recent addresses whose country is still unknown. If a service fails, lookups pause for ten minutes.
* What is sent: the visitor's IP address (inside the request address) and the plugin name and version in the User-Agent header, for example `BaniHonarToolkit/1.0.0`. Like any web request, the service also sees your server's IP address. No page address, cookie, name, e-mail or other visitor detail is sent. If you also tick "Hide the last part of every IP address", your site saves only the shortened address, but the full address is still used for that single lookup and is not stored.
* What comes back: a two-letter country code, which is saved with the visit.
* Which service: you choose it in the settings. "Automatic" tries ipwho.is first and then GeoJS.

Services used:

* ipwho.is (free endpoint of the IPWhois API, `https://ipwho.is/{ip}`): [Terms of Service](https://ipwhois.io/terms) and [Privacy Policy](https://ipwhois.io/privacy).
* GeoJS (`https://get.geojs.io/v1/ip/country/{ip}.json`): [Terms of Service](https://www.geojs.io/tos/) and [Privacy Policy](https://www.geojs.io/privacy/).

If you do not want any address to leave your server, leave the option off, or answer from a local database with the `bhtk_geo_lookup` filter, which skips the online services. If you use the option, mention it in your own privacy policy; the plugin adds suggested text to Settings > Privacy.

Links that open another website only when you click them (for example "Write in Gmail" on the Contact us tab, or the Google Search Console link in Visitor Stats) are ordinary links; the plugin itself sends nothing to those sites.

== Installation ==

1. In WordPress open Plugins > Add New > Upload Plugin, choose the zip file and press Install Now. (Or upload the `bani-honar-toolkit` folder to `/wp-content/plugins/`.)
2. Activate the plugin. If an older version is installed, WordPress offers to replace it; your data is kept.
3. Open **Bani Honar Toolkit** in the admin menu. Switch modules on or off on the Dashboard (Page Shield, Visitor Stats and Post Duplicator also have a switch on their own tab) and choose the language under Settings.

For the shortest links, use any permalink structure other than "Plain" in Settings > Permalinks.

== Frequently Asked Questions ==

= Is "Edit safely" different from just editing a live page? =

Yes. The page is copied to a hidden draft. You edit the draft with the normal editor (Elementor too) while visitors keep seeing the live page. When you press "Publish into the live page", the draft replaces the text and fields of the live page, which keeps its address, id, date and comments, and the old text is saved as a revision you can restore. Draft and live page are linked, so a second draft cannot be started for the same page.

= What if somebody changes the live page while I edit the draft? =

The plugin notices. When you publish, it tells you that the live page changed after the draft was made, and you can choose "Publish anyway" or go back and compare first.

= Why can I not duplicate WooCommerce products? =

Products, variations and orders keep prices, stock and relations in extra database tables that a general copier cannot copy correctly. Use the duplicate button that WooCommerce provides.

= Can Page Shield make my pages impossible to copy? =

No. Nothing can. A browser must receive the HTML, CSS and JavaScript of a page, so a determined person can always read them. Page Shield only stops casual copying. PHP code stays on the server and is never visible.

= Does Page Shield hurt SEO? =

No. Its options (right-click menu, developer shortcuts, image dragging, hidden WordPress version details) do not change the content that search engines read. Options that could hurt your ranking are deliberately not offered.

= How do I send a suggestion, a criticism or a problem report, or ask for a module? =

Open the Contact tab of the toolkit and press a button on the card you need: "Write in Gmail" opens a new message in Gmail, "Mail app" opens the e-mail program of your computer, and "Copy message" copies the whole text so you can send it from any e-mail service to it@banihonarpars.ir. Nothing is sent by your website.

= I found a security problem. Who do I tell? =

Please do not post it in public. Use "Report a security problem" on the Contact tab, or write to it@banihonarpars.ir. See SECURITY.md in the GitHub repository.

= Can I see the real IP address behind a proxy or CDN? =

By default the plugin reads the visitor address from the headers a CDN adds (Cloudflare, ArvanCloud and others) and falls back to the connection address. If your site can also be reached directly, without the CDN, a visitor can send a forged header, so the statistics of that visitor can be wrong. For a site that is not behind a CDN, choose "Only the direct connection (cannot be faked)" in Visitor Stats > Settings. Blocking checks both the header address and the connection address, so a forged header cannot be used to avoid a block.

= Why do I not see my own visits in Visitor Stats? =

Visits of logged-in administrators are not counted, so your own work does not change the numbers. Open your website in a private window or on your phone to test. You can change this under Visitor Stats > Settings.

= Does Visitor Stats work with caching plugins? =

Yes. The visit is sent by a small script after the page has loaded, so it is counted even when the page comes from a cache. If a visit never shows up, check that a security or cache plugin is not blocking `admin-ajax.php` for visitors.

= How is the country found? =

From your CDN or server first (Cloudflare, ArvanCloud and others add the country to every request), then from earlier visits of the same IP address, and finally, only if "Find the country online" is switched on (it is off by default), from a free lookup service. See the Privacy section. Developers can plug in their own database with the `bhtk_geo_lookup` filter.

= Can it show the name of the visitor's computer or their e-mail address? =

No. Browsers do not let websites read the computer name, folders or e-mail accounts of a visitor, and this plugin does not try. What it can show is the IP address, country, device type, brand and model (when the browser reports it), system, browser, screen size, language, time zone, and the name and e-mail of a visitor who is logged in to your website.

= Can I see what visitors searched for in Google? =

No website can: Google no longer passes the search words to the sites it sends visitors to. Use Google Search Console (free) for that. Visitor Stats shows the words typed into the search box of your own website.

= Can I block a troublemaker? =

Yes. Open the visit log, press Details on a visit, then "Block this IP address", or use the Blocked IPs tab. Remember that an IP address belongs to a network connection, not one device: people on the same Wi-Fi, and many mobile users, share an address. Prefer a temporary block. Blocking works for requests that reach WordPress; a page that a CDN or cache serves directly is not stopped. If you lock yourself out, add `define( 'BHTK_NO_IP_BLOCK', true );` to wp-config.php.

= Can it show the exact model of every phone? =

Android phones in Chrome report their model, for example Samsung Galaxy A54 5G. Apple does not let websites read the iPhone model, so only the family (for example "Apple iPhone 12 / 13 / 14") is shown, and some Android browsers hide the model as well.

= What happens to my visits if I switch Visitor Stats off? =

Counting stops, and the saved visits stay visible on its tab until you delete them. Deactivating the plugin never deletes data.

= Does the QR code generator send my text to another website? =

No. The code is drawn in your browser by a bundled script. Nothing is uploaded and nothing is stored.

= Do short links work with "Plain" permalinks? =

Yes, but they look like `example.com/?bhtk=Ab33`. Choose another permalink structure for links like `example.com/Ab33`.

= Can a short link replace one of my pages? =

No. A short link is used only when WordPress would otherwise show a "not found" page. When you create a custom code, the plugin also refuses codes that match an existing page or post.

= Are "Ab" and "ab" the same link? =

No. Codes are case-sensitive.

= Can I change the language of the plugin without changing my site language? =

Yes. Go to Bani Honar Toolkit > Settings > Plugin language and choose Persian or English.

= Where does the Persian translation come from? =

From the language pack that WordPress delivers from translate.wordpress.org, or from a Persian file in the plugin's `languages` folder when the plugin was installed from a package that includes it. If the Persian screens do not appear, copy `bani-honar-toolkit-fa_IR.mo` into `wp-content/languages/plugins/`; it survives plugin updates.

= What happens to my data when I delete the plugin? =

Nothing, unless you tick "Delete all toolkit data when the plugin is deleted" in Settings first.

= I used the older "BHAC" plugin. =

Your links and settings are copied automatically the first time Bani Honar Toolkit is activated. The old plugin is left untouched; deactivate it afterwards. Profile pictures uploaded with the old plugin keep working.

== Screenshots ==

1. The dashboard with one card and switch per module.
2. Creating a short link and the list of links with click counts.
3. The QR code generator.
4. Settings: plugin language, redirect type and more.
5. The profile picture card on the user profile screen.
6. Visitor Stats: overview with chart, countries, sources and devices.
7. Visitor Stats: the visit log with IP address, country and device name.
8. Page Shield on its own tab with an on/off switch.
9. The Contact tab.

== Changelog ==

= 1.0.0 =
First public release. Before this release the toolkit went through many rounds of testing and changes while it was used inside the agency, and everything that was built and tested in that time is part of 1.0.0:

* Link Shortener: short links on your own domain with random or custom case-sensitive codes, click counter, redirect type, optional path prefix, search, edit and delete. A short link never hides a real page.
* QR Code Generator: PNG and SVG download, colors, size and error correction, Persian text, drawn in the browser.
* Visitor Stats (off by default): overview with chart, sources (search engines, social networks, messengers, campaigns), countries, devices with brand and model, systems and browsers, visit log with filters, details and CSV download, website search words, logged-in members, Jalali or Gregorian dates, retention period, IP exclusion list, and blocking of IP addresses and IPv4 ranges (temporary or permanent, with protection against blocking yourself).
* Post Duplicator: duplicate posts, pages and custom content from the lists, the bulk actions menu, the admin bar and the editor, with a title pattern and copy options, and "Edit safely" - edit a live page through a hidden draft and publish it into the live page with one click (revision saved, conflict warning, address and comments kept). Elementor and block data are copied byte for byte.
* Page Shield (off by default): blocked right-click menu, developer shortcuts and image dragging, and hidden WordPress version details. Every option is off until you tick it, and none of them affects search engines; options that could lower the search ranking are deliberately not offered.
* Profile Pictures: upload a profile picture from the profile screen, no Gravatar needed. Pictures are checked and saved again as small squares.
* Contact tab: suggestions, criticism, problem reports, security reports and module requests for the IT team (it@banihonarpars.ir). Each card opens Gmail or your mail program with a ready text, and can copy the whole message; nothing is sent from your website.
* Persian (RTL) and English (LTR) screens with a language switch in the plugin settings, and a complete Persian translation.
* Privacy: the online country lookup is off by default, suggested privacy policy text, support for WordPress "Export Personal Data" and "Erase Personal Data", and anonymous statistics when a user is deleted.
* Security review: every action checks permission and nonce, database queries are prepared, all output is escaped, CSV files are protected against spreadsheet formulas, short links accept only http and https, uploaded pictures are re-saved. Two findings were fixed: blocking can no longer be avoided with a forged proxy header, and the visit counter has a per-minute ceiling against floods.
* Tested again and again: about 430 automated checks, which were repeated after every change, on a test stand-in for WordPress. Please test the plugin on your own site before using it in production.
* Migration: links and settings of the older "BHAC" plugin are copied automatically the first time the toolkit is activated.

== Upgrade Notice ==

= 1.0.0 =
First public release. If you test an earlier internal build, replace it with this one; your data is kept. The online country lookup in Visitor Stats is off until you switch it on.
