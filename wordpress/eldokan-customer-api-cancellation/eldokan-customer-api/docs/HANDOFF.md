# Engineering Handoff — ElDokan Customer API 0.8.0

## Current state

Backend 0.8.0 / Contract v1 completes Phase 2C: Address Book, Checkout/Quote, durable attempts, one native Woo Order, COD, hosted Paymob, Orders read, secure Guest access, payment recovery/retry and private optional Customer phone. Client 0.6.0 / Portal 1.8.0 / OpenAPI / Mahmoud handoff are complete. Real staging remains required; Seller Fulfillment mutation compatibility remains separate.

The current deployable adapter is the WordPress plugin in this package. `api.eldokan.com/v1/customer` is the planned public Customer API base and must not be treated as live until the proxy/Worker is deployed and tested.

## Contract decisions

- Public IDs remain `prd_*`, `var_*`, `cat_*`, `sel_*`, `brd_*`, `att_*`, and `atr_*` strings. They are opaque. WPML translations currently have distinct IDs.
- Product attribute filtering is server-side. OR applies inside one attribute; AND applies between attributes.
- Public attribute slugs omit Woo taxonomy prefixes. Variation selections contain `attribute_id`, `attribute_slug`, `option_id`, and `option_slug`.
- Variable parents expose price/min/max. Published visible variations expose an explicit `purchasable` boolean.
- `_eldokan_seller_user_id`, written by the existing Seller Dashboard API, is the only ownership source. No post-author or generic vendor-meta fallback remains.
- Password-protected and unpublished products are never public. Catalog/search visibility follows the route context. Hidden products remain directly linkable by deliberate policy.
- HTTP shared caching is disabled via `private, no-store`; application transient diagnostics remain unchanged.
- Home returns only populated/configured sections in this release.
- Customer sessions use WordPress-native cookies, session-bound CSRF, throttling and restricted credentialed CORS; no JWT/localStorage credential is introduced.
- Customer IDs are random stable `cus_*` values. `/me` never exposes WordPress IDs, roles, capabilities or internal metadata.
- Wishlist is private, idempotent and returns ProductCard data only after existing visibility validation.
- WPML is isolated behind a translation adapter. Native fallback remains safe when WPML is unavailable, and `Content-Language` states the effective response language.
- Public health exposes service/version/status/readiness only; platform versions stay private.
- ProductDetail now returns the contract-required primary `image`; `images` remains the complete ordered image collection.
- Category children remain available through `/categories?parent={slug}` and `CategoryDetail.children`.
- Brand and Tag collection responses are paginated, localized and use opaque IDs.
- Brand projections use the existing taxonomy Thumbnail and return normalized nullable `image` without attachment IDs.
- Guest Cart ownership uses an opaque HttpOnly cookie and server-side transient; customer Cart uses private user storage.
- Login/register merge a valid guest Cart once. Cart price, stock and seller projections are recalculated server-side and inventory is not reserved.
- Cart membership stores source product/variation IDs internally and remains language-independent; only `prd_*`, `var_*` and `cit_*` are public.
- `/products?category={slug}&sort=best_selling` is the canonical category best-seller query; `sort=newest` is the canonical new-arrival query.
- `/sellers/{seller_id}` uses the stable `sel_*` ID and is public storefront identity only. It is separate from authenticated Seller Portal/Seller API functionality.

## Source relationships reviewed

- Seller Dashboard API writes `_eldokan_seller_user_id` during product ownership assignment.
- ElDokan Admin stores category attribute rules in `_eldokan_attribute_mode` and `_eldokan_attribute_ids`.
- Customer API reads both contracts but does not require WCFM output shapes.
- Developer Portal 1.8.0 contains the synchronized Contract v1 schemas and 32 paths / 40 operations for backend 0.8.0; use Client 0.6.0.

## Deployment gate

Completed backend runtime/source verification and its test harness are retained separately from the production ZIP. Downstream verification is complete; OpenAPI and Portal verification lives in the separate Portal package. Runtime files have not changed during this documentation cleanup. WordPress/WooCommerce/WPML behavior still requires live acceptance because this work does not deploy to a WordPress environment.

Highest-risk checks:

1. Count and repair legacy products missing `_eldokan_seller_user_id`.
2. Confirm accepted seller roles are `eldokan_owner`, `wcfm_vendor`, `seller`, or `vendor` in production.
3. Verify Arabic slugs and WPML translation mapping using real data.
4. Compare attribute-filter totals and pagination with the product set.
5. Verify Woo variable price, visibility and backorder behavior.

## Phase 2 status

Identity/Auth, Account + phone, Address Book, Wishlist, Guest/customer Cart, Checkout/Quote, attempts/placement, Orders and hosted payment/recovery are implemented. Product Reviews, Seller Ratings and Seller Fulfillment mutation compatibility remain separate.

Public `GET /sellers/{seller_id}` is customer-facing storefront data. Authenticated Seller account management belongs to the separate Seller Portal/Seller API boundary and must not be added to this public resource.

Guest Cart ownership, merge and server-authoritative revalidation are implemented. Retain SAME chk_*/ord_* for uncertain placement/payment recovery; native callback is payment authority and browser return is not proof. Refer to API-CONTRACT.md for exact account/Guest CSRF and status mapping rules.
