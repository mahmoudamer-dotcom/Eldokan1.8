# Known Issues / Open Acceptance Items

These are not reasons to bypass the client; they are known platform/data items still being validated.

- `api.eldokan.com/v1/customer` is the planned canonical hostname and is not yet the active route in Client 0.5.0 rollout.
- Some Arabic/WPML category translations and parent relations are incomplete in source catalog data.
- Legacy products may return `seller: null` until `_eldokan_seller_user_id` is backfilled.
- Visibility and error-code live acceptance are still to be completed.
- Checkout and Orders are intentionally absent; Phase 2B Cart still requires Live acceptance.
- The current Catalog API uses short-lived application caching; frontend/framework caching is disabled by default in this client.
