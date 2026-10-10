# Data Mapping — Current WooCommerce Adapter

## Product

Current source:

```text
WooCommerce product post + WC_Product
```

Public ID:

```text
WordPress product ID 21417
→ prd_21417
```

## Seller ownership

Canonical product meta:

```text
_eldokan_seller_user_id
```

The Seller Dashboard API writes this value. Customer API accepts no post author, WCFM or generic vendor-meta fallback. The referenced user must have an accepted seller role and configured store/company name; otherwise public `seller` is null.

## Brand

The adapter currently probes supported taxonomies in this order:

```text
product_brand
pa_brand
pwb-brand
yith_product_brand
```

This compatibility logic belongs in the adapter.

## Stock

WooCommerce:

```text
instock
outofstock
onbackorder
```

Public ElDokan:

```text
in_stock
out_of_stock
on_backorder
```

## Category attributes

Existing ElDokan category term metadata:

```text
_eldokan_attribute_mode
_eldokan_attribute_ids
```

Semantics:

```text
custom
none
inherit/unset
```

If no explicit rule is found in the category ancestry, the Customer API infers taxonomy attributes from published products.

## Warranty

Current adapter attempts:

1. product attribute whose name contains warranty/ضمان;
2. selected warranty meta fallbacks;
3. otherwise null.

A future canonical warranty model should replace fallback guessing.

## Delivery

Current status:

```text
not implemented
```

Do not fabricate delivery dates from generic WooCommerce fields.

## Price and stock authority

Catalog values are read models and may be cached briefly.

Before checkout/order creation, current price and stock must be revalidated from the authoritative backend.
