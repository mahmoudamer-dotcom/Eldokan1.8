# Mahmoud frontend handoff — Client 0.6.0 / Backend 0.8.0 / Contract v1

This is an implementation handoff, not evidence of frontend implementation or Live acceptance. Portal 1.8.0 and the bundled OpenAPI describe the frozen API. Keep each public identifier as an opaque string.

1. **Upgrade the Client.** Install the delivered private `@eldokan/customer-api-client` 0.6.0 package and import `createEldokanCustomerApiClient`. Keep one configured client per application/session context. Use the resource methods below; success methods return the complete `{success,data,meta}` envelope.

2. **Keep the existing same-origin proxy where used.** Preserve `/api/customer/*` as the BFF/proxy boundary. Configure `baseUrl` to the absolute origin plus that prefix. Forward permitted cookies, response Set-Cookie, JSON, request IDs, and the protected `X-ElDokan-CSRF`, `X-WP-Nonce`, `X-ElDokan-Order-Access` headers without logging sensitive values. Do not bypass Customer API with raw Woo commerce calls. If direct cross-origin transport is deliberately configured, use credentialed cookies and the exact trusted origin allowlist; do not invent wildcard credentialed CORS or token authentication.

3. **Keep the browser fetch fix.** Transport uses `Reflect.apply(this.fetchImpl, globalThis, [url, init])`. Never change it to a property-bound native fetch invocation. Both injected and default fetch have regression coverage. Private methods use `credentials: 'include'`; CSRF remains in memory. On refresh restore `auth.session()` for accounts or `cart.get()` / `checkout.get()` for Cart ownership.

4. **Implement Account screens.** Use `auth.register/login/session/logout`, `account.me/update`, `addresses.list/create/update/remove`, `wishlist.get/add/remove`. Customer phone is always a string: `""` is empty and clears; omission preserves; null is invalid. Registration phone is optional at the backend; the UI may require it as UX policy. Respect 32 ASCII characters and six digits for nonempty values. Phone does not imply verification, login, OTP, account matching or Guest attachment. Do not copy it automatically into an address. Saved addresses have their own required phone, country EG and installed state code. The book has at most 20 addresses. Keep phone and all account responses private/no-store. On phone storage error reload profile; requested names may already have been written. A registration storage error may leave the email account created; use email login rather than inventing phone recovery.

5. **Implement Products and variations.** Preserve `home`, `categories`, category filters, `brands`, `tags`, `sellers`, `products.list/lookupBySlug/get`, `search.suggestions`. Read advertised `var_*` and `VariationSelection` values; render attributes from those selections. Cart add accepts public product/variation IDs and quantity, not arbitrary attribute overrides. Never guess wildcard or missing variation choices. IDs, including catalog suffixes, are never converted to numeric Woo IDs.

6. **Implement Cart through the Client.** `cart.get/add/update/remove` support Guest and Customer sessions. Show returned current price, stock and issues; frontend values do not authorize a sale. Duplicate adds return `changed:false` and do not increase quantity; update sets exact quantity, 1..999. Membership survives language changes. Login merges compatible lines within backend limits. No stock is reserved here.

7. **Implement Checkout in sequence.** Read checkout, submit one saved address ID (customer only) or a direct address, review quote, select a currently advertised shipping choice and eligible payment method, prepare once, then place using the retained `chk_*`. Direct addresses do not save to the Address Book. Shipping must be explicit (current `shp_*`, or null when shipping is not required). Totals may be null; `ready:false` and issues are meaningful HTTP 200 outcomes. Backend prices, stock, Seller, tax, shipping, fees, discounts and final total are authoritative.

```ts
const state = await eldokanApi.checkout.get({lang:'en'});
const quote = await eldokanApi.checkout.quote({address}, {lang:'en'});
// Review ready/issues and select actual quote.data shipping/payment choices.
const purchase = {address, shipping_method_id:selectedShippingId, payment_method:'paymob' as const};
const attempt = await eldokanApi.checkout.createAttempt(purchase);
const checkoutAttemptId = attempt.data.checkout_attempt_id;
// Retain this exact ID for retry/recovery of this purchase before the next request.
const placed = await eldokanApi.checkout.placeOrder({...purchase, checkout_attempt_id:checkoutAttemptId});
const orderId = placed.data.order_id;
```

The UI must retain the attempt across a refresh/uncertain placement. Use app-controlled recovery state with no sensitive capability in a URL. Do not automatically create another attempt or Order after network timeout, uncertain COMMIT or payment follow-up failure. A deliberate corrected purchase is a separate user-reviewed action only when recovery confirms no committed purchase.

8. **COD.** Use the backend placement receipt for confirmation and subsequent authoritative Order detail for current state. Placement `status` is the immutable native placement-time Woo status; Order detail `status` is the normalized live status. Native COD paid semantics are not independent physical cash-collection confirmation.

9. **Paymob hosted flow.** Save `ord_*` BEFORE navigation. For Guest placement retain `guest_access.credential` and its dedicated CSRF securely before leaving. `payment` can be a normal Payment or a minimal follow-up issue; do not assume `generation` exists on that minimal variant. Redirect only if `requires_redirect === true` and `redirect_url` exists; consume only the backend validated HTTPS native hosted URL and never log/cache/share it. Backend COMMIT and lock release precede initiation. Support pending state, delayed callback, failed authoritative state, retryable and ambiguous initiation.

Frontend → Customer API → ONE Woo Order → native Paymob 4.1.15 hosted initiation → Paymob → native Accept TRANSACTION POST callback → authoritative Woo state → guarded browser return → protected Order detail. Browser return is NOT payment confirmation. Never infer paid from `success=true` or any query/transaction parameter. On return call `orders.get(orderId, accessOptions)` and render `payment_status` plus normalized `status`. Unknown/pending is not failed. Only the audited safe Accept TRANSACTION POST profile is supported; Flash support is not claimed. Actual merchant compatibility must pass staging.

