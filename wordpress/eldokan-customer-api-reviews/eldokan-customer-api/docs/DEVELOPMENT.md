# Development and Release Workflow

## Safe development sequence

```text
1. update source
2. update tests/docs
3. PHP lint every PHP file
4. package with stable top-level folder:
   eldokan-customer-api/
5. replace/update plugin
6. smoke test
7. performance test
8. update Developer Portal only after the live contract is verified
```

## Folder slug

Keep:

```text
eldokan-customer-api
```

stable inside release ZIP files so WordPress recognizes updates as replacements.

## Version locations

A release must update:

```text
Plugin header Version
ELDOKAN_CUSTOMER_API_VERSION
CHANGELOG.md
release note
endpoint status if needed
handoff if project state changes
```

## Coding principles

- small classes with one responsibility;
- no giant all-purpose plugin class;
- no secrets in code;
- sanitize inputs;
- escape/admin output where relevant;
- public response shape belongs to ElDokan, not WooCommerce;
- bounded queries;
- explicit pagination;
- readable names over clever abstractions.

## Public contract changes

If a route/field is already consumed by frontend production code:

- do not silently rename/remove it;
- document deprecation;
- provide migration path;
- update OpenAPI/Developer Portal.
