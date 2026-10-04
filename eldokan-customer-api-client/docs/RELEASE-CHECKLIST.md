# Release Checklist

Before handing a new API Client release to frontend development:

- [ ] Bundled OpenAPI is the approved portal contract.
- [ ] `python scripts/generate-types.py` completed.
- [ ] `tsc --noEmit` passes.
- [ ] Build passes.
- [ ] Unit tests pass.
- [ ] `scripts/verify-package.py` passes.
- [ ] No backend secrets or credentials are present.
- [ ] Base URL is configuration-only.
- [ ] No direct backend-specific route is embedded in runtime source.
- [ ] CHANGELOG/HANDOFF versions are synchronized.
- [ ] Live Catalog acceptance status is updated.
- [ ] Independent review completed before frontend adoption.
