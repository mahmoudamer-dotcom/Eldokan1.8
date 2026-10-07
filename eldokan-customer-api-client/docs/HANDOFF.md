# Engineering Handoff — ElDokan Customer API Client 0.6.0

## Current contract snapshot

- Customer API Contract: v1
- Adapter release represented by the supplied handoff: 0.8.0
- Client package: 0.6.0
- OpenAPI: 32 paths and 133 schemas
- The `api.eldokan.com` server remains a planned hostname; use the current configured base URL until deployment is confirmed.

## Client resources

The client exposes typed Catalog/Home, Auth, Account, Address Book, Wishlist, Cart, Checkout and Orders resources. It retains complete `{ success, data, meta }` response envelopes. Public IDs remain opaque strings.

Use one browser instance per session and one isolated instance per server request. Keep authenticated calls behind the same-origin `/api/customer/*` proxy. The proxy forwards the Customer API session cookies and the allowlisted nonce/order-access headers, preserves response cookies, and never forwards the app's private recovery cookie upstream.

## Checkout and recovery

Checkout reads current state, quotes the submitted address and selected shipping method, creates one `chk_*` attempt, and places through that same attempt. The application stores the attempt and purchase input in an HttpOnly, SameSite recovery cookie before placement. A timeout is recovered by repeating the same placement with the same attempt and input.

After placement, store `ord_*` and any guest access capability in the same protected state before hosted navigation. Guest capabilities never enter a URL, logs, analytics, or browser-readable storage. Browser return is not payment confirmation; read the authoritative Order and display its normalized payment and Order statuses. Payment retry is explicit, uses the observed generation, and stays on the same Order.

The client does not call Paymob directly. Only follow a redirect when the API says `requires_redirect === true` and supplies a valid HTTPS hosted URL. Do not create another Order to retry payment.

## Verification scope

Regenerate contract types with `npm run generate:types`, compile the package with `npm run build`, and run the package checks on a machine with the required runtime tools. Live WordPress/WooCommerce, database, shipping/tax, Paymob Sandbox, callback and status-mapping compatibility still require staging acceptance before deployment.
