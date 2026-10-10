# Phase 2C Checkpoint A1 — Address Book only

Customer API source version **0.7.0**, public namespace **v1**. This checkpoint is deliberately partial: it adds saved customer addresses to the accepted Phase 2B API 0.6.0. It does not implement Checkout, Orders, Seller Fulfillment, payment initiation, profile phone, or frontend changes. Client 0.5.0 and Portal 1.7.0 remain unchanged; their schemas do not include Address Book yet.

## Authentication and response contract

Use the existing native customer session cookie (`credentials: include`). Obtain `csrf_token` from the existing authenticated session endpoint and send `X-ElDokan-CSRF` on POST/PATCH/DELETE. Reads require a customer session but not CSRF. Noncustomer roles and guests receive `authentication_required` (401). Origins must pass the existing configured origin allowlist; approved origins retain credentialed CORS, `Vary: Origin`, and the existing header/method allowances.

Every route uses the existing envelope and `Cache-Control: private, no-store`, cache `BYPASS`. Neither internal owner IDs, table names, revisions, nor metadata are response fields.

| Method | Path | Success data | Status |
| --- | --- | --- | --- |
| GET | `/me/addresses` | `{items: Address[], count: number, default_address_id: adr_* \| null}` | 200 |
| POST | `/me/addresses` | `Address` | 201 |
| PATCH | `/me/addresses/{address_id}` | `Address` | 200 |
| DELETE | `/me/addresses/{address_id}` | `{deleted: true, id: adr_*, default_address_id: adr_* \| null}` | 200 |

Example POST body (governorate code must be recognized by the installed WooCommerce Egypt country definition):

```json
{
  "first_name": "Hussein",
  "last_name": "Customer",
  "phone": "+20 100 123 4567",
  "email": "customer@example.test",
  "company": null,
  "country": "EG",
  "state": "EGC",
  "city": "Cairo",
  "street_address": "1 Test Street",
  "address_extra": null,
  "is_default": true
}
```

`Address` contains exactly `id` and the eleven fields shown above. ID is `adr_` plus 64 lowercase hexadecimal characters from `random_bytes(32)` (256 bits); it is stable across updates and does not encode a numeric ID or creation sequence. IDs are opaque and customer-scoped: every read and write starts from the authenticated customer book, never from a globally looked-up address.

POST requires nonempty string values for `first_name`, `last_name`, `phone`, `email`, `country`, `state`, `city`, `street_address`. Country must be exactly `EG`; state must be a key of `WC()->countries->get_states('EG')`. No separate mapping or fallback governorate is invented. Missing Woo governorates returns 503; invalid codes return 422. Phone accepts ASCII digits, optional leading `+`, spaces, parentheses and hyphens, with at least six digits; this is format validation, not phone verification. Email uses WordPress `is_email`.

Limits in UTF-8 **bytes**: first/last name 100 each, phone 32, email 254, company/city 150 each, country 2, state 32, street/extra 500 each. Text is sanitized. `company` and `address_extra` default to null and accept a string or null. `is_default` is optional and must be a JSON boolean. PATCH accepts a nonempty subset, preserves omitted fields, and rejects blank/null required fields. Unknown fields, owner IDs, storage keys, public `id`, and attempts to override `address_id` in the body are rejected. The mutation target is read only from URL parameters.

Malformed IDs return `invalid_address_id` (400). A correctly formed foreign or missing address returns the same `address_not_found` (404), including repeated DELETE. Public errors do not disclose SQL details. Address Book is bounded to 20 addresses per customer (422 on the 21st).

## Default behavior and ordering

- A customer's first saved address always becomes default, even if POST explicitly supplied false.
- Later POSTs default to false. Explicit true clears any previous default atomically.
- PATCH true makes the selected address the only default. PATCH false clears it if it was default, permitting an explicit no-default state.
- DELETE of the default chooses the lexicographically smallest remaining `adr_*` ID as the new default. DELETE of the final address leaves null. Deleting a nondefault address preserves the current default or no-default state.
- List order is ascending public ID, independent of DB row order. Updates preserve the ID.

## Schema and automatic upgrade

Private table: `{$wpdb->prefix}eldokan_customer_address_books`.

| Column | Definition | Purpose |
| --- | --- | --- |
| `customer_id` | unsigned bigint, PRIMARY KEY | One book for one authenticated customer; DB-enforced unique row |
| `revision` | unsigned bigint, NOT NULL, default 0 | Compare-and-swap revision |
| `addresses_json` | longtext, NOT NULL | Bounded address array; no order data |

Schema marker: option `eldokan_customer_api_address_schema`, explicit version `1`. Activation runs `install`; `plugins_loaded` priority 5 runs `maybe_upgrade` when marker differs. WordPress `dbDelta` installs/upgrades non-destructively; required columns and the full unique customer primary key are checked before recording version 1. No manual SQL is required for a normal upgrade. There are no DROP/TRUNCATE statements, order references, numeric public IDs, or cleanup-on-deactivation/uninstall hooks.

A list on an untouched customer returns an empty array without inserting a row. Mutations initialize a book with `INSERT IGNORE` and atomically update the whole bounded JSON array using `WHERE customer_id = ... AND revision = ...`; a competing write causes the operation to be reapplied to the new row, up to eight attempts. Exhausted contention returns `address_book_busy` (409). This avoids lost updates and split default changes without relying on read/modify/write user metadata or process-local locks. InnoDB is requested for the table; normal WordPress/MySQL deployment remains to be verified.

Address IDs are unique within the customer book, with collision detection before append; independently generated 256-bit random IDs are used across books. The public lookup always includes authenticated ownership, so no global address index is needed. The primary key is the index for every book lookup/update.

Saved addresses are not linked to Woo orders. This checkpoint cannot modify historical orders. A later Checkout checkpoint must **copy** the address into the order's historical snapshot and never resolve order history through a mutable Address Book reference. Guest Checkout, when implemented later, must not save addresses implicitly.

## Verification and deployment limits

See `CHECKPOINT-A1-VERIFY.md` at checkpoint root for actual results. PHP runtime tests execute these production classes and the existing auth/route methods with WordPress/Woo/schema doubles and a real SQLite adapter. They are not a real WordPress installation, real WordPress dbDelta, MySQL race acceptance, or provider tests. Existing regression verifiers are static checks and small executable models, not Live evidence.

Before enabling in WordPress staging/Live, verify automatic upgrade with the real database/prefix and dbDelta, repeated activation preserving addresses, authenticated cookie/nonces and configured CORS from the browser, two real customer accounts, governorate values from installed WooCommerce, concurrent CRUD/default writes, and normal baseline screens/routes. No Live deployment or Paymob call was performed.
