# 0.10.0

Password recovery, private order return requests and staff replies, recorded refund display, stock/price email alerts, saved selection profiles, review images/helpfulness/reporting, search aliases and diagnostics, catalog sitemap and bundled home category shelves. Bosta integration deferred. Requires existing customer authentication and checkout setup.

# 0.9.1

Accept non-empty reviews shorter than ten characters, keeping the 2000-character maximum.

# 0.9.0

Adds customer product and seller reviews, moderation, ownership/CSRF checks, paginated seller products and nonzero-sales category ranking. Builds on 0.8.6 checkout and cancellation changes. See REVIEWS-SETUP.md.

# Customer API changelog

## 0.8.0 — current Phase 2C backend completion / Contract v1

- Address Book and authoritative Checkout/Quote.
- Durable owner-bound checkout attempts, ONE native Woo Order and COD.
- Native Paymob 4.1.15 hosted flow after COMMIT/lock release; safe Accept TRANSACTION POST profile.
- Authenticated Orders read, exact Guest Order capability and dedicated Guest payment CSRF.
- SAME-Order payment recovery/retry with observed generation fencing.
- Optional private Customer phone; canonical empty string and independent address/Order phone.
- Client 0.6.0, Portal 1.8.0, OpenAPI and Mahmoud handoff complete. Real staging acceptance pending; no runtime/version change in the final documentation cleanup.

## Historical development entries — superseded checkpoint scope

Everything below records earlier development checkpoints/releases. Paths to separate checkpoint evidence are historical references, not current install/read/run instructions and not included in this production ZIP. Current guidance is START-HERE.md and docs/API-CONTRACT.md.

# Checkpoint A4F — final backend contract, Customer API 0.8.0 / v1

A4F adds optional private account phone through canonical Woo billing_phone. Registration stays optional; account/session returns a consistent empty string for absent phone. All accepted commerce/payment/Guest behavior is preserved. Read `checkpoint-a4f/CHECKPOINT-A4F-PUBLIC-CONTRACT.md` and the A4F work state/verification/staging reports. Historical A4/A3H notes below remain evidence; A4F is the final backend contract handoff. Real staging acceptance is pending.

# Checkpoint A4 — Customer API 0.8.0

A4 adds native hosted Paymob, private Phase 2C Orders and order-bound Guest access. Read `checkpoint-a4/CHECKPOINT-A4-WORK-STATE.md`, `checkpoint-a4/CHECKPOINT-A4-PUBLIC-CONTRACT.md` and the staging checklist first. A3H remains the sole implementation baseline; the accepted history follows below.

# Changelog

## 0.7.0 — Phase 2C Checkpoint A3H hardening

- Added normalized bounded optional order_notes through native customer-note checkout data and logical idempotency.
- Added nullable schema-v2 recovery_json, durable owner/Cart markers and complete post-COD business/physical-row proof.
- Added independent read-only inspection and ledger-only reconciliation with conditional trusted WP-CLI commands; no lifecycle replay or force-create path.
- Replaced the blanket blog-table engine rule with legacy/HPOS/compatibility participants and an actual-write/implicit-commit SQL guard.
- Preserved global 10-second placement acquisition and added external-network/provider invariants.
- Added focused serial/restart/concurrent-operator tests, preservation guards and staging procedures. No A4 or other application modifications.

## 0.7.0 partial — Phase 2C Checkpoint A3

- Added two CSRF/private placement mutations: prepare durable chk_* and place COD Order.
- Added schema v1 attempt ledger, per-Seller Fulfillments and durable Cart consumption receipts, with database uniqueness and bounded MySQL named locks.
- Reused every A2 revalidation/calculation boundary; compared preparation fingerprints before native placement.
- Native WC_Checkout creates one Woo Order and fires native line-item/order lifecycle hooks; external fee metadata stays owned by existing listeners.
- Native COD processes status/stock; persisted public result recovers retries after Cart consumption/response loss.
- Ambiguous interrupted processing attempts fail closed for store-side recovery. No blindly repeated creation/payment hooks.
- Added unchanged upstream Checkout/COD/stock fixtures, local hook observer, parallel-process race/lost-response/kill recovery tests and all available regressions. Installed WordPress/MySQL/HPOS/Admin/Seller integration remains staging acceptance.
- No Paymob initiation, Order read API, guest retrieval, child Orders, legacy migrations or Client/Portal/Seller API/Frontend changes.

## 0.7.0 partial — Phase 2C Checkpoint A2

