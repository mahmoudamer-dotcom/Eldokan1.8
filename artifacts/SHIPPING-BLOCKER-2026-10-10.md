# Checkout shipping blocker — 10 October 2026

Observed quote: `req_1e78f74202bf496ab05a2fcac8b44daa`, subtotal EGP 249.00.

The storefront displays the backend issue `No supported shipping method covers the complete cart.` Total and payment options are unavailable at this stage. This observation does not establish a Paymob failure.

Customer API 0.8.0 accepts native `flat_rate`, `free_shipping`, and `local_pickup`. Custom WCFM rates must be classified through `eldokan_customer_api_checkout_shipping_type`. All shipping packages need an actual rate with a common delivery type. Classification alone cannot create a missing rate.

## Capture evidence without exporting the Customer API plugin

Install `eldokan-checkout-setup-0.1.2.zip`, replacing the previous Checkout Setup helper. In WooCommerce > ElDokan Checkout:

1. Classify installed WCFM methods that deliver to the customer as **Door delivery**, keeping actual pickup methods as **Store pickup**.
2. Enable **Shipping diagnostics** and save.
3. Refresh shipping and payment in checkout and note its new Quote reference.
4. Reload the admin settings and copy the matching report for the backend manager.

For packages with zero rates, the manager should inspect the matching shipping zone and enabled instances, WCFM vendor shipping configuration, package splitting and hooks expecting a persistent WooCommerce session. The adapter calculates with a request-only session and cart.

For packages with only unsupported rates, classify the returned method IDs. For mixed types, each package must provide the same delivery type. If compatible rates are observed but the API still returns shipping_unavailable, inspect later filters and package changes in the same request.

Reports contain bounded shipping metadata only, are visible to WooCommerce administrators, expire after two hours and retain at most five quotes. Disable capture and save to delete them. No address, customer details, cart contents or payment credentials are stored.

The helper does not invent a shipping price or alter quote/placement calculations. PHP/WordPress runtime execution and a full Paymob sandbox payment have not been completed locally.
