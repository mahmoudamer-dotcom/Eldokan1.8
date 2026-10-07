import test from 'node:test';
import assert from 'node:assert/strict';
import {
  createEldokanCustomerApiClient,
  EldokanClientError,
  serializeAttributeFilters,
} from '../dist/index.js';

function success(body, headers = {}) {
  return new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'content-type': 'application/json', ...headers },
  });
}

function makeClient(handler, extra = {}) {
  return createEldokanCustomerApiClient({
    baseUrl: 'https://example.test/v1/customer',
    fetch: handler,
    defaultLanguage: 'en',
    ...extra,
  });
}

test('checkout quote preserves shipping issues and support request ID', async () => {
  const issues = [{ code: 'shipping_unavailable', message: 'No supported shipping method covers the complete cart.' }];
  const client = makeClient(async (url) => {
    if (new URL(String(url)).pathname.endsWith('/cart')) {
      return success({ success: true, data: { csrf_token: 'csrf-cart' }, meta: { request_id: 'req_cart' } });
    }
    return new Response(JSON.stringify({ success: false,
      error: { code: 'checkout_not_ready', message: 'Checkout unavailable.', issues },
      meta: { request_id: 'req_quote_shipping' },
    }), { status: 422, headers: { 'content-type': 'application/json' } });
  });
  await assert.rejects(() => client.checkout.quote({ address_id: 'adr_example' }), (error) => {
    assert.ok(error instanceof EldokanClientError);
    assert.equal(error.code, 'checkout_not_ready');
    assert.equal(error.requestId, 'req_quote_shipping');
    assert.deepEqual(error.issues, issues);
    return true;
  });
});

test('checkout keeps an unavailable quote authoritative without inventing payment methods', async () => {
  const data = { ready: false, shipping_required: true, shipping_methods: [], payment_methods: [],
    totals: { total: null }, issues: [{ code: 'shipping_unavailable', message: 'No supported shipping method covers the complete cart.' }] };
  const client = makeClient(async (url) => new URL(String(url)).pathname.endsWith('/cart')
    ? success({ success: true, data: { csrf_token: 'csrf-cart' }, meta: { request_id: 'req_cart' } })
    : success({ success: true, data, meta: { request_id: 'req_quote' } }));
  const result = await client.checkout.quote({ address_id: 'adr_example' });
  assert.deepEqual(result.data, data);
});

test('serializes AND/OR attribute filters exactly as the API contract', () => {
  assert.equal(
    serializeAttributeFilters([
      { attributeId: 'att_10', optionIds: ['atr_538', 'atr_544'] },
      { attributeId: 'att_5', optionIds: ['atr_238'] },
    ]),
    'att_10:atr_538,atr_544;att_5:atr_238',
  );
});

test('home.get requests the configured language through the official client', async () => {
  let seenUrl = '';
  const client = makeClient(async (url) => {
    seenUrl = String(url);
    return success({
      success: true,
      data: {
        sections: [{
          id: 'hero_slider', type: 'hero_slider', enabled: true, order: 1, title: '',
          items: [{ id: 'hsl_10', kind: 'hero', title: 'Welcome', subtitle: '', desktop_image: null, mobile_image: null, cta: null }],
        }],
      },
      meta: { request_id: 'req_home' },
    });
  });
  const result = await client.home.get({ lang: 'ar' });
  assert.equal(new URL(seenUrl).searchParams.get('lang'), 'ar');
  assert.equal(result.data.sections[0].type, 'hero_slider');
  assert.equal(result.data.sections[0].items[0].kind, 'hero');
});

test('products.list maps camelCase options to canonical query parameters', async () => {
  let seen;
  const client = makeClient(async (url, init) => {
    seen = { url: String(url), init };
    return success({
      success: true,
      data: [],
      meta: { request_id: 'req_test', page: 2, per_page: 24, total: 0, total_pages: 0 },
    });
  });

  await client.products.list({
    page: 2,
    perPage: 24,
    category: 'cables-chargers-adapters',
    brand: 'apple',
    tag: 'new',
    minPrice: 30,
    onSale: false,
    attributes: [{ attributeId: 'att_10', optionIds: ['atr_538', 'atr_544'] }],
  });

  const url = new URL(seen.url);
  assert.equal(url.pathname, '/v1/customer/products');
  assert.equal(url.searchParams.get('page'), '2');
  assert.equal(url.searchParams.get('per_page'), '24');
  assert.equal(url.searchParams.get('category'), 'cables-chargers-adapters');
  assert.equal(url.searchParams.get('brand'), 'apple');
  assert.equal(url.searchParams.get('tag'), 'new');
  assert.equal(url.searchParams.get('min_price'), '30');
  assert.equal(url.searchParams.get('on_sale'), 'false');
  assert.equal(url.searchParams.get('attributes'), 'att_10:atr_538,atr_544');
  assert.equal(url.searchParams.get('lang'), 'en');
  assert.equal(seen.init.credentials, 'omit');
  assert.equal(seen.init.cache, 'no-store');
});

