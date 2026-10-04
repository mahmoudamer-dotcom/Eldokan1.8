# Architecture — API Client 0.4.1

## Boundary

The client is a frontend integration boundary, not a backend and not a second API.

```text
UI / Pages / Components
        ↓
ElDokan Customer API Client
        ↓
ElDokan Customer API v1
        ↓
Backend Adapter
```

## Rule

Frontend application code must not call WordPress/WooCommerce APIs directly. Backend calls should go through this client or an application data-service built directly on top of it.

## Why framework-agnostic

The package contains no React/Next.js imports. It can be used from:
- Next.js Server Components / Route Handlers.
- Browser React code when necessary.
- Node tests/tooling.

This keeps ElDokan API integration portable even if the UI framework changes.

## Resource model

- `health`
- `home`
- `categories`
- `products`
- `search`
- `brands`
- `tags`
- `sellers`

These map exactly to the 12 current read-only endpoints. Category hierarchy stays under `categories`; product brand/tag/newest/best-selling capabilities stay under `products` rather than creating special-purpose resources.

## Future

Cart/Auth/Checkout must be introduced as a new reviewed client capability after the server contracts exist. Do not prebuild speculative session or payment behavior here.
