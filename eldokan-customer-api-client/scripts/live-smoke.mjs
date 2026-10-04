import { createEldokanCustomerApiClient } from '../dist/index.js';

const baseUrl = process.env.ELDOKAN_API_BASE_URL;
if (!baseUrl) {
  console.error('Set ELDOKAN_API_BASE_URL before running live smoke tests.');
  process.exit(2);
}

const diagnostics = [];
const api = createEldokanCustomerApiClient({
  baseUrl,
  defaultLanguage: 'en',
  onResponse(context) {
    diagnostics.push({
      status: context.status,
      requestId: context.requestId,
      apiVersion: context.apiVersion,
      contentLanguage: context.contentLanguage,
      cacheStatus: context.cacheStatus,
      serverTiming: context.serverTiming,
    });
  },
});

const health = await api.health.get();
if (!health.data.ready) throw new Error('API health is not ready.');

const products = await api.products.list({ perPage: 3 });
const categories = await api.categories.list();
const home = await api.home.get();

console.log(JSON.stringify({
  health: health.data,
  products: { count: products.data.length, total: products.meta.total },
  categories: { count: categories.data.length },
  home: { sections: home.data.sections.length },
  diagnostics,
}, null, 2));
