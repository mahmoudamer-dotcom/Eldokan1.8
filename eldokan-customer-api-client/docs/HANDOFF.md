# Engineering Handoff — ElDokan Customer API Client 0.4.1

## Source of truth

- API Contract: v1
- Customer API Adapter: 0.5.2
- Developer Portal: 1.6.2
- Client: 0.4.1
- Bundled OpenAPI schemas: 60
- Bundled API paths: 20

## Current live acceptance already observed

Passed live checks:
- `/health` and adapter version/readiness.
- `/products` listing.
- Products application cache MISS → HIT.
- `/home` and Home cache MISS → HIT.
- `/categories`.
- Category detail and parent/child structure.
- Simple product detail.
- Variable product detail, attributes, variations, variable pricing and stock.
- Category filter discovery from real product attributes.
- Single attribute filtering.
- OR within one attribute.
- AND between different attributes.
- `lang=ar` / `lang=en` switching and `Content-Language: ar` observed.

Known data/acceptance work still open:
- Audit legacy products missing `_eldokan_seller_user_id`.
- Visibility acceptance (hidden/password/draft/search/catalog).
- Error responses such as 404/422/503.
- Arabic WPML catalog-data cleanup: some translations and category parent relations are incomplete.
- Review Home catalog payload/data quality where source product content itself is unusually large.

## Integration rule for Mahmoud

Use one browser-session instance or one instance per SSR request. Never share an authenticated instance between server-side users. Do not scatter `fetch()` calls across components.

For Home, switch on `section.type`. The new values are `hero_slider` and `banner_grid`; their items are typed `HeroSlide` and `PromoBanner`. CTA may be null, and desktop/mobile images may independently be null.

For current frontend work:

- `categories.list({ parent })` or `categories.get(slug).data.children` provides subcategories.
- `brands.list()` and `tags.list()` provide localized paginated filters.
- `products.list({ category, sort: 'best_selling' })` provides category best sellers.
- `products.list({ sort: 'newest' })` provides new arrivals.
- `products.list({ brand })` and `products.list({ tag })` filter the catalog.
- `sellers.get('sel_*')` provides public store identity through its stable public ID only.
- `auth.register/login/session/logout` owns customer session flow and in-memory CSRF.
- `account.me/update` provides the minimal private customer projection.
- `wishlist.get/add/remove` provides the private ProductCard Wishlist.

Recommended app boundary:

```text
src/lib/eldokan-api.ts
→ creates/configures client once

src/data/* or src/services/*
→ calls client resources

components/*
→ receives typed data; does not know backend URLs
```

## Base URL migration

Today:
`https://www.eldokan.com/wp-json/eldokan-customer/v1`

Target:
`https://api.eldokan.com/v1/customer`

Only configuration should change when the target hostname is activated.

## Do not add yet

- Cart/guest session.
- Checkout.
- Orders, product review submission and seller rating submission.
- Payment calls.
- WooCommerce Store API calls.
- Any custom JWT or localStorage credential.

Those require a reviewed contract and security design first.
