# START HERE — ElDokan Customer API Client 0.5.0

هذا المشروع هو **العميل الرسمي TypeScript** لاستهلاك ElDokan Customer API Contract v1 من واجهة العملاء.

## الفكرة في سطر واحد

```text
React / Next.js Components
        ↓
@eldokan/customer-api-client
        ↓
ElDokan Customer API v1
```

الـFrontend لا يتعامل مباشرةً مع WordPress أو WooCommerce أو WPML، ولا يكتب روابط API داخل الـComponents.

## الحالة الحالية

- Client: `0.5.0`
- API Contract: `v1`
- Current Adapter: Customer API `0.6.0`
- Developer Portal: `1.7.0`
- يغطي 23 paths: الـCatalog/Home + Customer Auth/Account/Wishlist + Guest Session/Cart.
- لا يوجد Seller Account أو Checkout/Orders في هذا الإصدار.

ابدأ بـ`README.md`، وللتسليم الهندسي اقرأ `docs/HANDOFF.md`.
