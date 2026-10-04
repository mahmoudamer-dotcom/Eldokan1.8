# Decisions

1. The package is named `@eldokan/customer-api-client`, matching the Customer API boundary.
2. The package is framework-agnostic; React/Next.js stays outside it.
3. The package is marked `private: true` until ElDokan intentionally decides how/where to publish packages.
4. OpenAPI remains the source for response schema types.
5. Request parameter names are ergonomic camelCase in TypeScript and mapped centrally to the wire contract.
6. Public IDs are opaque strings; frontend code must not parse their numeric portions.
7. Attribute filters are represented structurally in TypeScript and serialized centrally.
8. Shared/browser/framework response caching is disabled by default (`no-store`) while API application caching remains authoritative for current price/stock catalog reads.
9. Public Catalog requests omit credentials by default.
10. No speculative Cart/Auth/Checkout client API is included.
