# Release Checklist

Before publishing any Customer API release:

- [ ] Public contract changes reviewed.
- [ ] OpenAPI has no duplicate `(in, name)` operation parameters.
- [ ] Version updated in plugin header.
- [ ] Version constant updated.
- [ ] `CHANGELOG.md` updated.
- [ ] Release note added.
- [ ] `docs/ENDPOINT-STATUS.md` updated.
- [ ] `docs/HANDOFF.md` updated if current state changed.
- [ ] `docs/FILE-MAP.md` updated if files/classes changed.
- [ ] Every PHP file passes `php -l`.
- [ ] ZIP top-level folder is exactly `eldokan-customer-api`.
- [ ] `/health` works.
- [ ] `/health` does not expose WordPress/WooCommerce versions.
- [ ] `Content-Language` matches the effective response language.
- [ ] catalog smoke tests pass.
- [ ] variable product regression passes when relevant.
- [ ] performance headers visible.
- [ ] cache MISS/HIT behavior tested.
- [ ] no secrets were added.
- [ ] Developer Portal updated after live verification.
