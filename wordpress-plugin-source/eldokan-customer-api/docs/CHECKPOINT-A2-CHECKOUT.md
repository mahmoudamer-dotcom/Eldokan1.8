# Customer API 0.7.0 partial — Checkpoint A2

Contract v1. A1 Address Book remains intact. This adds readiness and authoritative quotes only; no Order, checkout attempt, Fulfillment persistence, payment initiation or stock reservation exists here.

## Endpoints and security

- `GET /checkout`: current private Cart, validation, address requirements, authenticated saved addresses/default where present, native totals/rates/payment availability where calculable.
- `POST /checkout/quote`: request-only calculation. Restore `/cart` or `/checkout` first; include the returned `X-ElDokan-CSRF` and native credentials/cookies. Uses the existing customer nonce or guest-token-bound Cart CSRF and rate limiter. Origins must satisfy the existing credentialed CORS allowlist.

Both endpoints use existing `{success,data,meta:{request_id}}` and `{success:false,error:{code,message},meta}` envelopes, `private, no-store`, cache BYPASS and language adapter. Checkout was added to Auth's existing private-CORS path matcher; no new authentication system.

A quote body is one JSON object, either:

```json
{"address_id":"adr_<64 lowercase hexadecimal characters>","shipping_method_id":"shp_<64 lowercase hexadecimal characters>"}
```

or:

```json
{
  "address": {
    "first_name": "Hussein",
    "last_name": "Customer",
    "phone": "+201001234567",
    "email": "customer@example.test",
    "country": "EG",
    "state": "EGC",
    "city": "Cairo",
    "street_address": "1 Example Street",
    "company": null,
    "address_extra": null
  }
}
```

`shipping_method_id` is optional until multiple choices exist. For direct input, `country` defaults to EG and any other value is rejected. Use installed WooCommerce Egypt governorate codes, not guessed codes; this example requires the shown code to exist on the installation. All other required fields are mandatory, including billing/contact information for pickup or virtual products. Optional fields are nullable. Unknown input fields (including frontend prices/totals/seller/stock) and ambiguous address modes are rejected.

Saved address lookup searches only the authenticated book; foreign and unknown valid IDs both return 404 `address_not_found`. Guests cannot use saved IDs. Direct addresses are not persisted for any customer. GET may quote the authenticated default saved address; a guest's previous POST address is never implicitly retained.

## Public result

`data` includes:

- `cart`: `items`, `count`, `valid`, `owner_type`, `csrf_token`. Deleted/hidden/invalid selections keep their `cit_*` identities and quantities with safe unavailable placeholders. Existing public catalog `prd_*`, `var_*` formats remain compatible; no raw numeric native IDs appear as public ownership or shipping fields.
- `ready`, `issues`: each issue uses `code`, `message`, `blocking`, optional `item_id`. Blocking issues prevent readiness. Address/body/ownership/security errors use the standard HTTP error envelope.
- `address`: sanitized public fields; `id` only for saved addresses.
- `address_requirements`: EG, required field names, installed governorate codes, required flag.
- `saved_addresses`: A1 public list/default projection, empty for guests.
- `shipping_required`, `shipping_calculable`, `shipping_methods`, `selected_shipping_method`.
- `payment_methods`, `payment_availability_calculable`, `payment_required`.
- `totals`: `subtotal`, `shipping`, `tax`, `fees`, `discount`, `total`, `taxes_enabled`, `calculable`.
- `stock_reserved`: always false.

Money uses the existing project shape: `amount` is integer minor units, with `currency`, `decimals`, `formatted`. Subtotal/shipping/fees/discount exclude tax; `tax` is native aggregate tax and `total` is the native final result, including configured fees and discounts. Do not recompute final totals from public line prices. Unknown shipping, location-dependent tax and final totals are null; tax is zero when Woo taxes are disabled. Invalid carts have no authoritative complete-cart subtotal or final total. Payment unavailability can block readiness while the monetary total is still calculable.

Cart validation covers existing public visibility, purchasability/current price, variation parent and attributes, minimum/maximum purchase quantity, sold individually, stock and combined parent-managed variation stock. Canonical Seller comes only from parent `_eldokan_seller_user_id`; it must be a positive integer identifying an existing onboarded Seller role accepted by the existing catalog (`eldokan_owner`, `wcfm_vendor`, `seller`, `vendor`). Role membership validates the referenced account and never supplies ownership. No author/vendor-key fallback. Seller account data/meta/user IDs are omitted from the checkout Cart projection.

