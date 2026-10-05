# Mahmoud Frontend Handoff — Client 0.5.0

Use only `@eldokan/customer-api-client` from the frontend data/service layer.

```ts
const subcategories = await eldokanApi.categories.list({
  parent: 'phones',
  lang: 'en',
});

const category = await eldokanApi.categories.get('phones', { lang: 'en' });
const sameDirectChildren = category.data.children;

const brands = await eldokanApi.brands.list({
  page: 1,
  perPage: 50,
  lang: 'en',
});

const tags = await eldokanApi.tags.list({
  page: 1,
  perPage: 50,
  lang: 'en',
});

const categoryBestSellers = await eldokanApi.products.list({
  category: 'phones',
  sort: 'best_selling',
  page: 1,
  perPage: 24,
  lang: 'en',
});

const newArrivals = await eldokanApi.products.list({
  sort: 'newest',
  page: 1,
  perPage: 24,
  lang: 'en',
});

const brandProducts = await eldokanApi.products.list({ brand: 'apple' });
const taggedProducts = await eldokanApi.products.list({ tag: 'new' });
const publicStore = await eldokanApi.sellers.get('sel_42');
```

Home banners and new-arrival carousels may already be present in `eldokanApi.home.get()`; do not duplicate them with invented frontend data.

## Customer session, account and Wishlist

```ts
const session = await eldokanApi.auth.login({ email, password });
const customer = session.data.customer;

// On browser refresh, restore the HttpOnly-cookie session and refresh CSRF:
await eldokanApi.auth.session();

const me = await eldokanApi.account.me();
await eldokanApi.account.update({ first_name: 'Mahmoud', display_name: 'Mahmoud Ahmed' });

const wishlist = await eldokanApi.wishlist.get({ lang: 'ar' });
await eldokanApi.wishlist.add('prd_17231', { lang: 'ar' });
await eldokanApi.wishlist.remove('prd_17231', { lang: 'ar' });

await eldokanApi.auth.logout();
```

## Guest Session and Cart

Create the Client once per browser session. On Cart page/app bootstrap, restore or create the Cart session:

```ts
const cart = await eldokanApi.cart.get({ lang: 'en' });

await eldokanApi.cart.add({
  productId: 'prd_17231',
  quantity: 1,
}, { lang: 'en' });

await eldokanApi.cart.add({
  productId: 'prd_17240',
  variationId: 'var_17244',
  quantity: 1,
}, { lang: 'en' });

await eldokanApi.cart.update(
  'cit_0123456789abcdef0123456789abcdef',
  { quantity: 2 },
  { lang: 'en' },
);

await eldokanApi.cart.remove(
  'cit_0123456789abcdef0123456789abcdef',
  { lang: 'en' },
);
```

The Client automatically calls `cart.get()` before the first Cart mutation when it needs to bootstrap guest CSRF. Login/register merges the valid guest Cart into the customer Cart on the server. Do not perform a frontend merge and do not add quantities locally.

Use only the public `prd_*`, `var_*` and `cit_*` values returned by the API. Quantity zero is invalid. Render `Cart.valid` and each item's `valid/issues` state because the server refreshes current price, stock and purchasability on every response. Cart does not reserve stock.

Switching language changes only the response projection. Never remove frontend Cart membership because an Arabic/English translation is unavailable.

Customer and guest Cart cookies are HttpOnly and handled by the browser. Do not read cookies or store Cart state, passwords, session IDs or CSRF in `localStorage`. The Client keeps CSRF in memory and sends it automatically for mutations.

Create one Client instance per browser session. For Next.js server rendering, create it per incoming request; never share one authenticated Client singleton between users. Handle 401 by clearing frontend customer state and returning to login.

Handle pagination metadata, empty arrays, `image: null`, `seller: null`, and `EldokanClientError`. Do not call WordPress/WooCommerce directly.

Not available in this release: authenticated Seller account, Checkout, order writes, product-review submission or seller-rating submission.
