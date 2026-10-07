# Known Issues / Open Acceptance Items

These are not reasons to bypass the client; they are known platform/data items still being validated.

- `api.eldokan.com/v1/customer` is the planned canonical hostname; configure the current WordPress adapter URL until that route is deployed.
- Some Arabic/WPML category translations and parent relations are incomplete in source catalog data.
- Legacy products may return `seller: null` until `_eldokan_seller_user_id` is backfilled.
- Visibility and error-code live acceptance are still to be completed.
- Checkout and Orders are intentionally absent; Phase 2B Cart still requires Live acceptance.
- The current Catalog API uses short-lived application caching; frontend/framework caching is disabled by default in this client.
