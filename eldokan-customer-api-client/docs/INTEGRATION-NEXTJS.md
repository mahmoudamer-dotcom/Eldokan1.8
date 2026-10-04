# Next.js Integration

## Recommended setup

Create one file in the frontend, e.g. `src/lib/eldokan-api.ts`:

```ts
import { createEldokanCustomerApiClient } from '@eldokan/customer-api-client';

export const eldokanApi = createEldokanCustomerApiClient({
  baseUrl: process.env.ELDOKAN_API_BASE_URL!,
  defaultLanguage: 'en',
});
```

Prefer server-side catalog reads where practical for SEO and to avoid shipping unnecessary networking logic to the browser.

Then create application-level data functions, e.g. `src/data/products.ts`:

```ts
import { eldokanApi } from '@/lib/eldokan-api';

export async function getCategoryProducts(category: string) {
  return eldokanApi.products.list({ category, perPage: 24 });
}
```

Components consume the returned typed data and do not know API hostnames.

## Do not

```ts
// Do not scatter this across components:
fetch('https://www.eldokan.com/wp-json/...')
```

The current origin is temporary implementation detail.
