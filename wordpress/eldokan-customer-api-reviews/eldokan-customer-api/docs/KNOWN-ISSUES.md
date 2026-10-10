# Known Issues and Deliberate Gaps

## Delivery

Product detail currently exposes delivery placeholders.

Real delivery calculation is not implemented.

## Warranty

Warranty extraction currently uses compatibility heuristics.

A canonical warranty data model is still needed.

## Seller ratings

Public seller `rating` and `rating_count` remain null until the Phase 2 customer-facing Seller Rating system is implemented. Internal or manually assigned Admin scores are never exposed as customer ratings.

## Follow / price tracking

Seller follow and price-tracking are intentionally absent. Wishlist exists as a separate authenticated customer resource and is not embedded in public Product/Seller cards.

## Session deployment configuration

The current WordPress origin works with the native logged-in cookie. A future separate `api.eldokan.com` deployment must explicitly configure the frontend origin and shared cookie/proxy behavior before authenticated calls can move there.

## Home configuration

Managed Hero Slider and Promo Banner content is available through `/home`. FAQ, Why ElDokan and feature sections remain omitted because they are not part of the current managed Home contract.

## WooCommerce dependence

The implementation still calls WooCommerce/WordPress internally. This is expected for the current adapter.

The frontend contract is the part intended to survive backend replacement.

## Performance

Application caching reduces repeated work, but cache-miss performance must still be measured on production hosting.

If cache misses remain slow, query strategy must be optimized rather than simply increasing TTL indefinitely.


## Seller migration

Legacy products without `_eldokan_seller_user_id` return `seller: null` until audited and migrated.

## WPML identity

Translations currently use distinct public IDs. A future independent identity store may unify them, but must preserve all issued IDs.

WPML remains the current bilingual data provider, but all provider-specific calls and SQL are isolated in the translation adapter. Without WPML, the API falls back to the native site language rather than failing.
