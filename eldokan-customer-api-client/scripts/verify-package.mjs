import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const readJson = async (path) => JSON.parse(await readFile(resolve(root, path), 'utf8'));
const pkg = await readJson('package.json');
const spec = await readJson('openapi/eldokan-customer-api-v1.openapi.json');
const errors = [];
if (pkg.name !== '@eldokan/customer-api-client' || pkg.version !== '0.6.0' || pkg.private !== true) errors.push('package metadata mismatch');
if (Object.keys(spec.paths).length !== 32 || Object.keys(spec.components.schemas).length !== 133) errors.push('OpenAPI snapshot counts mismatch');
for (const path of ['/me/addresses', '/checkout', '/checkout/quote', '/checkout/attempts', '/checkout/orders', '/orders', '/orders/{order_id}', '/orders/{order_id}/payment']) {
  if (!spec.paths[path]) errors.push(`missing OpenAPI path ${path}`);
}
const index = await readFile(resolve(root, 'src/index.ts'), 'utf8');
for (const resource of ['AddressesResource', 'CheckoutResource', 'OrdersResource']) if (!index.includes(resource)) errors.push(`missing exported resource ${resource}`);
const sources = await Promise.all(['src/resources/addresses.ts', 'src/resources/checkout.ts', 'src/resources/orders.ts'].map((path) => readFile(resolve(root, path), 'utf8')));
for (const source of sources) if (!source.includes("credentials: 'include'")) errors.push('protected resource does not include cookies');
if (errors.length) {
  console.error('VERIFY FAIL');
  for (const error of errors) console.error(`- ${error}`);
  process.exitCode = 1;
} else console.log('VERIFY PASS: package metadata, contract, resources, and credentialed calls');
