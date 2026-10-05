# Testing

## Local verification

```bash
npm run verify
```

Runs:
1. OpenAPI → TypeScript schema generation.
2. Strict TypeScript typecheck.
3. TypeScript build.
4. Node unit tests with mocked Fetch.
5. Package verification against the 20-path / 60-schema Contract v1 snapshot.

## Unit coverage in 0.5.0

- Attribute filter OR/AND serialization.
- Home language query and managed Hero Slide response shape.
- Product query mapping (`perPage` → `per_page`, etc.).
- Default language.
- URL encoding for Arabic category slugs.
- `credentials: omit` and `cache: no-store` defaults.
- Diagnostic response header capture.
- API error envelope normalization.
- Local rejection of invalid attribute use without category.
- Brand/Tag paginated query mapping and validation.
- Nullable normalized Brand Thumbnail data.
- Guest Cart CSRF bootstrap, typed add/update/remove and quantity validation.
- Product tag filter query mapping.
- Stable public Seller ID routing and validation.
- Credentialed register/login/session/logout request behavior.
- In-memory CSRF propagation to account and Wishlist mutations.
- Session restoration and CSRF clearing after logout.
- Typed account 401 normalization and Wishlist add/remove routing.

## Live tests

The API Client does not replace live API acceptance. Use the live checklist in `docs/HANDOFF.md` and the Developer Portal.

## Optional live smoke

After `npm run build`:

```bash
ELDOKAN_API_BASE_URL="https://..." npm run smoke:live
```

This only performs read-only catalog requests (`health`, `products`, `categories`, `home`). It does not replace the full acceptance checklist.
