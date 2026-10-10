# Commerce extensions — client 0.8.0 / adapter 0.10.0

Use `api.commerce` for password recovery, customer order returns, product alerts, saved decision profiles, review feedback/images and public sitemap pagination. Methods and response types are exported from `resources/commerce` through the package root.

Authenticated writes require `await api.auth.session()` first. `CommerceResource` uses the shared session CSRF token. All private reads/writes use credentials; provider secrets never enter this client. Public password recovery requires an allowed origin and a reset key for completion. The storefront proxy checks same-origin writes and forwards cookies through the existing API client transport.

```ts
await api.auth.session();
await api.commerce.saveAlert({ product_id: 'prd_5526', kind: 'price', target_price: 200 });
const returns = await api.commerce.returns(orderId);
await api.commerce.requestReturn(orderId, {
  reason: 'damaged', details: 'Arrived damaged',
  lines: [{ item_id: returns.data.items[0].item_id, quantity: 1 }],
});
```

Return availability is based on the configured confirmed-delivery status. Submission is a store-review request, never a refund. `refunded_amount` is the order refund amount recorded by WooCommerce in major currency units, not provider confirmation. Return states and staff replies are administered in the WooCommerce order panel.

Product alerts support simple products. Stock alerts require an unavailable product; price alerts require a target below the current price. Email is sent by a WordPress scheduled task, not by an open browser tab. One product alert is stored per product/customer, with a 50-product cap. Decision profiles save category, budget, keywords, priority and up to four comparison IDs.

Review images use a data URL for JPEG/PNG/WebP, maximum 3 MB per file and three per review. Ownership and review status are checked on the server; an image places the review back in moderation. Images are public WordPress media. Feedback accepts `helpful` or `report`; helpful counts come from the server. Reports are visible only to store staff.

Home payload optionally includes `category_shelves`, preserving compatibility with older adapters. Product list metadata optionally includes `resolved_search` when an empty search was resolved through a configured alias. `api.commerce.sitemap(page)` returns published catalog IDs, modification dates and category slugs on its first page.

Bosta integration is deferred. No carrier creation, refund execution or invented delivery estimate is part of these extensions. PHP runtime and end-to-end payment acceptance are not asserted by package compilation.
