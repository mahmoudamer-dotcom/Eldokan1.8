# Local Customer API payment options extension 0.8.2

This is a local extension of the supplied 0.8.0 plugin, retaining the 0.8.1 shipping context fix. It is not an upstream manager release.

## Checkout choices

Cash on delivery uses the actual WooCommerce COD gateway. Card and bank installments use separate, administrator-selected native hosted Paymob single-integration gateways. The general Paymob option remains a fallback when individual options are not configured. API options are derived from native gateway availability, setup checks, and the existing audited Paymob 4.1.15 boundary.

The server-side `eldokan_checkout_paymob_options` map has the optional keys `card` and `bank_installments`, pointing to distinct native gateway IDs. Use Checkout Setup 0.1.3 to configure them. The general `paymob` gateway and Pixel cannot serve as individual option mappings. Check each integration's actual type in Paymob; the helper does not infer it from its name.

## API extension

Quotes add `paymob_options`, `selected_payment_method`, and `selected_paymob_option`. Quote requests accept optional `payment_method` (`cod` or `paymob`) and optional `paymob_option_id` (`card` or `bank_installments`, only with paymob). Attempts and placement accept `paymob_option_id` too. Public option IDs do not expose native gateway IDs or payment credentials.

When selecting a payment method the request-only Woo session receives the chosen native method before totals calculation. The frontend re-quotes the selected method and shows updated native totals. Preparation uses that same payment selection. Existing attempt hashing includes the input option and the native gateway in the material fingerprint, rejecting changed configuration rather than silently routing to a different method. Recovery uses the stored native order gateway and existing payment journal.

The local frontend uses additive types in `lib/checkout-payment-options.ts`, without replacing the supplied SDK generated contract. Its transport preserves these JSON extension fields. Frontend deployment and API extension installation are both required for individual options.

## Bank plans

Selecting **Bank installments** routes payment to the configured installment integration. Bank, term, fees and eligibility are selected or confirmed on Paymob. This extension does not display or pass a bank/plan selection from Next.js, does not compute installment fees, and does not claim all amounts or cards are eligible. Adding full plan selection inside Next.js requires the official Affordability Widget and verified plan forwarding support in the installed Paymob integration.

Reference: [Paymob WooCommerce integration and bank installment setup](https://developers.paymob.com/paymob-docs/integration-paths/plugins/wordpress).

## Installation

1. Replace Customer API with `eldokan-customer-api-0.8.2-payment-options.zip`. It retains the shipping fix.
2. Replace Checkout Setup with `eldokan-checkout-setup-0.1.3.zip`. Deactivate older helpers if installed in a different folder.
3. In the original Paymob plugin's Payment Integrations, enable individual hosted card and bank-installment integrations for the selected Test/Live environment. Use their actual integration IDs. If no installment integration exists, obtain its activation from Paymob.
4. WooCommerce > ElDokan Checkout: enable COD if desired, select distinct Card payment gateway and Bank installments gateway, and save. Keep current origin, callback confirmation and shipping configuration.
5. Refresh checkout. Only available payment options appear. Choose a method to review its total, then confirm.

TypeScript and targeted ESLint passed locally. PHP is unavailable locally; these PHP changes have not been executed in WordPress and no sandbox payment was made in this update. Verify COD, card and installment paths on the server, including payment recovery and authoritative callback paid status. Retain the working 0.8.1 ZIP for rollback.
