# Naming and Schema Conventions

## Public ID prefixes

```text
prd_ product
cat_ category
sel_ seller
var_ variation
brd_ brand
att_ attribute definition
atr_ attribute option term
```

## Endpoint naming

Use nouns for resources.

Preferred:

```text
/products
/categories
/search/suggestions
/products/lookup
```

Avoid leaking implementation terms such as:

```text
/wp_posts
/postmeta
/wcfm_vendor
/wc_product
```

## JSON naming

Use:

```text
snake_case
```

Examples:

```text
regular_price
sale_price
average_rating
rating_count
backorders_allowed
```

## Status/enums

Use stable ElDokan enums, not raw backend enums.
