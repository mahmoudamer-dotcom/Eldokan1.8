# Phase 2C — Checkpoint A3H hardening

Customer API source version **0.7.0**, Public Contract **v1**. A1+A2+A3 are code-complete for staging acceptance. Actual deployment acceptance is pending. This is not the final Phase 2C release. A4 has not started.

The sole authoritative implementation input is the supplied A3 archive, embedded unchanged at `checkpoint-a3h/baselines/CHECKPOINT-A3-CUSTOMER-API.zip`:

`01eec361c558620950aa30a23aa49736902e64e57e4b417ae585dc638f03d610`

This document supersedes the original A3 notes prohibition, manual-only fence instructions and blanket blog-table engine policy. Original A1/A2/A3 checkpoint reports and logs are historical evidence. Their unchanged archived sources remain included.

## Optional customer notes

Both `POST /checkout/attempts` and `POST /checkout/orders` accept optional `order_notes`. Send the same intended checkout fields in both calls; placement additionally sends `checkout_attempt_id`. Notes must be strings, valid UTF-8, at most 4,000 raw bytes and 1,000 sanitized Unicode characters. Invalid type, invalid UTF-8 or excessive length returns `invalid_order_notes` (422). Null is not a string. Existing CSRF/origin/owner validation still applies.

CRLF/CR becomes LF; WordPress `sanitize_textarea_field` removes unsafe markup/script content; outer whitespace is trimmed. Sanitized empty and missing notes have the same logical fingerprint, preserving A3 retries and prepared attempts without notes. A nonempty normalized note is part of the logical request hash. Same `chk_*` and same normalized note return the original receipt. A different normalized note returns `checkout_attempt_conflict` (409), including after completion. Adding a nonempty note to an attempt prepared without it is a different request and requires preparing a new attempt **before placement begins**.

WooCommerce receives `order_comments`; unchanged native `WC_Checkout::create_order` calls `set_customer_note`. Placement verifies the native persisted customer note. No arbitrary notes metadata or additional public response field is introduced. Native customer-note display remains WooCommerce's responsibility, with its normal escaping.

## Schema and durable proof

Schema option `eldokan_customer_api_order_schema` is **2**. Activation and normal WordPress plugin load use additive `dbDelta` installation. The only schema change is nullable `recovery_json longtext` on `eldokan_checkout_attempts`. Existing unique attempt/public-order/Woo mappings, fulfillment composite key and receipt composite key remain unchanged. No Orders are migrated; no rows are dropped or rewritten for conversion. Own-table columns, full unique keys, engines and schema version still fail closed. Automatic A3 migration is skipped in WP-CLI bootstrap; explicitly activate/update the plugin before using operator commands. Inspection itself never installs or upgrades schema.

After native creation, native order-processed hooks and successful native COD/stock processing, placement checks its original prepared invariants again and saves private recovery evidence in the **same transaction** as Order/mapping/fulfillments/receipts/result. Evidence includes version, attempt/public/internal mapping, owner and Cart HMACs, logical request/material identity, the safe original result, frozen business fields/addresses/notes/all item types/taxes/metadata (including extension fee snapshots), reduced-stock markers, Seller groups and exact line IDs, and a digest of the uncached authoritative physical Order/item/meta/reservation rows. The proof is HMAC-signed with the site secure-auth salt; edited/recomputed plain digests cannot manufacture a valid creation proof. Rotating that salt makes previously fenced signed proof fail closed and requires operator investigation, never bypassing signature validation. The physical-row proof prevents a cached Woo object from standing in for committed database evidence. Private evidence contains address data; it must receive the same database access/backup controls as Woo order data. It is never returned by the Customer API or CLI report.

Order metadata adds `_eldokan_customer_owner_binding` and `_eldokan_customer_cart_binding`, alongside the existing attempt/public-ID/address/flow markers. Seller/Fee hooks remain native; there is no second snapshot writer. Evidence generation fails closed if managed stock lacks the native per-line quantity marker or order reduced-stock flag. Third-party filters that deliberately bypass that stock behavior require a compatible reviewed integration; they cannot silently produce a completed A3H order.

Existing A3 completed receipts retain their original public result and remain retryable. Existing A3 prepared attempts without notes retain compatible logical fingerprints and gain proof when successfully placed through A3H. An existing ambiguous A3 processing row with no pre-recorded durable proof cannot acquire invented evidence: inspection reports `insufficient_durable_evidence` and leaves it fenced. That is an explicitly unresolved state, not a replay fallback or a deferred implementation.

## Trusted operator procedure

The core `ElDokan_Customer_API_Checkout_Recovery` service is independent of CLI. It has no REST route. WP-CLI registration occurs only when `WP_CLI` is true and its class exists; checkout works when CLI is absent. Access is trusted local/operator shell access to this site's WP-CLI, not a Customer account, URL or admin AJAX route. The optional core binding argument enforces caller-provided owner scope and returns the same 404 for foreign/unknown attempts.

