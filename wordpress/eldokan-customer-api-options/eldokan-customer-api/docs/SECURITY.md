# Security

## Current phase

Customer API 0.8.0 / Contract v1 implements native customer sessions, Guest/customer Cart, Checkout and Orders. Guest Cart ownership uses an opaque Secure/HttpOnly cookie with owner-bound CSRF; account mutations use the native customer session CSRF. Runtime/security behavior is unchanged by this release documentation cleanup.

No WooCommerce Consumer Key/Secret is required in browser code.

## Rules

- Never expose WooCommerce secrets to React.
- Never put production secrets in README/MD files.
- Public catalog routes must not mutate WordPress/WooCommerce state.
- Validate and sanitize route/query parameters.
- Do not leak raw private user fields in seller/customer projections.
- Error responses should not expose stack traces or SQL.
- Request IDs may be exposed; credentials/tokens may not.

## Customer session model

- Authentication uses WordPress-native session cookies; no JWT or long-lived browser token is introduced.
- On HTTPS the logged-in cookie is reissued with Secure, HttpOnly and SameSite=Lax attributes.
- Register/login/session return a short-lived, session-bound CSRF value. Logout, account updates and Wishlist mutations require it in `X-ElDokan-CSRF`.
- Credentialed CORS for Auth/Account/Wishlist reflects only the site origins plus explicit values added through `eldokan_customer_api_allowed_origins`.
- Login failures use one generic 401 response and failed attempts are throttled by hashed IP/email identity.
- Only the `customer` role is accepted by default. Seller/staff/admin sessions are not Customer Account sessions.
- Public customer IDs are random stored `cus_*` values; numeric WordPress user IDs, roles, capabilities and internal meta are never serialized.
- Authenticated responses are always `private, no-store` and never use the public catalog application cache.

## Current checkout, Orders and payment boundaries

- Credentialed native customer cookies remain the authentication model; no Bearer/JWT/customer API key. Existing native REST nonce transport is preserved. Exact trusted origins govern credentialed CORS.
- Address Book, Wishlist and Order history are customer-private. Guest Cart session authorizes that Cart, not account history.
- Order detail/payment requires the authenticated native Woo owner OR the exact Guest Order capability in `X-ElDokan-Order-Access`. Missing/foreign/invalid credentials share generic 404; public IDs/email/phone are not access credentials. A valid Guest capability never attaches the Order to an account and cannot turn a staff/seller session into a customer/Guest.
- Guest payment mutation additionally requires dedicated `guest_access.csrf_token` in `X-ElDokan-CSRF`; account/Cart CSRF must not substitute for it. Guest read needs the capability header; no Guest history list. Never put credentials in URLs, logs or analytics.
- Native Paymob 4.1.15 Accept TRANSACTION POST callback remains payment/HMAC authority. Browser return is not payment proof; read the protected Order afterward. No direct provider payment engine or frontend HMAC verifier is introduced; Flash profile is unsupported.
- Hosted initiation follows purchase COMMIT and placement-lock release. Recovery/retry keeps the SAME Order and observed generation; never create another purchase automatically after ambiguity. Fencing is not provider idempotency/cancellation.
- Customer phone is private optional profile data, not login/verification/Guest matching. Empty string clears; null is invalid; saved address and historical Order phone remain independent.

Seller mutations, Reviews and Seller Ratings remain separately reviewed scope.

Do not reuse seller authentication merely because Seller API already exists.

## Caching warning

Never use public shared caching for personalized/authenticated responses without explicit cache-scope design.

The application cache layer remains for public catalog models only; Account, Wishlist, Cart, Checkout and Orders bypass it.


Catalog responses currently send `Cache-Control: private, no-store` until CDN/browser caching is explicitly designed and tested with language and currency.
