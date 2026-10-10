# Architecture

## Logical architecture

```text
Customer Frontend
        ↓
Customer API Contract
        ↓
Customer API Implementation
        ↓
Commerce backend
```

Today:

```text
www.eldokan.com / future customer React frontend
        ↓
ElDokan Customer API
        ↓
WordPress plugin adapter
        ↓
WooCommerce + WordPress
```

Future:

```text
Customer Frontend
        ↓
same ElDokan Customer API
        ↓
ElDokan native backend
```

## Separate API domains

ElDokan is intentionally separating concerns:

```text
Customer API
Seller API
Admin API
```

They may share the same underlying data source today, but they have different callers, authorization models, and contracts.

## Adapter responsibility

The WordPress plugin translates:

```text
WooCommerce objects / taxonomies / meta
```

into:

```text
ElDokan-owned JSON response models
```

Language handling follows the same boundary:

```text
lang=ar|en → ElDokan language resolver → WPML adapter today / native fallback
```

Frontend clients never call WPML or depend on WPML IDs, hooks or tables.

Examples:

```text
WooCommerce stock "instock"
→ ElDokan stock "in_stock"

WordPress product ID 21417
→ ElDokan public ID "prd_21417"
```

## Read models

The public catalog endpoints return purpose-specific data:

```text
Product card model
Product detail model
Category model
Category filter model
Home section model
Search suggestion model
```

The API should not expose one giant generic WooCommerce object everywhere.

## Future replacement strategy

When WooCommerce is replaced:

```text
old:
ElDokan contract → WordPress/Woo adapter → WooCommerce

new:
ElDokan contract → native backend service → ElDokan database
```

Frontend changes should be limited if the contract is preserved.