- Added private guest/customer GET checkout and CSRF-protected POST checkout quote.
- Added canonical Seller/current Cart/address ownership revalidation without pruning invalid selections.
- Native WooCommerce calculates subtotal, shipping, tax, fees and total in an isolated request context.
- Safe opaque shipping choices and COD/Paymob availability only; no Orders, reservation or payment initiation.
- A1 schema and Address Book behavior retained; Client/Portal/Admin/Seller API/Frontend untouched.
- Local verification passes; real WordPress/shipping/gateway acceptance remains pending.

## 0.7.0 — Phase 2C Checkpoint A1 (Address Book only)

- Added authenticated Egypt Address Book list/create/PATCH/delete with stable 256-bit `adr_*` IDs.
- Added automatic schema v1 install/upgrade and customer-scoped atomic compare-and-swap writes.
- Preserved Phase 2B behavior, registration optionality, native cookie/CSRF protection and credentialed CORS.
- Checkout, Orders, Seller Fulfillment, Client and Portal changes are deferred to later checkpoints. This source checkpoint is not a complete Phase 2C release.

## 0.6.0 — Phase 2B Guest Session and Cart

- Added server-owned guest/customer Cart with HttpOnly guest ownership and session-bound CSRF.
- Added GET/add/update/remove Cart routes with public `cit_*`, `prd_*` and `var_*` identifiers only.
- Added authoritative price, stock and purchasability refresh without inventory reservation.
- Added idempotent duplicate handling and one-time guest Cart merge on login/register.
- Kept membership language-independent and preserved all Phase 2A/Catalog/Home behavior.
- Checkout, Orders, Reviews and Seller Ratings remain unavailable.

## 0.5.2 — Brand Thumbnail Projection

- Added the existing managed Brand taxonomy Thumbnail to the normalized public Brand projection as `image` or `null`.
- Reused one projection across Brands, Home brand grids and product Brand data without exposing attachment IDs or term metadata.
- Preserved Customer API Contract v1, routes, cache invalidation and all accepted Phase 2A behavior.

## 0.5.1 — Wishlist Language-Persistence Fix

- Prevented a missing requested-language product translation from permanently removing a valid Wishlist membership.
- Preserved language-independent storage, idempotency, customer isolation, visibility rules, opaque IDs and Contract v1.

## 0.5.0 — Phase 2A Customer Identity, Account and Wishlist

- Added WordPress-native customer register/login/session/logout with hardened session cookies, CSRF, generic login failures and throttling.
- Added random stable `cus_*` customer IDs without exposing WordPress user IDs.
- Added minimal private `GET/PATCH /me` projection.
- Added authenticated idempotent Wishlist endpoints returning ProductCard-compatible data for visible parent products.
- Kept Seller Account, Cart, Checkout, Orders, Reviews and Seller Ratings unavailable.
- Preserved Contract v1 and all accepted 0.4.0 Home/Catalog behavior.

## 0.4.0 — Catalog Frontend Phase 1

- Added paginated, localized `GET /brands` and `GET /tags` collections.
- Added `tag` filtering to the existing `GET /products` catalog query.
- Added `GET /sellers/{seller_id}` for the existing public seller/store projection, using stable `sel_*` IDs.
- Confirmed subcategories through `GET /categories?parent={slug}` and `CategoryDetail.children`.
- Confirmed category-scoped best sellers through `category={slug}&sort=best_selling` and new arrivals through `sort=newest`.
- Preserved Customer API Contract v1, existing routes, Home behavior, public IDs, visibility, cache diagnostics and error envelopes.

## 0.3.1 — Home Release Hardening

- Made Home schedule evaluation timezone-consistent by parsing stored schedule values with the WordPress site timezone and comparing them with `current_datetime()`.
- Preserved the existing local stored datetime format and public `GET /home` response contract.
- Split CTA sanitization so category targets remain slugs while search targets remain human search text, including spaces and Unicode/Arabic text.
- No route, schema, cache strategy or permission changes.

## 0.3.0 — Home Content Management v1

- Extended `GET /home` with ordered `hero_slider` and `banner_grid` sections.
- Added strict slide, promo banner and CTA projections with normalized image URLs.
- Enforced language, enablement and optional schedules for editorial records.
- Added Home-only cache generation invalidation while preserving diagnostics.
- Kept all non-Home endpoint behavior unchanged.

## 0.2.3 — Documentation Cleanup

- Corrected `START-HERE.md` to use the current release description.
- Marked legacy `0.1.x` study-note sections as `HISTORICAL / SUPERSEDED`.
- Made the current slug lookup explicit as `/products/lookup?slug=...` and labeled `/products/by-slug/...` historical.
- Reconfirmed `_eldokan_seller_user_id` as the only seller ownership source in current study notes.
- Synchronized documentation, handoff, status and Developer Portal 1.3.3 metadata.
- No runtime logic, endpoint, schema or response-shape changes.

