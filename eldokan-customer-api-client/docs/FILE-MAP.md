# File Map

- `src/index.ts` — public package surface and resource composition.
- `src/client.ts` — HTTP transport, query serialization, timeout, JSON/error handling, diagnostics.
- `src/config.ts` — client configuration and current/target base URL constants.
- `src/errors.ts` — one normalized error class.
- `src/filters.ts` — attribute filter serializer/validation.
- `src/types.ts` — public IDs, request parameter types, response diagnostics and generated type re-export.
- `src/generated/types.ts` — generated from the bundled OpenAPI; do not hand-edit.
- `src/resources/*` — typed wrappers for the 9 current endpoints.
- `openapi/*` — exact Contract v1 snapshot used to generate types.
- `scripts/generate-types.py` — dependency-free OpenAPI schema type generator.
- `scripts/verify-package.py` — package/contract boundary verifier.
- `tests/client.test.mjs` — transport/resource unit tests.
- `docs/HANDOFF.md` — current engineering state and live-acceptance status.
