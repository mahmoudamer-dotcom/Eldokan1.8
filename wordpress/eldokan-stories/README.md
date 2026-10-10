# ElDokan Stories 1.1.1

Requires WordPress, WooCommerce and an active ElDokan Customer API plugin exposing `ElDokan_Customer_API_Products::card_by_internal_id`.

## Install and manage

1. Upload `eldokan-stories-1.1.1.zip` under Plugins → Add New → Upload Plugin. Replace version 1.0.0 if installed. Keep the Customer API plugin active; this is a separate companion plugin. Existing stories are retained.
2. Open WooCommerce → Store Stories → Add Store Story.
3. Search by seller/store name, select the seller, then search their product names. Results show the product image, name, actual selling price, stock, quantity and SKU. Select a result to preview it. Add English and Arabic story titles. Set a featured image if desired; otherwise the product image is used.
4. Set Order under Attributes (lowest first). Set an optional expiration in the store timezone. Use WordPress scheduling for a future publication.
5. Publish. Draft, expired, inaccessible and out-of-stock product stories are excluded. At most 24 published, unexpired stories are considered per request.

The product button, prices and inventory come from the existing Customer API serializer. Only managers with `manage_woocommerce` can manage stories. An active plugin with no published eligible stories returns an empty list, which hides the story strip. Deactivate the companion plugin to restore automatic seller stories from the catalog.

The seller name is displayed with the story and links to the seller page in the viewer. It comes from `product.seller` through the API client. Seller selection uses the existing API seller registry; product ownership uses `_eldokan_seller_user_id`, matching the Customer API. Unassigned products must have their seller assignment corrected in the existing seller dashboard before they can be selected. Search is available only to authorized WordPress managers, with a nonce. Product ownership is checked again on save.

## API

`GET /wp-json/eldokan-customer/v1/stories?lang=ar` (or `en`).

Response: `{ success: true, data: { items: [{ id, title, image: { url, alt } | null, product: ProductCard }] }, meta: { request_id } }`.

Client: `api.stories.list({ lang: 'ar' })`. Browser calls use the existing same-origin storefront proxy. No authentication is required to read published stories; admin writes use WordPress permissions and nonces.

No payment settings, shipping methods, orders or category settings are modified. PHP syntax was parsed; activation and publication require checking in your WordPress installation.

Version 1.1.1 loads shared bilingual story records before switching product language, and falls back to the original public product when its translation is absent. Localized story titles remain available.
