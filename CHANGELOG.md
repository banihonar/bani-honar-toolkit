# Changelog

## 1.0.0 — first public release

Before this release the toolkit went through many rounds of testing and changes while it was used inside the agency. Everything built and tested in that time is part of 1.0.0.

- **Link Shortener** — short links on your own domain with random or custom case-sensitive codes, click counter, redirect type, optional path prefix; a short link never hides a real page.
- **QR Code Generator** — PNG and SVG download, colors, size, error correction, Persian text; drawn in the browser.
- **Visitor Stats** (off by default) — overview with chart, sources (search engines, social networks, messengers, campaigns), countries, devices with brand and model, systems, browsers, visit log with filters, details and CSV download, website search words, logged-in members, Jalali or Gregorian dates, retention period, IP exclusion list, blocking of IP addresses and IPv4 ranges (temporary or permanent, protected against blocking yourself).
- **Post Duplicator** — duplicate posts, pages and custom content from the lists, bulk actions, the admin bar and the editor; title pattern and copy options; **Edit safely**: edit a live page through a hidden draft and publish it into the live page with one click (revision saved, conflict warning, address and comments kept). Elementor and block data are copied byte for byte.
- **Page Shield** (off by default) — blocked right-click menu, developer shortcuts and image dragging, and hidden WordPress version details. Every option is off until ticked, and none of them affects search engines; options that could lower the search ranking are deliberately not offered.
- **Profile Pictures** — upload from the profile screen, no Gravatar; pictures are checked and saved again as small squares.
- **Contact tab** — suggestions, criticism, problem reports, security reports and module requests to it@banihonarpars.ir. Each card opens Gmail in a new tab or the e-mail program of the computer with a ready text, and can copy the whole message; nothing is sent from the website.
- Persian (RTL) and English (LTR) screens with a language switch; complete Persian translation.
- **Privacy** — online country lookup off by default, suggested privacy policy text, WordPress personal data export and erase, anonymous statistics when a user is deleted.
- **Security review** — capability and nonce checks on every action, prepared SQL, escaped output, CSV formula protection, http/https-only short links, re-saved uploads. Two findings were fixed: IP blocking can no longer be avoided with a forged proxy header; the visit counter has a per-minute ceiling against floods.
- About 430 automated checks, repeated after every change, on a stand-in for WordPress. Test on your own site before production use.
- Migration of links and settings from the older "BHAC" plugin.