test('category slugs are safely percent-encoded', async () => {
  let seenUrl = '';
  const client = makeClient(async (url) => {
    seenUrl = String(url);
    return success({ success: true, data: { id: 'cat_1', name: 'x', slug: 'x', description: '', count: 0, parent_id: null, image: null, children: [] }, meta: { request_id: 'req_test' } });
  });
  await client.categories.get('هواتف ذكية', { lang: 'ar' });
  assert.match(seenUrl, /categories\/%D9%87%D9%88%D8%A7%D8%AA%D9%81%20%D8%B0%D9%83%D9%8A%D8%A9\?lang=ar$/);
});

test('captures exposed ElDokan diagnostics through onResponse', async () => {
  let context;
  const client = makeClient(
    async () => success(
      { success: true, data: { service: 'ElDokan Customer API', version: '0.2.3', status: 'ok', ready: true }, meta: { request_id: 'req_body' } },
      {
        'X-ElDokan-Request-ID': 'req_header',
        'X-ElDokan-API-Version': '0.2.3',
        'X-ElDokan-Cache': 'HIT',
        'Content-Language': 'ar',
      },
    ),
    { onResponse: (value) => { context = value; } },
  );
  await client.health.get();
  assert.equal(context.requestId, 'req_header');
  assert.equal(context.apiVersion, '0.2.3');
  assert.equal(context.cacheStatus, 'HIT');
  assert.equal(context.contentLanguage, 'ar');
});

test('normalizes API error envelopes into EldokanClientError', async () => {
  const client = makeClient(async () => new Response(JSON.stringify({
    success: false,
    error: { code: 'product_not_found', message: 'Product not found.' },
    meta: { request_id: 'req_error' },
  }), { status: 404, headers: { 'content-type': 'application/json' } }));

  await assert.rejects(
    () => client.products.get('prd_999'),
    (error) => {
      assert.ok(error instanceof EldokanClientError);
      assert.equal(error.kind, 'api');
      assert.equal(error.status, 404);
      assert.equal(error.code, 'product_not_found');
      assert.equal(error.requestId, 'req_error');
      return true;
    },
  );
});


test('health does not send lang even when a default language is configured', async () => {
  let seenUrl = '';
  const client = makeClient(async (url) => {
    seenUrl = String(url);
    return success({ success: true, data: { service: 'ElDokan Customer API', version: '0.2.3', status: 'ok', ready: true }, meta: { request_id: 'req_health' } });
  });
  await client.health.get();
  const url = new URL(seenUrl);
  assert.equal(url.searchParams.has('lang'), false);
});

test('rejects attribute filtering without category before sending a request', async () => {
  let called = false;
  const client = makeClient(async () => {
    called = true;
    return success({ success: true, data: [], meta: { request_id: 'req_test', page: 1, per_page: 24, total: 0, total_pages: 0 } });
  });
  assert.throws(
    () => client.products.list({ attributes: [{ attributeId: 'att_10', optionIds: ['atr_538'] }] }),
    (error) => error instanceof EldokanClientError && error.code === 'category_required_for_attributes',
  );
  assert.equal(called, false);
});

test('brands and tags expose paginated localized collection methods', async () => {
  const seen = [];
  const client = makeClient(async (url) => {
    seen.push(String(url));
    return success({
      success: true,
      data: [],
      meta: { request_id: 'req_terms', page: 2, per_page: 25, total: 0, total_pages: 0 },
    });
  });

  await client.brands.list({ page: 2, perPage: 25, search: 'app', lang: 'en' });
  await client.tags.list({ page: 2, perPage: 25, search: 'جديد', lang: 'ar' });

  const brands = new URL(seen[0]);
  const tags = new URL(seen[1]);
  assert.equal(brands.pathname, '/v1/customer/brands');
  assert.equal(brands.searchParams.get('per_page'), '25');
  assert.equal(brands.searchParams.get('search'), 'app');
  assert.equal(tags.pathname, '/v1/customer/tags');
  assert.equal(tags.searchParams.get('search'), 'جديد');
  assert.equal(tags.searchParams.get('lang'), 'ar');
});

