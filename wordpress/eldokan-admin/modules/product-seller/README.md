# Product seller

The ElDokan Customer API sells a product only when `_eldokan_seller_user_id` points to a user with the `eldokan_owner`, `wcfm_vendor`, `seller` or `vendor` role. Products created by staff (not through the seller dashboard or the WCFM migration) have no such owner, so checkout reports `seller_missing`.

This module adds:

- An **ElDokan seller** box on the product edit screen.
- An **ElDokan seller** column in the product list ("Missing" in red when the product cannot be sold).
- **Products → ElDokan sellers**: lists products without a seller, assigns one seller to all of them, and can create a dedicated store seller account (role `eldokan_owner`) for products the store sells itself. Staff accounts are never given seller roles, because Admin Staff withdraws back-office capabilities from seller accounts.

It pauses itself if the standalone *ElDokan Product Seller* plugin is active. Disable it with `define('ELDOKAN_ADMIN_EMBED_PRODUCT_SELLER', false);`.
