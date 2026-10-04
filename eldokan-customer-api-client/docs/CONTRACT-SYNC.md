# Contract Sync

When Customer API Contract v1 changes:

1. Export/copy the canonical OpenAPI JSON from the approved Developer Portal release.
2. Replace `openapi/eldokan-customer-api-v1.openapi.json`.
3. Run `python scripts/generate-types.py`.
4. Review compile errors in resources; they indicate contract drift.
5. Update request parameter mappings only if the wire contract changed.
6. Run `npm run verify`.
7. Update CHANGELOG and HANDOFF.
8. Independent review before handing the release to frontend developers.

A breaking contract change should not be hidden in a client patch. Coordinate API contract versioning first.
