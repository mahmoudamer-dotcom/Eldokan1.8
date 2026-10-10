> Historical original A3 contract. Current A3H supersedes notes acceptance, schema/recovery, transaction participants and operator procedures: see [CHECKPOINT-A3H-HARDENING.md](CHECKPOINT-A3H-HARDENING.md). The original input/report archive remains unchanged.

# Phase 2C Checkpoint A3 — placement core only

Customer API **0.7.0 partial A3**, Public Contract **v1**. The sole implementation input is the supplied A2 ZIP (SHA-256 `deccdda9d1cb3f04e8e70b347060fc6393660f1920d3d93419d24f177a247dbd`). A1 Address Book and A2 quote/calculation behavior are retained. This is not a final Phase 2C release or WordPress Live acceptance.

## Public placement contract

Both endpoints are private CSRF-protected mutations under `/eldokan-customer/v1`. Restore the Customer API Cart/session, obtain an A2 quote and select its safe whole-Cart shipping identifier. Send credentials and `X-ElDokan-CSRF`, exactly as for existing Cart/quote mutations.

`POST /checkout/attempts` accepts the complete intended COD checkout request. It validates and durably prepares it without creating an Order, reserving stock or processing a gateway:

```json
{
  "address_id": "adr_<64 lowercase hex>",
  "shipping_method_id": "shp_<64 lowercase hex>",
  "payment_method": "cod"
}
```

Use `address` instead of `address_id` for a validated direct Egypt address. Address fields and validation are the unchanged A2 contract. Saved addresses require the current customer. Exactly one address mode is required. `shipping_method_id` must be present: use the selected current quote identifier for physical products, or `null` for a Cart requiring no shipping. Successful preparation returns HTTP 201, within the standard envelope:

```json
{"checkout_attempt_id":"chk_<64 lowercase hex>","state":"prepared","stock_reserved":false}
```

`POST /checkout/orders` accepts **the same intended request** plus `checkout_attempt_id`. Placement returns HTTP 200. A different logical request on the same attempt returns `checkout_attempt_conflict` (409). JSON key ordering, trimmed text and omitted default optional address fields do not change the logical request. New address or shipping choices require a fresh quote and attempt. IDs are strictly validated; foreign and unknown valid attempts share the same 404.

Only native `WC_Gateway_COD` under `cod` is eligible. Arbitrary gateways mapped to the public COD concept cannot impersonate it. Paymob returns `payment_not_supported_in_checkpoint` (501); no Paymob initiation, provider calls, redirects, callbacks or webhooks exist. Other payment IDs are rejected. Money, tax, quantities, product IDs, Seller ownership, order keys, order notes and arbitrary fields cannot be supplied through these endpoints. Notes have not been unlocked in the supplied contract.

The standard success envelope contains only `order_id` (`ord_*`), `checkout_attempt_id`, native initial `status`, fixed safe COD projection, native `total`/`currency`, safe line name/quantity/subtotal/total/Seller summary, safe selected shipping summary and `created_at`. Money keeps the existing integer-minor-unit shape. Existing public `sel_*` catalog identity is used; no numeric Seller field is exposed. The success is an immutable placement receipt, so retry after a later cancellation still returns that original receipt. Future A4 Order reads will report current Order status.

Validation errors preserve the existing envelope/request ID and add `error.issues` on A3 endpoints only. Cart/address/Seller/price/stock/shipping/tax/fee/discount fingerprints are rechecked through A2 immediately before placement. Blocking A2 issues remain explicit; changed valid preparation returns 409 with category-specific `*_changed` issues. No previous quote is a price or stock authority.

## Durable model and transaction boundaries

Schema option `eldokan_customer_api_order_schema`, version **1**. Three prefix-scoped InnoDB tables install/upgrade through activation plus `plugins_loaded`, using `dbDelta` without dropping or converting historical data:

| Table suffix | Purpose | Database uniqueness |
|---|---|---|
| `eldokan_checkout_attempts` | Private owner/session binding, stable Cart identity, logical hash, nine material digests, state, random public Order ID, internal Woo mapping, persisted safe response, UTC timestamps | Primary `attempt_id`; UNIQUE `order_id`; UNIQUE nullable `woo_order_id` |
| `eldokan_seller_fulfillments` | One Seller's immutable identity/name and owned Woo line IDs, independent initial `pending` state, revision 0, UTC timestamps | Primary `(woo_order_id,seller_user_id)` |
| `eldokan_checkout_receipts` | Durable consumption of bought `cit_*` memberships, including recovery when Cart compaction/cache writes fail | Primary `(cart_owner_hash,item_id)` |

`chk_*` and `ord_*` each use independent `random_bytes(32)` and 64 lowercase hex characters, never sequential IDs. `ord_*` is allocated privately at preparation and exposed only with successful placement. Customer binding is HMAC of stable Cart identity plus the native logged-in session token; guest binding uses the opaque HttpOnly Cart cookie. Only owner-bound reads are permitted. No customer email matching occurs.