test('brands expose the normalized nullable thumbnail through the typed client', async () => {
  const client = makeClient(async () => success({
    success: true,
    data: [
      { id: 'brd_12', name: 'Apple', slug: 'apple', image: { url: 'https://example.test/apple.webp', alt: 'Apple' } },
      { id: 'brd_13', name: 'No image', slug: 'no-image', image: null },
    ],
    meta: { request_id: 'req_brands', page: 1, per_page: 50, total: 2, total_pages: 1 },
  }));

  const result = await client.brands.list();
  assert.deepEqual(result.data[0].image, { url: 'https://example.test/apple.webp', alt: 'Apple' });
  assert.equal(result.data[1].image, null);
});

test('cart bootstraps guest CSRF in memory then uses credentialed mutations', async () => {
  const seen = [];
  const itemId = `cit_${'a'.repeat(32)}`;
  const client = makeClient(async (url, init) => {
    seen.push({ url: String(url), init });
    if (String(url).includes('/cart/items')) {
      return success({
        success: true,
        data: { items: [], count: 0, valid: true, owner_type: 'guest', csrf_token: 'guest-csrf', changed: true },
        meta: { request_id: 'req_cart_mutation' },
      });
    }
    return success({
      success: true,
      data: { items: [], count: 0, valid: true, owner_type: 'guest', csrf_token: 'guest-csrf' },
      meta: { request_id: 'req_cart' },
    });
  });

  await client.cart.add({ productId: 'prd_10', quantity: 1 });
  await client.cart.update(itemId, { quantity: 2 });
  await client.cart.remove(itemId);

  assert.equal(seen[0].url.endsWith('/cart?lang=en'), true);
  assert.equal(seen[0].init.credentials, 'include');
  assert.equal(new Headers(seen[1].init.headers).get('X-ElDokan-CSRF'), 'guest-csrf');
  assert.equal(seen[1].init.credentials, 'include');
  assert.equal(JSON.parse(seen[1].init.body).product_id, 'prd_10');
  assert.equal(Object.hasOwn(JSON.parse(seen[1].init.body), 'variation_id'), false);
  assert.equal(seen.filter((entry) => entry.url.endsWith('/cart?lang=en')).length, 1);
});

test('cart sends variation_id only when a variation is selected', async () => {
  let body;
  const client = makeClient(async (url, init) => {
    if (String(url).includes('/cart/items')) body = JSON.parse(init.body);
    return success({ success: true, data: { items: [], count: 0, valid: true, owner_type: 'guest', csrf_token: 'guest-csrf', changed: true }, meta: { request_id: 'req_cart' } });
  });

  await client.cart.add({ productId: 'prd_10', variationId: 'var_20', quantity: 1 });
  assert.equal(body.variation_id, 'var_20');
});

test('cart shares an in-flight session bootstrap with an add request', async () => {
  const calls = [];
  let resolveCart;
  const client = makeClient(async (url) => {
    if (String(url).includes('/cart/items')) {
      calls.push('add');
      return success({ success: true, data: { items: [], count: 0, valid: true, owner_type: 'guest', csrf_token: 'guest-csrf', changed: true }, meta: { request_id: 'req_cart_add' } });
    }
    calls.push('get');
    return new Promise((resolve) => {
      resolveCart = () => resolve(success({ success: true, data: { items: [], count: 0, valid: true, owner_type: 'guest', csrf_token: 'guest-csrf' }, meta: { request_id: 'req_cart' } }));
    });
  });

  const initialLoad = client.cart.get();
  const add = client.cart.add({ productId: 'prd_10', quantity: 1 });
  await Promise.resolve();
  assert.deepEqual(calls, ['get']);
  resolveCart();
  await Promise.all([initialLoad, add]);
  assert.deepEqual(calls, ['get', 'add']);
});

test('cart rejects zero quantity before transport', async () => {
  let called = false;
  const client = makeClient(async () => { called = true; throw new Error('transport must not run'); });
  await assert.rejects(
    () => client.cart.add({ productId: 'prd_10', quantity: 0 }),
    (error) => error instanceof EldokanClientError && error.code === 'invalid_quantity',
  );
  assert.equal(called, false);
});

