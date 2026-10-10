# Architecture Decisions

## ADR-001 — ElDokan-owned Customer API

Decision:

```text
Frontend → ElDokan API → WooCommerce
```

not:

```text
Frontend → WooCommerce API directly
```

Reason: backend replaceability and contract ownership.

## ADR-002 — Separate Customer / Seller / Admin APIs

Reason: different consumers, authorization, data exposure, and evolution.

## ADR-003 — Stable public product IDs

Canonical product detail:

```text
/products/prd_...
```

Reason: slug/SEO values can change and WordPress canonical redirects can conflict.

## ADR-004 — Slug lookup is explicit query lookup

```text
/products/lookup?slug=...
```

Reason: pretty routes ending in a real product slug were redirected by WordPress.

## ADR-005 — Category filter source is explicit

Response source modes:

```text
configured
inherited
inferred_from_products
none
unavailable
```

Reason: developers should know why a filter exists.

## ADR-006 — Public catalog excludes standalone variation children

Variations belong inside parent product detail.

## ADR-007 — Performance cache is part of the adapter

Short-lived catalog caching is allowed.

Checkout must revalidate authoritative price/stock.

## ADR-008 — Documentation lives with source

README, handoff, decisions, performance, testing, and release history travel inside the plugin so context is not dependent on one person or one chat.


## ADR-009 — Explicit seller ownership

Only `_eldokan_seller_user_id` establishes public seller ownership. Unsafe author and generic vendor fallbacks are rejected.

## ADR-010 — Attribute filter algebra

Public `att_*`/`atr_*` IDs are allowlisted per category. OR applies inside a group and AND applies between groups.

## ADR-011 — Translation identity

WPML translations currently have distinct public IDs. Clients keep IDs opaque; migrations preserve every issued ID.

## ADR-012 — HTTP caching disabled for release gate

Application cache remains active and diagnostic. Shared/browser caching stays `private, no-store` until edge behavior is verified.

## ADR-013 — Translation provider isolation

The public contract accepts `lang=ar|en` and never exposes WPML concepts. WPML is the current provider behind an internal adapter; a native fallback keeps the API operational when WPML is unavailable. `Content-Language` reports the language actually returned.

## ADR-014 — No invented customer state

Wishlist, price tracking and seller-follow fields are excluded until an authenticated customer session can produce authoritative values.
