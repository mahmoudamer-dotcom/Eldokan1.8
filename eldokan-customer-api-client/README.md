# @eldokan/customer-api-client

Official framework-agnostic TypeScript client for **ElDokan Customer API Contract v1**.

Current release: **0.5.0**, synchronized with Phase 2B Guest Session and Cart in Customer API 0.6.0.

## لماذا موجود؟

حتى يكون الاتصال بالـBackend مملوكًا ومنظمًا من ElDokan، ولا تعتمد صفحات React/Next.js على `wp-json` أو WooCommerce response shapes أو WPML internals.

```text
Frontend Component
      ↓
ElDokan API Client
      ↓
ElDokan Customer API Contract
      ↓
Current WordPress/WooCommerce Adapter
```

عند تشغيل `api.eldokan.com` لاحقًا، نغير `baseUrl` في مكان واحد فقط.

## الاستخدام

```ts
import { createEldokanCustomerApiClient } from '@eldokan/customer-api-client';

export const eldokanApi = createEldokanCustomerApiClient({
  baseUrl: process.env.ELDOKAN_API_BASE_URL!,
  defaultLanguage: 'en',
});
```

ثم داخل طبقة البيانات، وليس داخل كل Component:

```ts
const products = await eldokanApi.products.list({
  category: 'cables-chargers-adapters',
  perPage: 24,
  attributes: [
    { attributeId: 'att_10', optionIds: ['atr_538', 'atr_544'] },
    { attributeId: 'att_5', optionIds: ['atr_238'] },
  ],
});
```

الـClient يحول المثال السابق إلى منطق الـContract:

```text
(att_10 = atr_538 OR atr_544)
AND
(att_5 = atr_238)
```

## الموارد المتاحة

```ts
eldokanApi.health.get()
eldokanApi.home.get({ lang: 'ar' })

eldokanApi.categories.list({ lang: 'en' })
eldokanApi.categories.get('electronics')
eldokanApi.categories.filters('cables-chargers-adapters')
eldokanApi.brands.list({ page: 1, perPage: 50, lang: 'en' })
eldokanApi.tags.list({ search: 'new', lang: 'en' })
eldokanApi.sellers.get('sel_42', { lang: 'en' })

eldokanApi.products.list({ page: 1, perPage: 24, tag: 'new' })
eldokanApi.products.list({ category: 'phones', sort: 'best_selling' })
eldokanApi.products.get('prd_17231')
eldokanApi.products.lookupBySlug('generic-telephone-cable-normal')

eldokanApi.search.suggestions('iphone', { limit: 8 })

await eldokanApi.auth.register({ email, password, first_name, last_name })
await eldokanApi.auth.login({ email, password })
await eldokanApi.auth.session() // restore cookie session + refresh in-memory CSRF
await eldokanApi.account.me()
await eldokanApi.account.update({ display_name: 'Mahmoud Ahmed' })
await eldokanApi.wishlist.get({ lang: 'ar' })
await eldokanApi.wishlist.add('prd_17231')
await eldokanApi.wishlist.remove('prd_17231')
await eldokanApi.auth.logout()
```

Client 0.5.0 adds typed Cart methods with automatic guest session/CSRF bootstrap while preserving Auth, Account, Wishlist and Catalog/Home behavior.

```ts
const cart = await eldokanApi.cart.get({ lang: 'en' });
await eldokanApi.cart.add({ productId: 'prd_21417', quantity: 1 });
await eldokanApi.cart.add({ productId: 'prd_21417', variationId: 'var_21419', quantity: 1 });
await eldokanApi.cart.update('cit_0123456789abcdef0123456789abcdef', { quantity: 2 });
await eldokanApi.cart.remove('cit_0123456789abcdef0123456789abcdef');
```

لا تحفظ Cart أو CSRF أو أي session identifier في `localStorage`. الـClient يرسل cookies ويحتفظ بقيمة CSRF في الذاكرة فقط.

## Base URL

### الحالي أثناء WordPress Adapter

```text
https://www.eldokan.com/wp-json/eldokan-customer/v1
```

### المستهدف بعد تشغيل الـGateway

```text
https://api.eldokan.com/v1/customer
```

لا تكتب أيًا منهما داخل Components. استخدم Environment Variable واحدة.

### Next.js — Server Side (مفضل للكتالوج)

```env
ELDOKAN_API_BASE_URL=https://www.eldokan.com/wp-json/eldokan-customer/v1
```

### Browser-side عند الضرورة

```env
NEXT_PUBLIC_ELDOKAN_API_BASE_URL=https://www.eldokan.com/wp-json/eldokan-customer/v1
```

## الأمان والسلوك

- لا توجد Secrets داخل الـClient.
- لا WooCommerce Consumer Keys.
- لا WordPress Application Passwords.
- `credentials: 'omit'` يظل الافتراضي للـCatalog العام؛ Auth/Account/Wishlist/Cart تستخدم `include` تلقائيًا.
- الـClient يحتفظ بـCSRF في الذاكرة فقط بعد register/login/session أو Cart bootstrap، ولا يضعه في `localStorage`.
- في المتصفح استخدم instance واحدة لكل جلسة. في SSR أنشئ instance لكل request ولا تستخدم singleton مشتركًا بين المستخدمين.
- `cache: 'no-store'` افتراضيًا لمنع Next/browser من إنشاء Cache إضافية تتجاوز سياسة السعر والمخزون الحالية.
- الـAPI نفسها لديها Application Cache قصيرة العمر، وتم اختبار MISS/HIT حيًا.
- Cart متاحة في Phase 2B؛ Checkout وOrders غير متاحة.

## Errors

```ts
import { EldokanClientError } from '@eldokan/customer-api-client';

try {
  await eldokanApi.products.get('prd_999');
} catch (error) {
  if (error instanceof EldokanClientError) {
    console.log(error.kind);      // api | network | timeout | invalid_response | validation
    console.log(error.code);      // product_not_found ...
    console.log(error.status);    // 404
    console.log(error.requestId); // req_...
  }
}
```

## Diagnostics

لو أردنا logging مركزيًا:

```ts
const api = createEldokanCustomerApiClient({
  baseUrl: process.env.ELDOKAN_API_BASE_URL!,
  onResponse(context) {
    console.log(context.requestId, context.cacheStatus, context.serverTiming);
  },
});
```

الـComponents لا تحتاج معرفة Headers التشخيصية.

## Contract types

`src/generated/types.ts` يتولد من OpenAPI snapshot:

```bash
python scripts/generate-types.py
```

لا تعدل الملف المولد يدويًا.

## التحقق

```bash
npm run verify
```

يشمل:
- توليد الـTypes من OpenAPI.
- TypeScript strict typecheck.
- Build.
- Unit tests للـURLs والفلاتر والأخطاء والـdiagnostics.
- Package/contract verification.

راجع `docs/HANDOFF.md` قبل تسليم الكود لأي مطور.