test('seller public resource uses the stable public seller ID and has no auth behavior', async () => {
  let seenUrl = '';
  const client = makeClient(async (url) => {
    seenUrl = String(url);
    return success({
      success: true,
      data: { id: 'sel_42', name: 'متجر النور', slug: 'متجر-النور', rating: null, rating_count: null },
      meta: { request_id: 'req_seller' },
    });
  });

  const result = await client.sellers.get('sel_42', { lang: 'ar' });
  assert.match(seenUrl, /sellers\/sel_42\?lang=ar$/);
  assert.equal(result.data.id, 'sel_42');
  assert.equal(result.data.rating, null);
  assert.equal(result.data.rating_count, null);
});

test('catalog term resources validate pagination before transport', () => {
  const client = makeClient(async () => {
    throw new Error('transport must not run');
  });

  assert.throws(
    () => client.brands.list({ perPage: 101 }),
    (error) => error instanceof EldokanClientError && error.code === 'invalid_per_page',
  );
  assert.throws(
    () => client.tags.list({ page: 0 }),
    (error) => error instanceof EldokanClientError && error.code === 'invalid_page',
  );
});

test('customer auth uses credentialed WordPress session requests and keeps CSRF in memory', async () => {
  const seen = [];
  const client = makeClient(async (url, init) => {
    seen.push({ url: String(url), init });
    return success({
      success: true,
      data: {
        customer: { id: 'cus_0123456789abcdef0123456789abcdef', first_name: 'M', last_name: 'A', display_name: 'M A', email: 'm@example.test' },
        csrf_token: 'csrf-session-token',
      },
      meta: { request_id: 'req_auth' },
    });
  });

  await client.auth.login({ email: 'm@example.test', password: 'correct-horse-battery-staple' });
  await client.account.update({ display_name: 'Mahmoud Ahmed' });

  assert.equal(seen[0].init.method, 'POST');
  assert.equal(seen[0].init.credentials, 'include');
  assert.equal(new URL(seen[0].url).searchParams.has('lang'), false);
  assert.deepEqual(JSON.parse(seen[0].init.body), { email: 'm@example.test', password: 'correct-horse-battery-staple' });
  assert.equal(seen[1].init.method, 'PATCH');
  assert.equal(seen[1].init.credentials, 'include');
  assert.equal(seen[1].init.headers.get('X-ElDokan-CSRF'), 'csrf-session-token');
});

test('session restoration enables Wishlist methods and logout clears client CSRF state', async () => {
  const seen = [];
  const client = makeClient(async (url, init) => {
    seen.push({ url: String(url), init });
    const path = new URL(String(url)).pathname;
    if (path.endsWith('/auth/session')) {
      return success({ success: true, data: { customer: { id: 'cus_0123456789abcdef0123456789abcdef', first_name: '', last_name: '', display_name: 'Customer', email: 'c@example.test' }, csrf_token: 'csrf-restored' }, meta: { request_id: 'req_session' } });
    }
    if (path.endsWith('/auth/logout')) {
      return success({ success: true, data: { logged_out: true }, meta: { request_id: 'req_logout' } });
    }
    return success({ success: true, data: { items: [], count: 0, changed: true }, meta: { request_id: 'req_wishlist' } });
  });

  await client.auth.session();
  await client.wishlist.add('prd_42', { lang: 'ar' });
  await client.wishlist.remove('prd_42');
  await client.auth.logout();

  assert.equal(seen[1].init.credentials, 'include');
  assert.equal(seen[1].init.headers.get('X-ElDokan-CSRF'), 'csrf-restored');
  assert.equal(JSON.parse(seen[1].init.body).product_id, 'prd_42');
  assert.equal(seen[2].init.method, 'DELETE');
  assert.equal(seen[3].init.headers.get('X-ElDokan-CSRF'), 'csrf-restored');
  assert.throws(
    () => client.wishlist.add('prd_42'),
    (error) => error instanceof EldokanClientError && error.code === 'missing_csrf_token',
  );
});

test('unauthenticated account errors remain standardized', async () => {
  const client = makeClient(async () => new Response(JSON.stringify({
    success: false,
    error: { code: 'authentication_required', message: 'Customer authentication is required.' },
    meta: { request_id: 'req_unauthenticated' },
  }), { status: 401, headers: { 'content-type': 'application/json' } }));

  await assert.rejects(
    () => client.account.me(),
    (error) => error instanceof EldokanClientError
      && error.status === 401
      && error.code === 'authentication_required',
  );
});
