# Security

## Public Catalog

Contract v1 endpoints are public read-only catalog endpoints.

Defaults:
- `credentials: 'omit'`
- no secrets
- no API keys
- no WordPress credentials
- no WooCommerce consumer keys
- public resources remain anonymous

## Frontend secrets

Never place the following in browser code or this package:
- WooCommerce Consumer Secret
- WordPress Application Password
- payment secret
- server-side API token

## HTML descriptions

`ProductDetail.short_description_html` and `description_html` are server-sanitized HTML according to the current contract. The frontend must still render HTML through a deliberate reviewed component rather than arbitrary unsanitized user input.

## Customer Auth/Account/Wishlist

- Authenticated resources use `credentials: include`; public resources keep the existing default.
- The HttpOnly session cookie is browser-managed and unavailable to JavaScript.
- Register/login/session place the short-lived session-bound CSRF value in Client memory only. Mutations send it as `X-ElDokan-CSRF`.
- Do not persist password or CSRF in localStorage/sessionStorage.
- Use one Client per browser session and one per SSR request. Never share an authenticated Client singleton across users.
- Configure only reviewed frontend origins in the adapter CORS allowlist.
- 401 means the customer session is absent/expired; clear frontend customer state.

Cart/Guest Session and Checkout require a separate Phase 2B design.
