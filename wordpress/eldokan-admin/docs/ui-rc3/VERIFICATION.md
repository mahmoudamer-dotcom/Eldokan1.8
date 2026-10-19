# RC3 Verification and Outstanding Acceptance

## Source-backed, locally run

- PHP 11 files: `php -l` for every plugin PHP source (PASS).
- JavaScript: `node --check` for all 3 JS sources (PASS).
- Role-filtered menu mock: six sidebar links with specified order; Products shows Home and Stories only with applicable capabilities; Settings exposes Checkout, Team and WCFM index to authorized users; Orders does not show duplicate navigation (PASS).
- Arabic MO catalog: all 637 original translated entries preserved byte-semantically; 15 additional labels translated (PASS).
- No database schema or REST route source modification; backend handlers preserved outside Dashboard readonly-link and WCFM notice wording (verified by file diff).

## Not tested; must be accepted on WordPress Staging

1. All six sidebar screens open with administrator; hidden subpages open via context links, including native Stories list/add/edit, Checkout Setup and Home Management.
2. Repeat as Manager/Staff/Viewer; confirm no privilege escalation by direct URL and no links to unauthorized pages.
3. WPML/WCFM/WoodMart notices render **outside** hero, above content and can be dismissed; no error/warning is silently hidden.
4. Save/reload one *test* banner and story; verify image and translation relationships and Customer API read delivery.
5. Inspect payment/shipping settings **read-only first**; safe sandbox write test separately with rollback.
6. Check seller registration, product moderation, order status and report filters with authorized test data; capture browser console and PHP logs.
7. Compare report and Dashboard totals to WooCommerce authoritative order data: see audit caveats.
8. Test desktop/mobile, Arabic/English, navigation, labels and direct links.

## Unsupported confirmation

WPVibe live connector could not inspect live WordPress: Cloudflare returned a security challenge, so online plugin status, installed WCFM components, live database values and staging browser acceptance remain unverified. Never describe these as passing.
