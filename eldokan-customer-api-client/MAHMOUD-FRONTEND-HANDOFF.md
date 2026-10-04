# Mahmoud Frontend Handoff — Client 0.4.1

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

The session cookie is HttpOnly and is handled by the browser. Do not read cookies, store passwords, or persist the CSRF value in `localStorage`. The Client keeps CSRF in memory after register/login/session and sends it automatically for mutations.

Create one Client instance per browser session. For Next.js server rendering, create it per incoming request; never share one authenticated Client singleton between users. Handle 401 by clearing frontend customer state and returning to login.

Handle pagination metadata, empty arrays, `image: null`, `seller: null`, and `EldokanClientError`. Do not call WordPress/WooCommerce directly.

Not available in this release: authenticated Seller account, Cart/Guest Session, Checkout, order writes, product-review submission or seller-rating submission.
