# Client 0.7.1 / Customer API adapter 0.9.1

Adds `api.reviews.list/mine/create/update/remove`, `api.sellers.products` and `api.products.bestSelling(category)`.

Reviews target `prd_*` or `sel_*`. Public lists contain approved reviews and a whole-target star summary, with server pagination and rating/sort filters. `mine` requires a customer session and reports purchase eligibility and the customer's own review. Call `api.auth.session()` before writing; writes use the in-memory session CSRF token. New and edited reviews await moderation. One review per account/target; only the owner can edit/delete. Public responses exclude private customer IDs, email and phone.

Seller purchases use immutable seller IDs on processing/completed order items. Seller product pages are queried on the server, rather than downloading the entire catalog. Best sellers are category-scoped, in stock, and have nonzero WooCommerce total sales; this is an all-time ranking, not a promise about recent sales.

Install the adapter 0.9.1 alongside the updated storefront. The original client test suite and WordPress runtime have not been run for this release. See the workspace REVIEWS-SETUP.md for deployment and moderation.
