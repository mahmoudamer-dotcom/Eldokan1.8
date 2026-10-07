#!/usr/bin/env python3
from __future__ import annotations
import hashlib
import json
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
errors: list[str] = []

required = [
    'package.json', 'tsconfig.json', 'README.md', 'START-HERE.md', 'CHANGELOG.md',
    'MAHMOUD-FRONTEND-HANDOFF.md',
    'src/index.ts', 'src/client.ts', 'src/types.ts', 'src/filters.ts', 'src/errors.ts',
    'src/resources/health.ts', 'src/resources/home.ts', 'src/resources/products.ts',
    'src/resources/categories.ts', 'src/resources/search.ts', 'src/resources/brands.ts',
    'src/resources/tags.ts', 'src/resources/sellers.ts',
    'src/resources/auth.ts', 'src/resources/account.ts', 'src/resources/wishlist.ts', 'src/session.ts',
    'src/resources/cart.ts', 'src/resources/addresses.ts', 'src/resources/checkout.ts', 'src/resources/orders.ts',
    'src/generated/types.ts', 'openapi/eldokan-customer-api-v1.openapi.json',
    'docs/HANDOFF.md', 'docs/ARCHITECTURE.md', 'docs/SECURITY.md', 'docs/TESTING.md',
]
for rel in required:
    if not (ROOT / rel).is_file(): errors.append(f'missing {rel}')

package = json.loads((ROOT/'package.json').read_text(encoding='utf-8'))
if package.get('name') != '@eldokan/customer-api-client': errors.append('package name mismatch')
if package.get('version') != '0.6.0': errors.append('package version mismatch')
if package.get('private') is not True: errors.append('package must remain private')

spec_path = ROOT/'openapi/eldokan-customer-api-v1.openapi.json'
spec = json.loads(spec_path.read_text(encoding='utf-8'))
expected_paths = {
    '/health', '/home', '/products', '/products/{product_id}', '/products/lookup',
    '/categories', '/categories/{slug}', '/categories/{slug}/filters',
    '/brands', '/tags', '/sellers/{seller_id}', '/search/suggestions',
    '/auth/register', '/auth/login', '/auth/session', '/auth/logout', '/me',
    '/wishlist', '/wishlist/items', '/wishlist/items/{product_id}',
    '/cart', '/cart/items', '/cart/items/{item_id}',
    '/me/addresses', '/me/addresses/{address_id}', '/checkout', '/checkout/quote',
    '/checkout/attempts', '/checkout/orders', '/orders', '/orders/{order_id}',
    '/orders/{order_id}/payment'
}
if set(spec.get('paths', {})) != expected_paths:
    errors.append('OpenAPI path set does not match Customer API Contract v1 (32 paths)')
if len(spec.get('components', {}).get('schemas', {})) != 133:
    errors.append('OpenAPI schema count is not 133')
schemas = spec.get('components', {}).get('schemas', {})
brand = schemas.get('Brand', {})
if 'image' not in brand.get('required', []): errors.append('Brand image is not required')
if not brand.get('properties', {}).get('image', {}).get('nullable'): errors.append('Brand image is not nullable')
for schema in ['HomeCta', 'HeroSlide', 'PromoBanner']:
    if schema not in schemas: errors.append(f'missing Home schema: {schema}')

source_text = '\n'.join((ROOT/'src'/p).read_text(encoding='utf-8') for p in ['index.ts','client.ts','resources/products.ts','resources/categories.ts','resources/search.ts','resources/brands.ts','resources/tags.ts','resources/sellers.ts','resources/auth.ts','resources/account.ts','resources/wishlist.ts','resources/cart.ts','resources/addresses.ts','resources/checkout.ts','resources/orders.ts','session.ts'])
for raw in ['wp-json', 'woocommerce', 'wpml_', 'consumer_secret', 'application_password']:
    if raw.lower() in source_text.lower():
        errors.append(f'frontend client source leaks forbidden backend detail: {raw}')

# Expected methods/resources exist.
index = (ROOT/'src/index.ts').read_text(encoding='utf-8')
for token in ['HealthResource','HomeResource','ProductsResource','CategoriesResource','SearchResource','BrandsResource','TagsResource','SellersResource','AuthResource','AccountResource','WishlistResource','CartResource','AddressesResource','CheckoutResource','OrdersResource']:
    if token not in index: errors.append(f'missing resource: {token}')

for schema in ['CustomerAccount','AuthSessionResponse','WishlistResponse','WishlistMutationResponse']:
    if schema not in schemas: errors.append(f'missing Phase 2A schema: {schema}')
for schema in ['Cart','CartItem','CartResponse','CartMutationResponse']:
    if schema not in schemas: errors.append(f'missing Phase 2B schema: {schema}')
for schema in ['Address','AddressCreate','Checkout','CheckoutAttempt','Placement','OrderDetail','PaymentResponse']:
    if schema not in schemas: errors.append(f'missing Phase 2C schema: {schema}')
auth_source = (ROOT/'src/resources/auth.ts').read_text(encoding='utf-8')
account_source = (ROOT/'src/resources/account.ts').read_text(encoding='utf-8')
wishlist_source = (ROOT/'src/resources/wishlist.ts').read_text(encoding='utf-8')
cart_source = (ROOT/'src/resources/cart.ts').read_text(encoding='utf-8')
if "credentials: 'include'" not in auth_source + account_source + wishlist_source:
    errors.append('authenticated resources do not include credentials')
if 'requireCsrfToken()' not in auth_source + account_source + wishlist_source:
    errors.append('authenticated mutations do not require in-memory CSRF')
if "credentials: 'include'" not in cart_source or 'ensureCsrf' not in cart_source:
    errors.append('Cart does not restore guest/customer session CSRF automatically')

# No obvious secrets.
for path in ROOT.rglob('*'):
    if not path.is_file() or 'dist' in path.parts:
        continue
    if path.name in {'.env', '.env.local'}:
        errors.append(f'forbidden env file: {path.relative_to(ROOT)}')
    if path.suffix.lower() in {'.ts','.js','.mjs','.json','.md','.py'}:
        text = path.read_text(encoding='utf-8', errors='ignore')
        if re.search(r'(?i)(consumer_secret|client_secret|api[_-]?key)\s*[:=]\s*["\'][A-Za-z0-9_-]{16,}', text):
            errors.append(f'possible secret in {path.relative_to(ROOT)}')

sha = hashlib.sha256(spec_path.read_bytes()).hexdigest()
print(f'OpenAPI SHA256: {sha}')
if errors:
    print('VERIFY FAIL')
    for e in errors: print('-', e)
    sys.exit(1)
print('VERIFY PASS')
