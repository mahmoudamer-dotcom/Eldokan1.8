## Customer API 0.10.0 commerce extensions

See COMMERCE-SETUP.md. New operations are additive; native payment and existing order authorization remain authoritative. PHP runtime acceptance on WordPress is still required.

> Adapter 0.9.0: see [REVIEWS-SETUP.md](REVIEWS-SETUP.md) for installation, reviews and seller products.

# ElDokan Customer API 0.8.0 — Contract v1

**Current release:** 0.8.0. **State:** Phase 2C backend code-complete; real staging acceptance pending. Client 0.6.0 and Developer Portal 1.8.0 downstream packages are complete. This installable WordPress package contains runtime files and release documentation; recovery/checkpoint evidence is distributed separately.

Address Book, authoritative checkout/quote, durable attempts, ONE native Woo Order, COD, native Paymob 4.1.15 hosted initiation, authenticated Orders, exact Guest Order capability, SAME-Order payment recovery/generation-fenced retry and optional private Customer phone are implemented. Browser return is not payment proof. The native Accept TRANSACTION POST callback remains payment authority; actual merchant compatibility and verified status mappings require staging.

Read START-HERE.md and docs/API-CONTRACT.md for current installation and contract guidance. Historical development milestones are labeled in CHANGELOG.md and docs/releases/; they do not limit this release's implemented scope.

## Purpose

The customer frontend consumes an ElDokan-owned JSON contract. WordPress and WooCommerce remain the current source of truth but their table names, meta keys, classes, taxonomy names and raw IDs are adapter details.

Current base path:

```text
https://www.eldokan.com/wp-json/eldokan-customer/v1
```

`https://api.eldokan.com/v1/customer` remains a target hostname until its proxy/Worker is deployed and tested.

## Implemented endpoints

```text
GET /health
POST /auth/register
POST /auth/login
GET /auth/session
POST /auth/logout
GET /me
POST /me
PUT /me
PATCH /me
GET /me/addresses
POST /me/addresses
PATCH /me/addresses/{address_id}
DELETE /me/addresses/{address_id}
GET /wishlist
POST /wishlist/items
DELETE /wishlist/items/{product_id}
GET /cart
POST /cart/items
POST /cart/items/{item_id}
PUT /cart/items/{item_id}
PATCH /cart/items/{item_id}
DELETE /cart/items/{item_id}
GET /checkout
POST /checkout/quote
POST /checkout/attempts
POST /checkout/orders
GET /orders
GET /orders/{order_id}
POST /orders/{order_id}/payment
GET /home
GET /categories
GET /categories/{slug}
GET /categories/{slug}/filters
GET /brands
GET /tags
GET /sellers/{seller_id}
GET /products
GET /products/lookup
GET /products/{product_id}
GET /search/suggestions
```

## Catalog contract rules

- OpenAPI defines ProductCard, ProductDetail, Money, Stock, SellerPublic, Category, Attribute, Variation, Pagination, HomeSection and error envelopes.
- Public IDs remain strings such as `prd_21417`, `var_12`, `cat_4`, `att_2` and `atr_31`. Clients must not parse the numeric suffix. Migration must preserve these values with a mapping table.
- `GET /products?category=...&attributes=att_1:atr_2,atr_3;att_4:atr_5` performs OR within one attribute and AND across attributes. IDs must come from that category's `/filters` response.
- Attribute taxonomy names and `attribute_pa_*` keys never appear in the public variation contract.
- Only `_eldokan_seller_user_id` establishes seller ownership. Unknown or invalid ownership returns `seller: null`.
- Catalog, search and direct detail visibility are explicit. Password-protected and unpublished products never appear. A WooCommerce hidden product may still be opened through its explicit public ID or slug.
- Variable parent pricing includes `min_price` and `max_price`. Query `min_price` and `max_price` use major currency units.
- ProductDetail returns `image` for the primary image and `images` for the primary image plus the full gallery.
- Brand projections return the existing managed taxonomy Thumbnail as normalized `image`, or `null` when unavailable.

