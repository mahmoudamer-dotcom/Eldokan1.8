# Testing and acceptance — Customer API 0.8.0 / Contract v1

## Passed locally, already completed

Backend A1–A4F checkpoint/runtime verification was completed previously. Client 0.6.0 passed 26/26 tests, typecheck/build and package checks; Portal 1.8.0 build/verifier, OpenAPI validation and 40/40 downstream contract matrix passed. Focused clean-package extraction/version/includes/source-byte checks passed. This documentation-only cleanup does not rerun backend runtime, Client or provider simulations.

Checkpoint reports and test harnesses are separate recovery evidence, not files required by this production ZIP. No local completion is a real installed-store acceptance claim.

## Real current-release acceptance pending

WordPress/Woo installation, MySQL/MariaDB, HPOS, real browser cookies/CORS/CSRF, Admin/Seller hooks, shipping/tax, Paymob 4.1.15 Sandbox and Accept TRANSACTION merchant compatibility, callback identity/expiry and explicitly verified completed/custom status mappings all remain mandatory before Live. Exercise Address Book/phone, checkout/attempt recovery, one-Order COD/hosted Paymob, account Orders, exact Guest capability and dedicated Guest payment CSRF, concurrent/stale generation fencing and delayed callbacks. Browser return is never payment proof. Seller Fulfillment mutation compatibility remains a separate phase.

The retained catalog/session acceptance checks below remain applicable; they are not executed by this cleanup.

## Mandatory WordPress staging/live tests

1. Call every endpoint in `docs/ENDPOINT-STATUS.md` with `lang=en` and `lang=ar`.
2. Verify `/categories?parent={slug}` and `/categories/{slug}.children` return the same direct hierarchy for both languages.
3. Verify `/brands` and `/tags` pagination, search, Arabic/English names/slugs, empty pages and invalid page bounds; verify Brand `image` is normalized or null.
4. Verify guest Cart persistence/isolation, customer Cart isolation, login/register merge, logout privacy, simple/variable lines, authoritative price/stock and EN → AR → EN persistence.
4. Verify `/products?brand={slug}` and `/products?tag={slug}` return only matching public products.
5. Verify `/products?category={slug}&sort=best_selling` remains category-scoped and ordered by sales; verify `sort=newest`.
6. Verify `/sellers/{seller_id}` with a ProductCard `sel_*` ID and a standardized 404; confirm product `seller:null` cases remain unchanged.
7. Repeat a cacheable request and confirm MISS then HIT with the same cache key/generation.
8. Add an irrelevant query parameter and confirm it does not change `X-ElDokan-Cache-Key`.
9. Confirm `Cache-Control: private, no-store`, `Content-Language`, and all diagnostic headers.
10. Test an Arabic category slug using percent encoding.
11. Test WPML English/Arabic product/category/attribute translations and record the public IDs returned for each language.
12. In a controlled test with WPML unavailable, confirm there is no fatal error, native content is returned, and `Content-Language` reports the actual native language.
13. Test published, draft, private, password-protected, catalog-hidden, search-only and catalog-only products in list/search/direct routes.
14. Test a category rule for each mode: configured, inherited, none, inferred.
15. Get real filter IDs, then verify OR within one attribute and AND across two attributes. Totals/pages must match the returned filtered set.
16. Reject filters without a category, unknown/repeated attributes, foreign option IDs, more than 5 groups, more than 10 options, and a reversed price range.
17. Test variable products with disabled, empty-price, out-of-stock, backorder, “any” option, and missing-image variations. Confirm no `attribute_pa_*` keys occur.
18. Audit products without `_eldokan_seller_user_id`, invalid seller roles, or missing store names; all must return `seller: null`. Confirm valid sellers are correct.
19. Confirm `/home` returns only managed Hero/Banner records, contains no placeholder FAQ/features, and Arabic titles are Arabic.
20. Confirm `/health` exposes only service, adapter version, status and readiness inside `data`.
21. Confirm Product Detail and Seller never expose wishlist, price-tracking or followed placeholders.
22. Confirm Product Detail returns `image` as the primary product image and keeps `images` as the primary image plus the full gallery; verify `image: null` for a product without a primary image.
23. Confirm validation, 404, 422 and Woo unavailable errors use the standard envelope and request ID.

## Phase 2A WordPress acceptance

1. Register successfully and confirm a random `cus_*` ID, HttpOnly/Secure/Lax cookie and CSRF value.
2. Confirm duplicate email is 409 and invalid email/password/name input is 422.
3. Confirm valid login succeeds; wrong email and wrong password return the same generic 401; exercise throttling without locking out unrelated customers.
4. Confirm unauthenticated `/me` is 401 and authenticated `/me` exposes only id, names, display name, email and string phone.
5. POST/PUT/PATCH supported names/phone with CSRF; reject missing/invalid CSRF and unsupported fields.
6. Restore the session through `GET /auth/session`, then logout and confirm the same session no longer restores or opens `/me`.
7. Confirm an empty Wishlist, add a visible simple product, add it twice with `changed:false`, and remove it.
8. Confirm deleting a missing Wishlist item returns 200 with `changed:false`.
9. Add a variable parent product; reject variation IDs, draft/private/password-protected/otherwise non-visible products.
10. Verify two customers cannot observe or modify each other's Wishlist.
11. Verify Wishlist language behavior and ProductCard shape without post IDs/Woo objects.
12. Confirm no response contains numeric WordPress user IDs, roles, capabilities, password data or internal user meta.
13. Verify allowed frontend origin with credentials and preflight; confirm an unapproved origin receives no credentialed CORS permission.
14. Regress all accepted Phase 1 endpoints and confirm Seller `rating` and `rating_count` remain null.

## Release evidence

Record date, environment, plugin version, WP/WC/WPML versions, request URL, status, response sample, cache headers and timing. Do not mark this release live-tested until that evidence exists.
