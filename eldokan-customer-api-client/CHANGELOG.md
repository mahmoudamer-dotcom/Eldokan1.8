# Changelog

## 0.6.0 — 2026-10-07

- Added typed Account Address Book resources.
- Added checkout quote, attempt, and order placement resources.
- Added authenticated Order listing, protected Order detail, and fenced Paymob recovery.
- Synchronized generated types with the additive 32-path / 133-schema Contract v1 snapshot.
- Added same-origin integration requirements and guest Order capability handling.

## 0.5.0 — 2026-10-01

- Added typed `cart.get/add/update/remove` methods for Phase 2B.
- Added automatic guest Cart bootstrap/CSRF restoration in Client memory.
- Added typed Cart schemas from Contract v1 while preserving every existing resource.
- Checkout and Orders remain unavailable.

## 0.4.1 — 2026-10-01

- Added typed `Brand.image: Image | null` from Customer API 0.5.2.
- Kept all resources, methods, session behavior and Contract v1 endpoints unchanged.

## 0.4.0 — 2026-09-28

- Added typed `auth.register/login/session/logout` methods using credentialed cookie sessions.
- Added in-memory session CSRF handling for authenticated mutations.
- Added typed `account.me/update` and `wishlist.get/add/remove` resources.
- Regenerated types from the additive 20-path / 60-schema Contract v1 snapshot.
- Preserved all 0.3.0 Catalog/Home resources and base-URL configurability.

## 0.3.0 — 2026-09-28

- Added typed `brands.list()`, `tags.list()` and `sellers.get()` resources.
- Added the `tag` product-list filter.
- Documented existing category hierarchy, newest and category best-selling client calls.
- Regenerated types from the additive 12-path / 46-schema Contract v1 snapshot.
- Preserved base-URL configurability and all existing client behavior.

## 0.2.0 — 2026-09-22

- Added strict Home CTA, Hero Slide and Promo Banner types.
- Extended Home sections with `hero_slider` and `banner_grid`.
- Synchronized with Customer API 0.3.0 and Portal 1.4.0.
- Preserved all resources and non-Home client behavior.

## 0.1.0 — 2026-09-21

Initial official ElDokan Customer API Client for Contract v1.

- Framework-agnostic TypeScript client.
- Typed resources for all 9 current Catalog endpoints.
- OpenAPI-generated 33 schema types.
- Structured attribute-filter serializer with OR/AND rules and local validation.
- Central Base URL and language handling.
- Central API/network/timeout/validation error model.
- Exposed diagnostic headers through one optional `onResponse` hook.
- Public catalog defaults to `credentials: omit` and `cache: no-store`.
- No Cart/Auth/Checkout implementation or placeholders.
- Bundled OpenAPI snapshot from Developer Portal 1.3.3 / Customer API Adapter 0.2.3.
