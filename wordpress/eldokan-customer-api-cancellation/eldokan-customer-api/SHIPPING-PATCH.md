# Local shipping compatibility patch 0.8.1

This is a locally patched copy of the supplied ElDokan Customer API 0.8.0, not an upstream release. The original supplied files remain in `wordpress-plugin-source/eldokan-customer-api`.

## Root cause found in source

The adapter assigns `$wc->shipping = new WC_Shipping()` and reads packages from that object. Native `WC_Cart::calculate_shipping()` instead calls `WC()->shipping()->calculate_shipping(...)`. In WooCommerce 11.1.2, that method returns `WC_Shipping::instance()`, independently of the dynamic `shipping` property. Therefore the adapter reads an uncalculated engine while native shipping hooks can report actual rates from the other engine.

Primary source references:

- [WooCommerce 11.1.2 shipping accessor](https://github.com/woocommerce/woocommerce/blob/11.1.2/plugins/woocommerce/includes/class-woocommerce.php)
- [Native cart shipping calculation](https://github.com/woocommerce/woocommerce/blob/11.1.2/plugins/woocommerce/includes/class-wc-cart.php)
- [Shipping singleton implementation](https://github.com/woocommerce/woocommerce/blob/11.1.2/plugins/woocommerce/includes/class-wc-shipping.php)

## Changes

A small `WC_Shipping` subclass exposes a scoped setter for the inherited protected singleton. Quote calculation activates its request-only shipping engine as the native singleton, saves the previous native engine separately, and restores it in `finally`. The same context alignment and restoration is applied to order placement, order construction and native Paymob payment context. Captured quote context retains the actual calculated engine for placement.

This uses a protected WooCommerce implementation detail verified in version 11.1.2. Reassess it on WooCommerce updates. The parent engine implementation, rates, taxes, fees, payment verifier, HMAC checks and order storage remain the supplied code. No artificial shipping price or frontend payment success is introduced.

## Install

Upload `eldokan-customer-api-0.8.1-shipping-fix.zip` through WordPress > Plugins > Add New > Upload Plugin, replacing **ElDokan Customer API** in the existing `eldokan-customer-api` directory. Do not install alongside an active second Customer API copy. Leave Checkout Setup and the original Paymob plugin installed. Saved options and database table schema are unchanged.

Refresh shipping and payment in checkout with an actual Cairo address (`EGC`). Inspect the native shipping choice and calculated totals, then complete a Paymob sandbox payment and confirm server-side paid status. This patch does not guarantee Paymob availability: its configuration checks run after shipping becomes calculable.

## Evidence and limits

The supplied quote `req_13242a4f32a94b7681eb52ded564f6ee` has country EG, state EGC, a valid cart, shipping_unavailable and no totals/payment choices. Earlier independent quote diagnostics observed flat_rate instance 36 at EGP 130. These request references differ; the source-level defect is established independently of a same-request report comparison.

PHP is not available locally. This patch has been inspected in source but has not been executed in PHP/WordPress and no payment has been initiated. Keep the original 0.8.0 ZIP available for rollback. The manager should review this targeted patch and include a maintained fix in the upstream plugin.