10. **Recover/retry on the SAME Order.** Call `orders.payment(orderId, {}, accessOptions)` to recover current state. If the issue is `payment_retry_confirmation_required`, show explicit confirmation and use the returned observed `generation`:

```ts
const current = await eldokanApi.orders.payment(orderId, {}, accessOptions);
// After user explicitly confirms a new initiation:
const retry = await eldokanApi.orders.payment(orderId,
  {retry:true, expected_generation:current.data.generation}, accessOptions);
```

Never automatically increment or replace the generation. A stale generation cannot authorize another initiation. Valid unexpired cached sessions are reused even on explicit retry. Fencing does not guarantee provider idempotency or cancel earlier sessions. For a minimal placement follow-up, first read the same Order and recover its payment to obtain current fields. Do not create another purchase just to retry Paymob.

11. **Guest Orders.** Keep the exact capability plus Guest Order CSRF in app-controlled protected state; prefer the intended server-side BFF/session mechanism across hosted navigation. Do not expose them in URLs, return queries, analytics, console, errors or third-party storage. This client does not persist or log them. Explicitly pass:

```ts
const accessOptions = {guestAccess:{
  credential:guestAccess.credential,
  csrfToken:guestAccess.csrf_token
}};
await eldokanApi.orders.get(orderId, accessOptions);
await eldokanApi.orders.payment(orderId, {}, accessOptions);
```

Read sends `X-ElDokan-Order-Access`; payment sends it AND dedicated `X-ElDokan-CSRF`. Account CSRF must not replace Guest CSRF, even while logged in. One capability authorizes exactly one Guest Order; no Guest history list. Login/email/phone does not attach it. If credentials were lost but original checkout ownership remains, repeat placement with the SAME `chk_*` and identical purchase for receipt/credential redelivery. Otherwise provide support guidance; no guessed identity recovery.

12. **Account Orders.** `orders.list({page,perPage,lang})` is authenticated only, page 1..10000 and perPage 1..50 (default 20). Pagination is `data.pagination`; catalog pagination is in `meta`. `orders.get(orderId)` reads an owned mapped Phase 2C Order; legacy Woo Orders are omitted. Stable statuses: pending_payment, processing, awaiting_pickup, shipped, delivered, cancelled, failed, refunded. Fulfillment statuses are independent. `completed` is not assumed delivered: custom/completed mappings require verified backend configuration; otherwise reads fail safely. Order lines, address and Seller snapshots are historical; current account phone does not rewrite them. Never expose raw Woo IDs/keys or private commission.

13. **Implement explicit error UX.** Inspect `EldokanClientError.code`, `issues`, `status`, `requestId`; also inspect successful readiness/payment issues. Keep request ID for support; do not include tokens, profile phone or hosted URL in debug output.

| Code / issue | UI action |
| --- | --- |
| authentication_required | Restore/login customer session; retain pending purchase identifiers. |
| cart_session_required | Restore the current Cart via cart.get; never replace an uncertain purchase. |
| invalid_csrf_token | Restore correct account/Cart token; Guest payment needs its dedicated token. Retry only intended mutation after recovery. |
| origin_not_allowed | Correct trusted proxy/CORS origin configuration; do not bypass protection. |
| invalid_phone | Show field validation; no null, country-prefix guessing or stripping letters into a phone. |
| phone_storage_unavailable | Reload profile / recover email account, show temporary service issue. |
| checkout_changed | Refresh quote and ask user to review changed price/stock/address/shipping. Resolve prior purchase state before a deliberate new attempt. |
| checkout_attempt_conflict | Keep existing chk_*; explain the attempt belongs to different input, recover/review rather than silently replace. |
| checkout_recovery_required | Block automatic new purchase; retain chk_*/ord_* and direct store support to durable recovery. |
| checkout_busy | Show processing/try-later; preserve identifiers, no automatic second Order. |
| payment_retry_confirmation_required | Obtain current generation and explicit user confirmation for SAME ord_* retry. |
| payment_initiation_unavailable | Explain initiation is uncertain; protected read and deliberate fenced recovery, not purchase failure. |
| order_followup_unavailable | Purchase may be committed; retain/recover SAME ord_*/chk_*. |
| payment_not_eligible | Read authoritative Order; do not force payment, switch COD or recreate it. |
| paymob_unavailable / paymob_hosted_required | Show currently unavailable hosted payment; keep existing purchase, stage gateway configuration. |
| payment_storage_unavailable | Retain same Order and show temporary recovery issue, no new purchase. |
| order_not_found | Generic unavailable/unauthorized Order; do not reveal another owner or attempt email/phone matching. |
| order_read_unavailable | State cannot be read safely; show pending/unknown and support reference, not “payment failed”. |

14. **Remove/avoid prohibited shortcuts.** Remove custom direct Paymob prototypes, direct provider API calls, frontend HMAC verification, payment success based on return query parameters, raw WooCommerce commerce API calls, raw numeric Woo IDs, frontend authoritative totals/stock, and another Order as payment retry. No JWT/Bearer/API-key customer authentication. No speculative shipping/refund/review/Seller mutation APIs.

Before Live, real staging still covers WordPress/Woo, MySQL/MariaDB, HPOS, Admin/Seller hooks, shipping/tax, browser cookies/CORS/CSRF, Paymob 4.1.15 Sandbox, Accept TRANSACTION merchant compatibility, expiry and verified status mappings. Seller Fulfillment mutation compatibility is a separate phase. This run does not code frontend, stage or deploy.
