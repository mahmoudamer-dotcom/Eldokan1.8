# Production file map — Customer API 0.8.0 / Contract v1

| File | Responsibility | Current release notes |
|---|---|---|
| `eldokan-customer-api.php` | Plugin bootstrap/version | Version 0.8.0 and full Phase 2C class loading |
| `includes/class-eldokan-customer-api.php` | Routes, envelopes, health and response headers | Arabic category routes, minimal health, namespace errors, no-store |
| `includes/class-eldokan-customer-api-auth.php` | Registration, login, session, logout and security | Native WP cookies, CSRF, throttling, SameSite and credentialed CORS allowlist |
| `includes/class-eldokan-customer-api-account.php` | Private customer projection/update | Random `cus_*` IDs, names and optional canonical billing phone |
| `includes/class-eldokan-customer-api-wishlist.php` | Private Wishlist | Idempotent per-customer storage and ProductCard projection |
| `includes/class-eldokan-customer-api-cart.php` | Guest/customer Cart | Server ownership, CSRF, persistence, merge and opaque `cit_*` lines |
| `includes/class-eldokan-customer-api-language.php` | Language resolver and translation adapters | WPML isolated behind a replaceable adapter; native fallback |
| `includes/class-eldokan-customer-api-products.php` | Product queries and serialization | Adds tag filtering; preserves brand/category filters and existing sort behavior |
| `includes/class-eldokan-customer-api-taxonomies.php` | Public brand/tag collections | Localized pagination; Brands reuse the shared image projection |
| `includes/class-eldokan-customer-api-categories.php` | Category data and filter definitions | Public attribute shape, visibility and WPML SQL scope |
| `includes/class-eldokan-customer-api-sellers.php` | Public seller projection | Explicit `_eldokan_seller_user_id` only; dedicated public slug lookup |
| `includes/class-eldokan-customer-api-home.php` | Home read model | Localized titles; empty placeholders removed |
| `includes/class-eldokan-customer-api-cache.php` | Application cache and diagnostics | Effective-parameter keys; language/currency; new invalidation hooks |
| `includes/class-eldokan-customer-api-utils.php` | IDs, text/HTML, money, image, brand helpers | Shared Brand Thumbnail projection without internal ID leakage |
| `includes/class-eldokan-customer-api-addresses.php` | Private Address Book CRUD and ownership/default rules | Phase 2C production class |
| `includes/class-eldokan-customer-api-address-store.php` | Durable Address Book storage | Phase 2C production class |
| `includes/class-eldokan-customer-api-checkout.php` | Authoritative readiness/quote and checkout validation | Phase 2C production class |
| `includes/class-eldokan-customer-api-checkout-woo.php` | Native Woo calculation/shipping/payment adapter | Phase 2C production class |
| `includes/class-eldokan-customer-api-orders.php` | Durable attempts and ONE native Woo Order placement | Phase 2C production class |
| `includes/class-eldokan-customer-api-order-store.php` | Purchase ledger, immutable receipts/snapshots and Fulfillment storage | Phase 2C production class |
| `includes/class-eldokan-customer-api-checkout-recovery.php` | Read-only inspection and trusted ledger-only recovery | Phase 2C production class |
| `includes/class-eldokan-customer-api-payment-store.php` | Minimal durable hosted initiation/redirect/generation state | Phase 2C production class |
| `includes/class-eldokan-customer-api-paymob.php` | Native hosted Paymob adapter, safe callback admission/browser return boundary | Phase 2C production class |
| `includes/class-eldokan-customer-api-order-read.php` | Authoritative private Order detail/history and owner/Guest projections | Phase 2C production class |
| `includes/class-eldokan-customer-api-checkout-cli.php` | Trusted operator recovery commands | Phase 2C production class |
| `README.md` | Operator/developer overview | Current rules and scope |
| `START-HERE.md` | Arabic quick start | Handoff order and release gate |
| `CHANGELOG.md` | Version history | 0.8.0 completion plus labeled historical release entries |
| `docs/API-CONTRACT.md` | Public behavior rules | Filtering, IDs, visibility, seller, money |
| `docs/ENDPOINT-STATUS.md` | Route readiness | Complete 32 paths / 40 operations |
| `docs/HANDOFF.md` | Current state and risks | Deployment gate and next phase |
| `docs/TESTING.md` | Acceptance suite | Staging/live matrix |
| `docs/releases/V0.3.0-HOME-CONTENT.md` | Release notes | Managed Home hero/banner contract and runtime behavior |
| `docs/releases/V0.4.0-CATALOG-PHASE-1.md` | Release notes | Frontend-blocking catalog capabilities and Phase 2 boundary |
| `docs/releases/V0.5.0-PHASE-2A-IDENTITY-ACCOUNT-WISHLIST.md` | Release notes | Customer session/account/wishlist scope and security boundary |

## Developer Portal deliverable

The separate `eldokan-developer-portal-v1.8.0` ZIP contains:

- canonical `eldokan-customer-api-v1.openapi.yaml`;
- generated matching JSON and `.yaml.txt` browser fallback;
- updated API reference, status, changelog and handoff pages;
- `portal-manifest.json` identifying backend 0.8.0.

All listed includes are shipped runtime files. Test runtimes, baseline archives and checkpoint evidence are intentionally separate and not required production content. Existing docs/releases/ and docs/CHECKPOINT-*.md are historical/operator references, not current installation instructions.