Schema version, required columns, full non-prefix UNIQUE keys and InnoDB engines are checked; errors/corruption fail closed. Placement requires autocommit on entry and all installed tables with this blog prefix to be InnoDB. The plugin does not silently convert tables. Deployments with nontransactional tables must resolve that incompatibility during staging.

MySQL connection-scoped named locks use hashed site-prefix/owner identities, a bounded 10-second wait and shutdown release. Cart reads that may prune, Cart mutations and login merge share the owner lock; merge locks are ordered. A placement lock also serializes different A3 buyers through final native stock validation/commit. Native Woo held-stock/reservation checks coordinate other Woo purchase flows. There is no custom inventory engine.

State progression is `prepared` → a **committed `processing` fence** → one native DB transaction for Woo creation/COD/stock/Seller snapshots/Fulfillments/receipts/result → `completed` and COMMIT. A completed retry reads its stored safe result **before consulting the now-empty Cart, address book, changed Seller, stock or gateway**. A failed/ambiguous in-progress attempt returns `checkout_recovery_required`; it never blindly recreates the Order or reruns payment/creation hooks. Database errors cannot become a success.

A lost response after COMMIT recovers the exact public result. An actual process kill inside an uncommitted transaction rolls back the controllable database effects, leaves the durable processing fence, preserves Cart/stock and requires store-side recovery. This deliberate boundary is not automatic recovery of every interrupted external plugin side effect. Store operators must inspect the attempt and Woo metadata through trusted backoffice tooling before resolving an ambiguous purchase; no recovery/admin mutation endpoint is introduced in A3. Never advise a customer to prepare another attempt for that unresolved purchase. Plugins using independent connections, implicit COMMIT/nested transactions, nontransactional side effects or persistent caches require explicit staging acceptance. Emails/network actions cannot be rolled back by this ledger; no exactly-once claim is made for them.

## Native Woo lifecycle and purchase history

The A2 orchestrator gains only an internal preparation capture of its request-scoped Woo Cart/customer/session/shipping. Public A2 results are unchanged. A3 temporarily reinstalls that context and restores the original Woo globals in `finally`. Native `WC_Cart::check_cart_items()` also checks held stock; authoritative native values, price/fee/tax/shipping calculation and Cart validation stay owned by Woo/A2.

`WC()->checkout()->create_order($validated_native_data)` creates exactly one Woo Order. No handwritten order engine or Seller child Orders are created. Native lifecycle includes:

- `woocommerce_checkout_create_order_line_item` and native fee/shipping/tax/coupon line hooks;
- `woocommerce_checkout_create_order`;
- `woocommerce_checkout_update_order_meta`;
- `woocommerce_checkout_order_created` (including native reservation listeners);
- `woocommerce_checkout_order_processed`, followed by native COD `process_payment()` and status/stock hooks.

A3 installs canonical line snapshots at the first line hook priority, so external fee listeners see them; existing listeners remain responsible for their own accepted commission metadata and formulas. No commission formula is implemented. A focused observer proves the native hook, canonical snapshot visibility and external metadata persistence. A native creation short-circuit that skips those hooks fails closed. The real installed ElDokan Admin/Seller sources are absent; their actual metadata/lifecycle compatibility remains staging acceptance.

Order billing/shipping fields are copied from the validated address, including phone/email where native fields apply. `_eldokan_customer_address_snapshot` freezes the full validated historical address. Each line stores `_eldokan_seller_user_id`, `_eldokan_customer_seller_snapshot` (canonical internal reference plus public identity/name), and internal Cart membership. Parent product canonical ownership is used for variations, with no author/vendor-meta fallback. Product ownership or Address Book edits/deletes never rewrite these snapshots. A3 verifies owner, markers, currency/payment/total, line membership/identity/quantity/money and canonical snapshots before persistence and after COD.

New Orders are distinguished by `_eldokan_customer_flow=phase2c-a3`, attempt/public-ID metadata and the ledger mapping. Legacy Orders are not queried for migration, status rewriting or Fulfillments. Existing backoffice Order editing remains outside this checkpoint; Customer API does not mutate historical snapshots.

Native COD decides the installed initial status, normally processing/on-hold as appropriate or native completion for a free total. Its private thank-you redirect/order key is discarded. Native empty-Cart hooks still run, while an A3-only request Cart flag disables destruction of an unrelated persistent Woo browser basket. Woo stock reduction/restoration and per-line/order reduced-stock markers are native. No manual decrement is added. Successful COMMIT creates durable Cart receipts; purchased IDs are hidden from the server-owned Cart even if physical compaction fails. New items have fresh IDs and remain usable. A failed transaction commits neither receipts nor stock effects and retains the source Cart. Same-attempt retry does not require that Cart.

## Acceptance and stop

See `checkpoint-a3/CHECKPOINT-A3-VERIFY.md`, logs and the Work State. Local native PHP and PHP-WASM serial suites plus parallel native PHP/SQLite/flock tests are distinct from actual WordPress, MySQL, HPOS, installed COD restrictions, Admin fee rules, Seller plugins, stock extensions, live shipping/tax, browser/CORS/cookies and installed statuses. All remain explicit staging gates. Stop at A3; A4 must be separately authorized.
