# Customer cancellation — local patch 0.8.6

Includes shipping context, native payment choices, installment widget, invoice display and the 0.8.5 saved-attempt compatibility fix.

## Install and storefront

Replace the active Customer API with `eldokan-customer-api-0.8.6-order-cancellation.zip`. Keep just one active Customer API. No schema migration or history rewrite is required. Deploy the matching storefront changes.

Orders are reached through Account → My orders at `/account/orders`; the orders icon was removed from the navbar. Existing `/orders` and individual order links are retained for saved URLs and guest recovery. Account order list cards include a cancellation panel without opening order details, a required reason, optional explanation (required for Other), a review step and an explicit confirmation button. No status is changed optimistically.

## Contract

Authenticated order detail adds `cancellation` with `available`, `mode` (`direct` or `request`), `state`, `reason_code`, `details`, and `requested_at`. Order ownership and account/guest authorization use the existing private order access implementation.

`POST /orders/{order_id}/cancel` accepts `reason_code` (`changed_mind`, `ordered_by_mistake`, `delivery_time`, `found_better_price`, `other`) and optional `details` (<=500 Unicode characters, <=2000 input bytes). Account mutations require the account CSRF token; guest API mutations require their dedicated order credential and CSRF. Browser guest capabilities remain protected by existing BFF rules; this storefront panel is provided on account order list cards.

Allowed native stages are pending/on-hold/processing, mapped to pending_payment/processing, with all seller fulfillment rows still pending. Shipped/delivered/collected/completed/cancelled/refunded/failed stages are excluded. Configure verified native status mappings and update fulfillment state when dispatching; third-party shipment tools must reflect dispatch in the store's authoritative order/fulfillment state.

Eligible unpaid native COD orders change to cancelled through WooCommerce `update_status`; native Woo stock/status hooks run. A resumable `cancelling` metadata stage handles a failed status save, and repeated successful requests do not repeat native status/stock transitions.

Paid or Paymob orders create a cancellation request for staff review; the native order/payment status is preserved. No refund, Paymob session cancellation, or refund completion is fabricated. The stored reason appears in private order metadata and an internal Woo order note. Staff review uses existing Woo order management and the provider's refund/cancellation procedures. Additional Paymob payment initiation/recovery through this API is blocked while the request is pending. Previously issued provider sessions and authenticated callbacks are not disabled or bypassed.

Staff can decline a pending cancellation request using the Woo order actions dropdown (requires manage_woocommerce; Woo owns its admin form/nonce). Declining preserves the reason, records an internal note, displays the review outcome to the customer and unblocks API payment recovery. Approvals use native cancellation/refund workflows after provider review; cancelled/refunded native statuses take precedence over the saved request marker.

Cancellation uses the same connection-scoped payment lock as payment initiation and re-reads the native order before checking eligibility. This serializes API payment/cancellation operations; external fulfillment/admin/provider callbacks are not governed by this API lock. Repeating a successful cancellation/request returns current order detail.

## Verification limits

Frontend TypeScript compilation and targeted ESLint passed. PHP is unavailable locally; no order has been cancelled, no refund initiated and no payment executed by the coding agent. WordPress runtime verification is outstanding.
