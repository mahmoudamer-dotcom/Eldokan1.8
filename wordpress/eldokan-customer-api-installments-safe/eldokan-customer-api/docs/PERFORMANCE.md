# Performance Policy

Performance is part of API correctness.

The historic Seller API has shown very slow responses, therefore the Customer API must not repeat patterns such as full-catalog scans, unbounded queries, or loading hundreds of objects per request.

## Current cache TTLs

```text
/home                          60 seconds
/categories                   300 seconds
/categories/{slug}            300 seconds
/categories/{slug}/filters    300 seconds
/products                     30 seconds
/products/{id}                30 seconds
/products/lookup              30 seconds
/search/suggestions           30 seconds
```

These are application read-model caches, not checkout authority.

## Why short product TTLs?

Product responses include price and stock.

Browse data may tolerate a very short delay, but checkout must always revalidate authoritative values.

## Cache diagnostics

Response headers:

```text
X-ElDokan-Cache: HIT | MISS | BYPASS
X-ElDokan-Cache-TTL: seconds
Server-Timing: eldokan;dur=...
X-ElDokan-Request-ID
```

Administrator-only cache bypass:

```text
?no_cache=1
```

Example:

```text
GET /products?per_page=24&no_cache=1
```

A normal public visitor cannot force cache bypass.

## How to measure

Use Chrome DevTools → Network.

Record:

```text
Status
TTFB
Total time
Transferred size
Content size
X-ElDokan-Cache
Server-Timing
Request ID
```

Test each endpoint twice:

```text
first request = likely MISS
second request = expected HIT
```

## Performance targets

These are engineering goals, not hosting guarantees:

```text
Cached lightweight endpoints:
preferably well below 500 ms end-to-end

Cached catalog endpoints:
preferably below 1 second end-to-end

Uncached catalog endpoints:
should normally remain within low single-digit seconds

15–20 seconds:
unacceptable; investigate immediately
```

## Known expensive operations to watch

### Product list

Current implementation still uses:

```text
WP_Query IDs
→ wc_get_product() for returned page items
→ brand/seller projections
```

This is bounded by `per_page`, which is good, but cache-miss cost must still be measured.

If cache misses remain slow, the next optimization candidate is WooCommerce's indexed product lookup data rather than postmeta sorting/filtering.

### Home

`/home` contains multiple product sections.

Version 0.1.3 limits each initial product carousel to 8 items.

If uncached `/home` is still heavy, the preferred next design is:

```text
/home → section configuration + initial critical data
frontend lazy-loads lower sections
```

rather than endlessly increasing server work in one request.

### Category filters

Do not load hundreds of `WC_Product` objects just to discover taxonomy filters.

Current implementation uses taxonomy relationship queries.

## Anti-patterns prohibited in public endpoints

```text
posts_per_page = -1
fetch all then paginate in PHP
scan every order/product on every request
one HTTP self-call per item
unbounded wc_get_product loops
N+1 queries without a bounded page
long cache TTL for checkout/order state
```

## SiteGround / hosting

The contract must not rely on SiteGround-specific APIs.

WordPress transient caching works across compatible WordPress hosting.

If a persistent object cache is available later, WordPress can benefit without changing the public frontend contract.


## 0.1.4 cache diagnostics

When repeated identical requests remain `MISS`, inspect:

```text
X-ElDokan-Cache-Generation
X-ElDokan-Cache-Key
X-ElDokan-Cache-Backend
X-ElDokan-Cache-Store
```

Interpretation:

```text
Same key + same generation + store=stored + next request MISS
→ cache backend is not reusing the stored value.

Different key
→ request identity is changing.

Different generation
→ an invalidation hook is firing between requests.

store=write_failed
→ WordPress cache write failed.

store=write_ok_readback_failed
→ cache backend reported a write but immediate retrieval failed.
```