## 0.2.2 — Product Detail Consistency

- Added the primary product `image` to the ProductDetail runtime response to match the existing public contract.
- Kept `images` as the primary image plus the full gallery.
- Added a regression verification for both ProductDetail image fields.
- Updated Arabic study notes to document `_eldokan_seller_user_id` as the only ownership source.
- Corrected testing documentation to distinguish the Customer API verifier from the Developer Portal/OpenAPI verifier.
- Synchronized Developer Portal 1.3.2 and current release documentation.

## 0.2.1 — Release Hardening Patch

- Removed duplicate `attributes` parameters from the canonical OpenAPI contract and added a uniqueness regression test.
- Removed non-functional `customer_state` and seller `followed` fields until authenticated customer context exists.
- Added a replaceable translation layer with WPML and native fallback adapters; WPML calls and table knowledge are now isolated.
- Added truthful `Content-Language` responses and language provider/effective-language cache identity.
- Minimized the public health response and removed WordPress/WooCommerce version disclosure.
- Updated release documentation and synchronized Developer Portal 1.3.1.

## 0.2.0 — Catalog Contract Hardening

- Added production domain schemas and endpoint-specific responses to OpenAPI.
- Implemented category-scoped server-side attribute filtering with public IDs.
- Removed Woo `attribute_pa_*` leakage from variation output and added purchasability.
- Added parent variable-product `min_price` and `max_price`.
- Made `_eldokan_seller_user_id` the sole seller ownership source; removed unsafe author/vendor fallbacks.
- Unified product visibility and password protection across catalog, search and direct detail.
- Added Arabic slug routing, localized Home titles and explicit WPML translation behavior.
- Normalized cache keys to effective parameters, language and currency; preserved diagnostics.
- Added invalidation for ownership/category rule metadata and seller roles.
- Removed empty Home placeholders and added namespace-scoped error normalization.
- Added `Cache-Control: private, no-store` pending tested edge-cache design.
- Added release verifier, updated handoff/testing/status docs and synchronized Developer Portal 1.3.0.

## 0.1.4 — 2026-09-21

### Diagnostics
- Added cache generation, key fingerprint, backend, and store-result headers.
- Added immediate transient read-back verification after cache writes.
- Kept the public JSON response contract unchanged.
- Added `START-HERE.md` for one-minute project handoff.


All notable changes to ElDokan Customer API are recorded here.

## 0.1.3 — 2026-09-21

### Performance
- Added a dedicated public read-model cache layer.
- Added endpoint-specific TTL policy.
- Added cache generation invalidation after relevant catalog, taxonomy, stock, and seller-display changes.
- Added `X-ElDokan-Cache`, `X-ElDokan-Cache-TTL`, `Server-Timing`, version, and request-ID response headers.
- Added administrator-only `no_cache=1` diagnostics.
- Reduced initial homepage product carousel payload from 12 to 8 items per product section.

### Documentation
- Expanded README into a full project entry point.
- Added handoff, architecture, file map, API contract, data mapping, performance, security, testing, development, decisions, known issues, endpoint status, naming conventions, and release checklist documentation.
- Extended Arabic study notes so project knowledge can survive developer/model/session changes.

### Principle
- Performance is now treated as part of API correctness.
- A 15–20 second response is not accepted as normal behavior.

## 0.1.2 — 2026-09-21

### Changed
- Replaced `GET /products/by-slug/{slug}` with `GET /products/lookup?slug={slug}` to avoid WordPress canonical redirects.
- Category filter responses include explicit source mode.

### Fixed
- Unconfigured categories fall back to discovering taxonomy attributes from products.
- Category filter options are restricted to values actually used in the category tree.
- Standalone variation children are excluded from catalog/search responses.
- Non-breaking spaces in public product HTML are normalized.

### Performance
- Category attribute discovery uses taxonomy relationships rather than loading hundreds of product objects.

## 0.1.1 — 2026-09-21

### Changed
- Product detail primary route uses stable ElDokan product ID.
- Stock enums normalized.

### Fixed
- HTML entities and formatted-money non-breaking-space leakage cleaned.
- WordPress editor metadata and shortcodes cleaned from public output.

## 0.1.0 — 2026-09-21

### Added
- Health.
- Home.
- Categories.
- Category filters.
- Products.
- Product detail.
- Search suggestions.
- WPML-aware language handling.
- Seller projection.
- Stable response envelope and pagination metadata.
