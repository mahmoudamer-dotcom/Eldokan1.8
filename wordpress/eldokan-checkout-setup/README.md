# ElDokan Checkout Setup 0.1.2

English administration settings for the existing ElDokan Customer API 0.8.0 checkout adapter.

## Install or update

1. Deactivate the older ElDokan Checkout Setup helper first. Saved settings are retained. Keep the Customer API active.
2. In WordPress, open Plugins > Add New > Upload Plugin and upload `eldokan-checkout-setup-0.1.2.zip`. Replace the existing helper when prompted.
3. Activate ElDokan Checkout Setup and open WooCommerce > ElDokan Checkout.
4. Review your settings and click Save checkout settings.

Keep WooCommerce, ElDokan Customer API, and the original Paymob plugin active.

## Connection fields

| Field | Value |
| --- | --- |
| Storefront origin (HTTPS) | The HTTPS origin of the Next.js storefront, for example `https://www.eldokan.com` if this domain serves your storefront. Use only the scheme and domain, with an optional port. Do not include `/orders/...`, a query string, or a fragment. |
| WordPress Paymob callback URL | Automatically displays the native callback URL for this WordPress installation. Copy it to the Paymob transaction callback/webhook settings. This is a separate address from the storefront origin. |
| Native hosted Paymob gateway | Select the installed native hosted Paymob gateway. Enable it and configure its credentials in the original Paymob settings. |
| Accept TRANSACTION confirmation | Check only after verifying that Paymob sends the expected native Accept TRANSACTION callbacks to the original WooCommerce plugin. The checkbox records compatibility; it does not configure your Paymob account. |

The supplied Customer API 0.8.0 adapter requires Paymob version 4.1.15 exactly. Local development also needs an HTTPS storefront URL for the payment return.

## Shipping

Shipping rates and zones are configured in WooCommerce. Standard `flat_rate`, `free_shipping`, and `local_pickup` methods are supported automatically by the supplied Customer API adapter.

Use classification overrides only for installed custom methods: Door delivery for delivery methods, or Store pickup for actual collection methods. Classification does not create shipping rates or zones. Every shipping package in the cart must have a compatible rate.

## Diagnose missing shipping

1. Classify installed WCFM home-delivery methods (Store Shipping and Marketplace Shipping by Country/Weight) as **Door delivery**. Standard WooCommerce methods can remain Automatic.
2. Enable **Shipping diagnostics** and save. Capture expires after two hours.
3. Click **Refresh shipping and payment** in checkout, then reload WooCommerce > ElDokan Checkout.
4. Match the checkout Quote reference to the report. Copy that report for support.

An empty rates list means WooCommerce returned no rate for that package: inspect the matching zone, enabled methods and the vendor shipping settings. A list containing only `unsupported` classifications needs a classification override. Packages with incompatible delivery types require a common type across all packages. Reports do not prove that Paymob is ready; payment availability is evaluated after shipping succeeds.

The report stores only request reference, timestamp and shipping method metadata for the latest five quotes, expires after two hours and is visible to users with `manage_woocommerce`. Uncheck diagnostics and save to delete the reports. It does not change shipping rates, totals, orders or API responses.

Hook references: [WordPress REST callback filters](https://developer.wordpress.org/reference/hooks/rest_request_after_callbacks/) and [WooCommerce calculated shipping packages](https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-shipping.html).

## Changes in 0.1.2

- Optional, time-limited shipping diagnostics showing actual native rates and adapter classifications.
- Instructions for WCFM delivery classification and missing vendor rates.

## Changes in 0.1.1

- English settings labels, help text, notices, and buttons.
- Separate read-only native Paymob callback URL.
- Invalid storefront origins and gateway selections return to the settings page with an inline error, retaining previously saved options.
- Clean ZIP layout with a single plugin directory.

## Runtime status

This helper has not been executed locally in PHP or WordPress. Confirm the settings and complete a sandbox payment before relying on the checkout in production.