1. Obtain the exact `chk_*` from the failed purchase/support request. Confirm site/path with normal WP-CLI deployment tooling. Do not request customer credentials, session tokens or a database password in the Customer API.
2. On an upgraded installation run:

   ```bash
   wp eldokan checkout-attempt inspect chk_<64-lowercase-hex>
   ```

   Inspection performs reads only: no lock acquisition, SQL writes, lifecycle hooks, schema changes or Cart mutation in the service. WordPress and installed plugins may independently perform their usual bootstrap work; the recovery command introduces none. It returns a minimal JSON operator report (attempt, public Order ID, ledger state, assessment, reason, changed=false), excluding address/owner secrets/internal Woo/Seller IDs.
3. Only a `recoverable` assessment permits:

   ```bash
   wp eldokan checkout-attempt reconcile chk_<64-lowercase-hex>
   ```

   Reconciliation takes the existing Cart lock then the existing global placement lock, starts a checked transaction, locks the ledger and relevant native Order/item/meta/fulfillment/receipt rows, reinspects committed evidence and changes **only** the attempt state/response/timestamp to completed. Lost COMMIT acknowledgement is checked against the durable ledger. No creation, COD, stock, line/order/fee hooks, Cart cleanup, fulfillment insert or receipt insert is called. Retry the original customer request with the original attempt afterward.
4. Repeat inspection/reconciliation if needed. An intact completed receipt is a no-op, including after legitimate later Woo status, stock restoration or fulfillment transitions. Conflicting multiple Order evidence or an invalid completed mapping is reported as conflict. Never overwrite that evidence to obtain a successful report.

| Assessment | Meaning | Action |
|---|---|---|
| `completed` / `already_completed` | Valid original receipt and unique matching mapping | No-op; original customer retry is safe |
| `recoverable` / `exact_committed_order_verified` | Exactly one mapped committed Order, complete proof and all frozen invariants match | Reconcile ledger only |
| `unresolved` / `no_committed_order` | No committed native Order can be proven, including an interrupted uncommitted transaction | Investigate; never automatically recreate |
| `unresolved` / `insufficient_durable_evidence` | A fenced historical/partial lifecycle lacks the required pre-recorded proof | Investigate native Order, deployment logs, backups and installed hooks; do not invent evidence |
| `unresolved` / `inspection_unavailable` or `reconciliation_unavailable` | Storage/read/transaction verification is unavailable | Repair the environmental fault, inspect again; retain the fence |
| `conflict` | Multiple Orders, inconsistent mapping, malformed ledger, changed immutable data, incomplete stock markers, mismatched financials/memberships/fulfillments/receipts | Stop automated recovery; investigate corruption/integration behavior |
| WP error `checkout_busy` / storage unavailable | Lock timeout, schema or storage admission failure | Bounded retry of inspect/reconcile after environmental correction |

CLI prints JSON and exits 0 for completed/recoverable, exits 2 for unresolved/conflict, and uses WP-CLI errors for malformed IDs/storage/usage errors. Extra arguments/flags, including `--force`, are refused. There is no force-create operation.

**Never** rerun `WC_Checkout::create_order`, COD payment processing, stock reduction, Seller/Fee hooks or fulfillment/receipt creation for a processing/fenced attempt. Never reset it to prepared, delete its receipts/ledger, manually fill fabricated proof, change mappings, or tell the customer to start another attempt for the same ambiguous purchase. Operator review uses existing native Woo/backoffice investigation and logs/backups; routine safe reconciliation requires no SQL editing. In unresolved/conflict cases, investigation is necessary and the service does not pretend to resolve an unprovable history. A fresh purchase decision must follow a documented independent operational resolution of the original case, never a recovery command's blind retry.

## Exact transaction participant policy

Replace `wp_` below with the site's actual blog prefix. All required tables must exist and be InnoDB on the same `$wpdb` connection/database. Autocommit must be enabled on entry. HPOS usage is detected through Woo `OrderUtil`, not assumed from the presence of backup tables.

| Mode/role | Required suffixes |
|---|---|
| Both modes: product/stock state and legacy/HPOS compatibility placeholders | `posts`, `postmeta` |
| Both modes: native notes and product visibility/taxonomy state | `comments`, `commentmeta`, `terms`, `term_taxonomy`, `term_relationships`, `termmeta` |
| Both modes: native Order lines and metadata | `woocommerce_order_items`, `woocommerce_order_itemmeta` |
| Both modes: product lookup and native stock holds | `wc_product_meta_lookup`, `wc_reserved_stock` |
| A3 ledger/fulfillments/consumption | `eldokan_checkout_attempts`, `eldokan_seller_fulfillments`, `eldokan_checkout_receipts` |
| HPOS authoritative, or legacy authoritative with Woo compatibility synchronization enabled | `wc_orders`, `wc_order_addresses`, `wc_order_operational_data`, `wc_orders_meta` |

