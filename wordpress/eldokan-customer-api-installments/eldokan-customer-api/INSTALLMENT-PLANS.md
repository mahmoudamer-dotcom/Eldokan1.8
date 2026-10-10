# Checkout installment plans — local patch 0.8.3

Includes the 0.8.1 native shipping context fix and 0.8.2 payment choices.

## Install

Replace the active Customer API plugin with `eldokan-customer-api-0.8.3-installment-plans.zip`. Keep the original Paymob 4.1.15 plugin and checkout setup helper active. Do not activate two Customer API copies.

In original Paymob settings:

1. Enable the actual Bank Installments integration in Payment Integrations. Its mode must match the merchant Test/Live configuration and its currency must be EGP.
2. In Affordability Widget, enable the widget and select that same bank installment integration (currently 4560065 in this store). Save. Merchant minimum amount settings still apply.
3. In ElDokan checkout setup, map Bank installments gateway to the same native integration and save.
4. Refresh shipping and payment in the storefront; choose Bank installments. The Paymob SDK shows actual available plans for the authoritative order total. Choose a plan in the widget before Confirm order and pay.

## Contract

`installment_widget` is null unless shipping/totals are calculable, the native installment gateway passes availability checks, the widget is enabled, its integration matches the mapped gateway, the currency is EGP and its configured minimum is met. When available it contains only `public_key`, `integration_id`, `amount` (minor units), and `currency`. No secret/API key is exported.

Attempt/placement accept additive `installment_plan_id` (positive numeric ID as a string), exclusively with `payment_method: paymob` and `paymob_option_id: bank_installments`. Plan discovery and displayed fees/monthly amounts come from the Paymob widget. No client supplied amount changes WooCommerce totals. Format validation and matching gateway/widget configuration precede order creation; final plan eligibility is validated by Paymob when the original native gateway creates the intention. This patch does not introduce a server-side Paymob plan-list endpoint or claim that a plan is provider-approved before intention creation.

The plan and its configured integration are bound into attempt material and persisted as private native order metadata. The existing native `paymob_intention_data` hook forwards `pre_selected_plan` only for the matching order/integration, including recovery attempts. Existing payment journals, retry fences, authenticated callbacks and stock guards are retained. Frontend refreshes invalidate the selected plan to require reconfirmation against the new quote.

The widget uses the same official CDN module as Paymob 4.1.15. It requires browser access to the Paymob/CDN domains. Native plugin availability and provider plan eligibility are respected; missing installment gateways are not fabricated. If the widget returns no plans for the total, use another method or inspect the merchant's Paymob configuration.

## Verification limits

PHP is unavailable in this workspace. This patch has not been executed in WordPress or used to initiate a payment. Validate in the merchant's test environment before live use, including plan selection, amount changes, intent preselection and recovery. The frontend payment remains hosted; card details are entered in Paymob.
