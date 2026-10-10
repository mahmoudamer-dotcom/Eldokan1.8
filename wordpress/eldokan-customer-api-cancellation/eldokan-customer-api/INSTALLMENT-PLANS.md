# Checkout installment plans — local patch 0.8.5

Includes the 0.8.1 native shipping context fix and 0.8.2 payment choices.

## Install

Replace the active Customer API plugin with `eldokan-customer-api-0.8.5-installment-invoice-fix.zip`. Keep the original Paymob 4.1.15 plugin and checkout setup helper active. Do not activate two Customer API copies.

## 0.8.5 storage compatibility and invoice display

0.8.3/0.8.4 added an optional `installment` material digest but omitted updating the original reader, which required exactly nine digests. Consequently a saved installment attempt with ten fields was rejected as `order_storage_unavailable` at the start of placement, before the processing fence or native order creation. 0.8.5 permits exactly the nine original fields plus optional `installment`, retaining SHA-256 validation and rejecting all other fields. No SQL migration, row rewrite, attempt deletion or new order is needed. After replacement, use Recover the same checkout attempt.

Invoices now expose the persisted selected installment plan and, when recorded, `selected_quote` with duration/monthly amount from the browser widget. These display figures are explicitly estimates selected at checkout, not an approved bank schedule or merchant order fees. Final approval/financing charges remain owned by Paymob and the bank. WooCommerce totals remain authoritative.

The additive attempt/placement field `installment_display` has exactly `tenure` (1–120) and `monthly_amount` (positive minor units <= 1e12). It is accepted only with a selected installment plan. Display values never change totals, the intention amount or the selected plan. They are excluded from request fingerprints so an older prepared attempt can retain its original financial identity while gaining display information at placement. The original plan ID remains bound into the request/material digests. The BFF allows this display enrichment only for the same saved plan. Existing completed orders are not rewritten. An older order without saved display figures shows its plan ID only; duration/monthly figures are not invented.

In original Paymob settings:

1. Enable the actual Bank Installments integration in Payment Integrations. Its mode must match the merchant Test/Live configuration and its currency must be EGP.
2. In Affordability Widget, enable the widget and select that same bank installment integration (currently 4560065 in this store). Save. Merchant minimum amount settings still apply.
3. In ElDokan checkout setup, map Bank installments gateway to the same native integration and save.
4. Refresh shipping and payment in the storefront; choose Bank installments. The Paymob SDK shows actual available plans for the authoritative order total. Choose a plan in the widget before Confirm order and pay.

## Contract

0.8.4 isolates widget configuration errors from quotation. It reads saved merchant settings and the already initialized native gateway after bank availability has been validated by the existing payment-options code. It does not rerun gateway-availability filters or call Paymob widget integration-discovery methods for configuration. It performs no extra HTTP call during quote calculation. Explicitly save the same numeric integration in Paymob's Affordability Widget settings.

`installment_widget_status` reports a safe configuration code: `ready`, `quote_incomplete`, `gateway_unavailable`, `widget_disabled`, `integration_mismatch`, `public_key_unavailable`, `below_minimum`, `configuration_unavailable`, or `unavailable`. These statuses do not block COD/card quotes. No provider exception text or credentials are exposed.

The live public `/health` and categories endpoints returned HTTP 200 with API 0.8.3 on 2026-10-10 during investigation. This does not reproduce or resolve an authenticated quote timeout. The user's original connection-error cause remains unconfirmed without the failed request's response/error log. 0.8.4 is a configuration isolation improvement, not a claim that the remote timeout has been proven fixed.

`installment_widget` is null unless shipping/totals are calculable, the native installment gateway passes availability checks, the widget is enabled, its integration matches the mapped gateway, the currency is EGP and its configured minimum is met. When available it contains only `public_key`, `integration_id`, `amount` (minor units), and `currency`. No secret/API key is exported.

Attempt/placement accept additive `installment_plan_id` (positive numeric ID as a string), exclusively with `payment_method: paymob` and `paymob_option_id: bank_installments`. Plan discovery and displayed fees/monthly amounts come from the Paymob widget. No client supplied amount changes WooCommerce totals. Format validation and matching gateway/widget configuration precede order creation; final plan eligibility is validated by Paymob when the original native gateway creates the intention. This patch does not introduce a server-side Paymob plan-list endpoint or claim that a plan is provider-approved before intention creation.

The plan and its configured integration are bound into attempt material and persisted as private native order metadata. The existing native `paymob_intention_data` hook forwards `pre_selected_plan` only for the matching order/integration, including recovery attempts. Existing payment journals, retry fences, authenticated callbacks and stock guards are retained. Frontend refreshes invalidate the selected plan to require reconfirmation against the new quote.

The widget uses the same official CDN module as Paymob 4.1.15. It requires browser access to the Paymob/CDN domains. Native plugin availability and provider plan eligibility are respected; missing installment gateways are not fabricated. If the widget returns no plans for the total, use another method or inspect the merchant's Paymob configuration.

## Verification limits

PHP is unavailable in this workspace. This patch has not been executed in WordPress or used to initiate a payment. Validate in the merchant's test environment before live use, including plan selection, amount changes, intent preselection and recovery. The frontend payment remains hosted; card details are entered in Paymob.