Compatibility synchronization is detected by `woocommerce_custom_orders_table_data_sync_enabled=yes`. Both primary and backup Order rows are included in the physical recovery proof in synchronized mode. The plugin conditionally declares HPOS compatibility through Woo `FeaturesUtil`, while installed mode/extensions remain environmental acceptance gates.

Extension hooks may add required full table names through `eldokan_checkout_transaction_tables`. They cannot remove core requirements. In addition, the transaction-scoped WordPress `query` guard checks **every actual single-table write target** against the inspected engine map before execution. Thus core/options/usermeta/scheduler or third-party tables become required if actually written, without blocking an untouched unrelated MyISAM table. Any actual non-InnoDB/missing write target, DDL/implicit commit, nested transaction, stored procedure, unsupported multi-table write or unsupported SQL form fails closed. Standard native INSERT/REPLACE/UPDATE/DELETE forms are supported. Third-party hooks must use this same `$wpdb` connection/filter path; direct mysqli/PDO writes, separate connections and out-of-band side effects cannot participate in this transaction and must not be used for required purchase state. No table is automatically converted.

Known admission failures are checked before the processing fence and return `order_storage_unavailable`: after repairing the storage assumption, the same prepared attempt can retry. Failures after the committed processing fence remain ambiguous and must be inspected. The admission check is repeated immediately before START TRANSACTION. Transaction end is owned by the service; hook SQL cannot COMMIT or implicitly commit it. Native Woo still calculates all money and runs the accepted lifecycle. Recovery checks the exact signed post-COD native status, including an installed valid custom status, instead of imposing a new status policy.

## Lock and external work invariant

The existing prefix-scoped MySQL `GET_LOCK(...,10)` global placement lock serializes A3 attempts, together with owner Cart locks. It protects shared native stock/Cart/material transitions and prevents duplicate Orders across concurrent attempts. Locks remain connection-scoped and held to request shutdown; maximum named-lock acquisition wait is **10 seconds per lock**. Acquisition failure returns `checkout_busy` (503), invokes no creation/payment, and preserves the source Cart. Database row-lock waits depend on the configured MySQL timeout; they are distinct from the 10-second named-lock wait. A post-fence failure still follows recovery fencing.

Launch impact: only one A3 placement executes at a time on the site; approximate maximum placement throughput is the reciprocal of the observed serialized placement duration, not a promised numerical RPS. Queued requests may receive busy after 10 seconds. Measure duration/queue/busy rate with installed hooks on staging. Quote/read traffic is not globally serialized by a new policy; existing per-owner protection remains. No locking redesign is introduced.

The placement lock installs a `pre_http_request` guard that returns `checkout_external_work_locked` before WordPress HTTP transport runs. `assert_external_work_unlocked()` is a mandatory entry invariant for **every future provider adapter**, including non-WP transports. External payment/network work, especially Paymob initiation, must occur only after the transaction has ended and the placement lock has been verifiably released. Do not introduce raw cURL, socket, SDK or alternate-connection network calls in the locked phase or override the guard. Release errors retain the fence/guard. Current A3H has no provider invocation; native COD remains the only payment lifecycle. Native shipping/fee extensions that make remote calls during locked revalidation will be blocked and must be verified against this policy on staging.

## Local verification and staging

Run `npm ci` in the included locked `checkpoint-a1/test-runtime` and run `ELDOKAN_TEST_PHP=/path/to/native/php python3 checkpoint-a3h/run-verification.py` with native PHP+SQLite3. Logs/results are written only under `checkpoint-a3h/logs`; original checkpoint logs remain unchanged. Serial suites run on PHP-WASM 8.0/8.3 and native PHP 8.3; native-process tests cover simultaneous placement, lost response, killed uncommitted lifecycle, fresh-process recovery and concurrent operators. Adapters model WP/Order stores and legacy/HPOS/compatibility engines; they do not certify actual MySQL/HPOS or installed plugins.

See `CHECKPOINT-A3H-VERIFY.md` and `CHECKPOINT-A3H-STAGING-ACCEPTANCE.md` for exact results and environmental gates. Completed in code: all requested notes/recovery/CLI/table/lock behavior and preserved A1/A2/A3 core. Requires staging verification: actual WordPress/MySQL/Woo/HPOS/Admin-Seller hooks/COD/shipping-tax/browser/concurrent HTTP behavior. Intentionally deferred to A4+: Paymob initiation/provider callbacks, Order list/detail/guest retrieval and subsequent product milestones. No known A1/A2/A3 implementation TODO is classified as an environmental staging item. Stop at A3H.

Primary storage references reviewed: [Woo HPOS](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/), [native product datastore](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/data-stores/class-wc-product-data-store-cpt.php), [native HPOS datastore](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/Internal/DataStores/Orders/OrdersTableDataStore.php), and the unchanged pinned Woo Checkout/COD/stock sources in `checkpoint-a3/UPSTREAM-PROVENANCE.json`. Current upstream pages inform the storage review; they do not substitute for the installed-version acceptance checklist.