## Cart, customer sessions and catalog rules

- Guest and customer Carts are server-owned; the guest identifier is an opaque HttpOnly cookie.
- Cart mutations use session-bound CSRF and credentialed requests. No Cart state or secrets belong in localStorage.
- `prd_*`, `var_*` and `cit_*` are the only public item identifiers; WooCommerce IDs and cart keys remain private.
- Prices, stock and seller ownership are recalculated server-side. Cart does not reserve stock.
- Guest Cart merges once on login/register; language switching never mutates stored membership.
- Checkout, Orders, COD, hosted Paymob and secure Guest Order access are implemented; Reviews and Seller Ratings remain separate.
- `lang=ar|en` is part of the cache key. Arabic category slugs are accepted. With WPML, translations have their own public product IDs; requesting a known ID with another language resolves its translation when one exists.
- Translation behavior is isolated behind an ElDokan adapter. WPML is the current bilingual provider; without it, the API safely returns the native site language and reports the effective language through `Content-Language`.
- Customer sessions use WordPress-native authentication cookies hardened as Secure/HttpOnly/SameSite=Lax on HTTPS. Authenticated writes additionally require the session-bound `X-ElDokan-CSRF` value returned by register/login/session.
- `GET /auth/session` restores an existing cookie session and returns a fresh CSRF token. Login errors are generic and throttled.
- Customer IDs are random stable `cus_*` identifiers and never reveal the WordPress numeric user ID.
- Customer exposes `id,first_name,last_name,display_name,email,phone`; phone is always string. POST/PUT/PATCH `/me` accept names/display name and optional phone. Empty string clears; omission preserves; null is invalid. Profile phone is independent of Address Book and historical Order phone.
- Wishlist is authenticated, customer-private and idempotent. It stores parent products and returns current ProductCard projections only for customer-visible products.
- `/home` returns one ordered section array. Managed `hero_slider` and `banner_grid` sections contain only enabled, in-schedule records for the effective language; empty managed sections are omitted.
- Home editorial records expose opaque `hsl_*` / `hbn_*` IDs, normalized images and safe CTA values. WordPress attachment/post IDs are not contract fields.
- Namespace errors use the standard `{ success:false, error, meta }` envelope.
- Category hierarchy uses the existing `GET /categories?parent={slug}` and `CategoryDetail.children` capabilities.
- `GET /brands` and `GET /tags` return localized paginated public term collections.
- `GET /products` accepts brand and tag slugs. `category={slug}&sort=best_selling` is the category best-seller query; `sort=newest` is the catalog new-arrival query.
- `GET /sellers/{seller_id}` uses the stable opaque `sel_*` ID from product seller data. It is not an authenticated Seller account.

## Cache diagnostics

Application cache diagnostics remain available:

```text
X-ElDokan-API-Version
X-ElDokan-Request-ID
X-ElDokan-Cache
X-ElDokan-Cache-TTL
X-ElDokan-Cache-Generation
X-ElDokan-Cache-Key
X-ElDokan-Cache-Backend
X-ElDokan-Cache-Store
Content-Language
Server-Timing
```

Cache keys use only effective parameters, normalized defaults, language and currency. Responses send `Cache-Control: private, no-store` until edge caching is designed and verified.

## Start and handoff

Read in this order:

```text
START-HERE.md
docs/HANDOFF.md
docs/API-CONTRACT.md
docs/ENDPOINT-STATUS.md
docs/TESTING.md
docs/FILE-MAP.md
CHANGELOG.md
docs/releases/V0.3.0-HOME-CONTENT.md
docs/releases/V0.5.0-PHASE-2A-IDENTITY-ACCOUNT-WISHLIST.md
```

## Out of scope

Authenticated Seller account, Seller Fulfillment mutation compatibility, Reviews, Seller Ratings, refund initiation and legacy Order migration remain separate. Customer frontend implementation is delivered as a handoff, not implemented here.