A1 persisted Cart has no price history. A2 records the last standard Cart projection's price in a separate bounded owner-keyed transient. When available, `price_changed` is a nonblocking review warning; native current price is always authoritative. Existing A1 carts and expired observations cannot yield a historical price-change comparison. Readiness still requires current prices/stock. Woo pricing hooks also refresh the public quote's line money fields. Quote does not change observations or source Cart membership.

A1 stores variation IDs rather than independently selected wildcard attributes. A wildcard or obsolete selection fails explicitly with `invalid_variation`; A2 never guesses the missing selection. A future explicit Cart selection contract is needed to support such wildcard products.

## Native Woo boundary

`ElDokan_Customer_API_Quote_Cart` inherits native `WC_Cart::calculate_totals()` and its `WC_Cart_Totals` engine, pricing/fee/tax/shipping hooks. Its constructor intentionally creates a `WC_Cart_Session` without `init()` and a fees API without persistent Cart hooks; its `get_cart()` never imports the browser Cart. Empty Cart is not calculated, avoiding native empty-cart update hooks.

A request-only `WC_Session` subclass, fresh unsaved `WC_Customer`, fresh `WC_Shipping`, and cloned product objects isolate the quote. All Woo globals are restored in `finally`, including on exceptions. Billing and shipping fields are explicitly set/cleared, preventing inherited native profile/session destination leakage. No customer save, native persistent Cart write, checkout entity or stock reservation.

Native `get_shipping_packages()` and `calculate_shipping()` supply actual zone rates. Physical products fail when shipping is disabled, EG is not a configured shipping country, any package lacks a supported rate, or selection changes. One rate per package forms a public whole-cart choice. Compatible choices support all-pickup or all-delivery bundles, bounded at 100; mixed pickup/delivery bundles are not launched. Multiple choices require explicit selection; one choice is selected automatically. Native choices are revalidated after recalculation.

Shipping IDs are keyed SHA-256 projections bound to address, source selections/quantities and current native rate costs. Raw method/instance/package IDs never leave the adapter. The amount on a method is the sum of actual selected native rate costs; final shipping/tax/total still come from WC_Cart. No invented rate or carrier API.

Default method classification: `local_pickup` → pickup; `flat_rate`/`free_shipping` → door delivery. Other installed methods must be explicitly classified with `eldokan_customer_api_checkout_shipping_type($default,$native_method_id)`. Safe branch-specific display names can be supplied through `eldokan_customer_api_checkout_shipping_name($default,$type,$native_rate_selection)`; the default names are Pickup From Branches and Door Delivery. Configure this for multiple branches whose generic labels/prices would otherwise be identical.

## Payment availability and launch caveat

Only the filtered native `get_available_payment_gateways()` result is inspected, after complete address/shipping/current Cart validation. Public concepts are COD and Paymob. Configuration, gateway descriptions, integration IDs and credentials are never serialized; public text is fixed and safe. No payment fields rendering or payment processing is called.

Official Paymob 4.1.14 source was inspected as a reference only, never executed/installed or used as an implementation baseline. It has standard `paymob`, inline `paymob-pixel`, configuration `paymob-main`, subscriptions, and generated classes extending `Paymob_Payment`. Defaults recognize standard and Pixel IDs; class identity handles generated Paymob gateways without guessing ID prefixes. Configuration/subscription classes are excluded. Explicit overrides are available via `eldokan_customer_api_checkout_gateway_map($native_id_to_public_concept)`.

Native Pixel availability is safely projected with `requires_redirect:false`; standard/generated hosted payment has true. When multiple native gateways map to one Paymob concept, the first available native gateway determines this availability-only projection. This is not a promise that A2 can execute either flow. The locked launch route for a future payment checkpoint remains the existing gateway's hosted redirect; an installation with only Pixel enabled needs explicit staging resolution before that checkpoint. A2 neither initiates Pixel nor creates a parallel provider implementation.

## Acceptance

See `checkpoint-a2/CHECKPOINT-A2-VERIFY.md` and retained logs. Local suites use production API/Auth/Cart/Address/Products plus unchanged upstream native Woo Cart/totals/fees/discount code; WP/customer/rates/tax lookup and calculation/gateways are controlled doubles. Real shipping zones, real gateway filtering, actual installed Woo/plugin versions, browser cookies/CSRF/CORS and hooks still require staging acceptance. Payment/provider tests are deferred. STOP after A2.
