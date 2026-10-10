# Stories companion plugin

Install and activate ElDokan Stories 1.1.0 alongside WooCommerce and Customer API. This optional resource does not change existing catalog endpoints.

```ts
const response = await api.stories.list({ lang: 'ar' });
// response.data.items: StoreStory[]
```

`StoreStory` includes `id`, localized `title`, nullable artwork `image`, and a live `ProductCard`. The endpoint is `/stories` under the existing Customer API base URL. Draft and expired stories and unavailable products are hidden. A missing companion plugin causes the endpoint to fail; the storefront preserves its automatic catalog stories in that case. A successful empty list hides the strip.

Manage content through WooCommerce → Store Stories. See the companion plugin README for publication, ordering and expiration.

Version 1.1.0 adds seller-first product search in WordPress. The public response remains compatible; the storefront displays the existing product.seller.name and links to product.seller.id through this typed resource.
