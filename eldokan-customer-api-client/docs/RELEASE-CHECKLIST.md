# Release Checklist

Before handing a new API Client release to frontend development:

- [ ] Bundled OpenAPI is the approved portal contract.
- [ ] `npm run generate:types` completed.
- [ ] `tsc --noEmit` passes.
- [ ] Build passes.
- [ ] Unit tests pass.
- [ ] `npm run verify:package` passes.
- [ ] No backend secrets or credentials are present.
- [ ] Base URL is configuration-only.
- [ ] No direct backend-specific route is embedded in runtime source.
- [ ] CHANGELOG/HANDOFF versions are synchronized.
- [ ] Live Catalog acceptance status is updated.
- [ ] Independent review completed before frontend adoption.
