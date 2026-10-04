# ELDOKAN-WORK-HANDOFF.md

> **HISTORICAL / SUPERSEDED:** This pre-client project snapshot is retained for traceability only. For the current Client 0.3.0 / Customer API 0.4.0 / Portal 1.5.0 state, use `docs/HANDOFF.md` and `RELEASE-MANIFEST.json`.

## 1) الغرض من الملف

هذا الملف هو **Source of Truth مختصر ومحدث** لحالة مشروع ElDokan الحالية.

يُستخدم مع أي ChatGPT Work / Chat جديد / مطور جديد بدل إعادة قراءة كل الشاتات القديمة أو إعادة تحليل المشروع من الصفر.

> **مهم:** عند التعارض بين هذا الملف وأقسام تاريخية أقدم داخل التوثيق، يُعتبر هذا الملف هو الحالة الحالية ما لم يوجد Release أحدث موثق بوضوح.

---

# 2) الحالة الحالية المختصرة

## الإصدارات الحالية التي تم فحصها

- **ElDokan Customer API:** `0.2.2`
- **ElDokan Developer Portal:** `1.3.2`
- **Customer API Contract:** `v1`

## البصمات الحالية

### Customer API 0.2.2
`bb4f2f076b3abd1a07ec3f2cf302fb19f6bdfc32cf1c0c83b45c6519e80f0c4c`

### Developer Portal 1.3.2
`957722e045cf07bfd8ece867ffbe444ef141acdd79d9cf9819359c4374eb474e`

## حالة الاعتماد

- الكود الحالي يعتبر **Catalog API Release Candidate قوي**.
- لم يتم اعتباره Production Baseline نهائيًا إلا بعد **Live Acceptance Tests**.
- لا يجب العودة لاستخدام:
  - Customer API `0.2.0`
  - Customer API `0.2.1`
  - Portal `1.3.0`
  - Portal `1.3.1`

---

# 3) المعمارية المعتمدة

الهدف هو أن تكون ElDokan هي مالكة الـAPI Contract، بينما WordPress/WooCommerce مجرد Backend Adapter حالي يمكن استبداله لاحقًا.

```text
Customer Frontend
Seller Frontend
Admin Frontend
        ↓
api.eldokan.com
        ↓
ElDokan-owned API Contracts
        ↓
WordPress + WooCommerce Adapters حاليًا
```

مستقبلًا:

```text
Customer Frontend
Seller Frontend
Admin Frontend
        ↓
api.eldokan.com
        ↓
ElDokan-owned API Contracts
        ↓
ElDokan Native Backend
```

## توزيع الـSubdomains المعتمد

```text
www.eldokan.com
→ واجهة العملاء

sell.eldokan.com
→ واجهة التجار الحالية

admin.eldokan.com
→ واجهة الموظفين مستقبلًا

api.eldokan.com
→ المدخل العام للـAPIs

developers.eldokan.com
→ Developer / Engineering Documentation Portal
```

## المسارات المستهدفة للـAPI

```text
https://api.eldokan.com/v1/customer/...
https://api.eldokan.com/v1/seller/...
https://api.eldokan.com/v1/admin/...
```

الحالي أثناء التنفيذ على WordPress:

```text
https://www.eldokan.com/wp-json/eldokan-customer/v1/...
```

### قرار مهم

لا نعمل:
- WordPress جديد تحت `api.eldokan.com`
- Backend Node/Laravel مستقل فقط لتمرير الطلبات
- Redirect 301/302 إلى `wp-json`

الخيار المخطط لاحقًا هو:

```text
api.eldokan.com
→ Cloudflare Worker محدود
→ Reverse Proxy
→ WordPress REST Adapter الحالي
```

---

# 4) حدود الـAPIs

## Customer API

خاصة بكل ما يخص تجربة العميل/الزائر:

- Catalog
- Home
- Products
- Categories
- Search
- Filters
- Product details
- Variations
- Guest Session لاحقًا
- Cart لاحقًا
- Login لاحقًا
- Checkout لاحقًا
- Payments لاحقًا
- Orders لاحقًا
- Wishlist / Follow / Price Tracking لاحقًا

## Seller API

تظل منفصلة منطقيًا عن Customer API.

لا يتم نقل Seller Authentication أو Seller workflows تلقائيًا إلى Customer API.

## Admin API

مخططة لاحقًا.

لا يتم بناء Admin جديدة الآن لمجرد وجود `admin.eldokan.com`.

---

# 5) ما تم إنجازه في Customer API حتى 0.2.2

## Endpoints الحالية: 9

- `GET /health`
- `GET /home`
- `GET /categories`
- `GET /categories/{slug}`
- `GET /categories/{slug}/filters`
- `GET /products`
- `GET /products/{product_id}`
- `GET /products/lookup?slug=...`
- `GET /search/suggestions`

## تم إنجازه

- Product catalog contract
- Categories
- Category filters
- Search suggestions
- Product detail
- Variable products
- Variations
- Price normalization
- Stock normalization
- Seller projection
- Public IDs
- Visibility rules
- Arabic / English support
- Translation abstraction
- App-level caching
- Cache diagnostics
- OpenAPI schemas
- Error envelope
- Request IDs
- Home payload reduction
- Documentation / Handoff / Changelog / Testing docs

---

# 6) الـPublic IDs الحالية

الـFrontend يجب أن يتعامل مع IDs الدكان فقط كـstrings، ولا يعتمد على أرقام WordPress الداخلية.

أمثلة:

```text
prd_   Product
cat_   Category
sel_   Seller
var_   Variation
brd_   Brand
att_   Attribute
atr_   Attribute Option/Term
```

## قاعدة مهمة

عند الانتقال مستقبلًا إلى Backend جديد:

- لا يتم إعادة توليد Public IDs من Primary Keys جديدة.
- يجب الحفاظ عليها أو عمل Mapping ثابت.

---

# 7) Seller Ownership

المصدر الحالي المعتمد الوحيد لملكية التاجر هو:

```text
_eldokan_seller_user_id
```

## ممنوع العودة إلى:

- `post_author`
- `_eldokan_seller_id`
- WCFM legacy fallbacks
- display_name / user_login كبديل تلقائي

## نتيجة متوقعة

المنتجات القديمة التي لا تحتوي:

```text
_eldokan_seller_user_id
```

قد ترجع:

```json
"seller": null
```

ويجب عمل Audit / Backfill لها قبل الاعتماد النهائي على Seller data في الواجهة.

---

# 8) Translation / WPML

الـPublic Contract هو:

```text
lang=ar
lang=en
```

الـFrontend لا يجب أن يعرف أن WPML موجودة.

البنية الحالية:

```text
ElDokan Language Contract
        ↓
Translation Adapter
     ↙       ↘
   WPML      Native
```

## الوضع الحالي

- WPML ما زالت مصدر الترجمة الحالي.
- تم عزل كل معرفة WPML داخل Translation Adapter.
- لا يجب نشر WPML IDs أو أسماء functions أو assumptions في الـPublic API.
- غياب WPML لا يجب أن يسبب Fatal.
- Native fallback موجود.
- `Content-Language` يوضح اللغة الفعلية للرد.

## قرار مستقبلي

يمكن إزالة WPML لاحقًا بدون تغيير عقد الـFrontend، بشرط بناء Translation source بديل.

---

# 9) Cache

تم اختبار App Cache حيًا ونجحت:

```text
MISS → stored
HIT  → hit
```

## أمثلة من الاختبارات السابقة

### Products
مع `per_page=24`:

```text
MISS: ElDokan processing ≈ 143 ms
HIT:  ElDokan processing < 1 ms
```

### Home

```text
MISS: ElDokan processing ≈ 173 ms
HIT:  ElDokan processing ≈ 1.2 ms
```

## ملاحظة مهمة

وقت الـAPI الداخلي سريع جدًا عند HIT، لكن WordPress/PHP/Plugins/Hosting ما زالوا يضيفون Origin overhead يقارب حوالي 0.9–1.4 ثانية في بعض الاختبارات.

هذا لا يعني أن Customer API نفسها بطيئة.

## Cache backend أثناء الاختبار

```text
transients-db
```

## Diagnostics الحالية

- `X-ElDokan-Cache`
- `X-ElDokan-Cache-Ttl`
- `X-ElDokan-Cache-Generation`
- `X-ElDokan-Cache-Key`
- `X-ElDokan-Cache-Backend`
- `X-ElDokan-Cache-Store`
- `X-ElDokan-Request-ID`
- `X-ElDokan-API-Version`
- `Server-Timing`

---

# 10) ProductDetail الحالي

بعد 0.2.2 تم توحيد التنفيذ مع OpenAPI.

Product Detail يحتوي:

```text
image
→ الصورة الأساسية

images
→ الصورة الأساسية ثم Gallery
```

الحالات المطلوبة:

- منتج له صورة أساسية + Gallery
- منتج بلا صورة أساسية → `image: null`

---

# 11) OpenAPI / Developer Portal

## الوضع الحالي

- 9 endpoints
- 36 schemas
- YAML / JSON / YAML.TXT متطابقة
- Duplicate parameters تم منعها باختبار
- Customer state placeholders غير المنفذة تم حذفها
- Seller followed placeholder تم حذفه

## Developer Portal

العنوان:

```text
developers.eldokan.com
```

هو Portal توثيق داخلي Static ومحمي.

وظيفته:

- Getting Started
- Architecture
- Authentication
- Customer API Reference
- OpenAPI
- Implementation Status
- Handoff
- Roadmap
- API Changelog
- Portal Changelog

ولا يدخل في Runtime traffic الخاص بالعملاء.

---

# 12) الفحوص التي تمت على 0.2.2 / 1.3.2

تم التحقق من:

- Customer API verification → PASS
- Developer Portal verification → PASS
- `php -l` على ملفات PHP التسعة → PASS
- JavaScript syntax → PASS
- HTML parsing → PASS
- UTF-8 → PASS
- ZIP integrity → PASS
- OpenAPI YAML = JSON = YAML.TXT → PASS
- 36 schemas → ثابتة
- 9 endpoints → ثابتة
- لا يوجد Duplicate operation parameters
- WPML knowledge محصورة داخل Translation Adapter
- ProductDetail `image` + `images` متطابقان بين runtime والعقد

---

# 13) ملاحظات Documentation الحالية

هناك تنظيف Documentation صغير مخطط له قبل الـLive حتى لا يلتبس على أي Chat أو Developer جديد.

## المطلوب فقط

### START-HERE.md

توحيد وصف Release الحالي وعدم استخدام وصف قديم مثل:

```text
Release Hardening Patch
```

إذا كان اسم الإصدار الحالي مختلفًا في Changelog.

### STUDY-NOTES-AR.md

الأقسام القديمة من 0.1.x يجب تعليمها بوضوح:

```text
HISTORICAL / SUPERSEDED
```

خصوصًا السلوك القديم مثل:

```text
/products/by-slug/...
```

والحالي هو:

```text
/products/lookup?slug=...
```

وكذلك Seller ownership القديم يجب ألا يلتبس مع الحالي.

## مهم

هذا **Documentation-only cleanup** وليس Runtime change.

---

# 14) المهمة الحالية التالية فقط

## Planned Documentation Patch

إنشاء:

```text
Customer API 0.3.0
Developer Portal 1.4.0
```

### المطلوب

- Documentation cleanup فقط
- مزامنة versions / changelog / handoff
- لا تغيير في Runtime behavior

### ممنوع في هذه المهمة

لا يتم تغيير:

- Endpoints
- OpenAPI schemas
- Filtering
- Cache logic
- Translation architecture
- Seller logic
- Public IDs
- Product serialization
- Error behavior
- Authentication
- Cart
- Checkout
- Worker
- Database model

---

# 15) بعد 0.3.0 مباشرة

لا نعمل Patchات نظرية إضافية بدون سبب ظهر من Live Test.

الخطوة التالية:

```text
Backup
↓
رفع Customer API
↓
Live Acceptance Tests
↓
لو نجحت:
رفع Developer Portal
↓
api.eldokan.com + Worker
↓
تسليم Catalog Contract لمحمود
```

---

# 16) Live Acceptance Test Checklist

يتم الاختبار على الـLive تدريجيًا لأن الواجهة الحالية لا تعتمد على Customer API حتى الآن.

## 1. Health

```text
/wp-json/eldokan-customer/v1/health
```

المتوقع:

```text
version = current version
status = ok
ready = true
```

## 2. Products

```text
/products?per_page=24
```

اختبار:

```text
MISS → HIT
```

وثبات Cache Key.

## 3. Home

اختبار:

```text
MISS → HIT
```

ومراجعة حجم الـpayload.

## 4. Categories

- Categories list
- Category detail

## 5. Filters

اختبار Category filters فعليًا ثم استخدام `att_* / atr_*` في `/products`.

قاعدة المنطق:

```text
OR داخل نفس الخاصية
AND بين الخصائص المختلفة
```

## 6. Simple Product

Product detail كامل.

## 7. Variable Product

اختبار:

- variations
- price range
- stock
- image
- purchasable state
- disabled variation
- out of stock
- backorder

## 8. Images

- Product with main image + gallery
- Product with no main image

## 9. Languages

- Arabic
- English
- `Content-Language`
- WPML translation mapping
- Arabic slugs

## 10. Seller

مراجعة:

```text
seller != null
```

ومراجعة المنتجات القديمة التي لا تحتوي:

```text
_eldokan_seller_user_id
```

## 11. Visibility

اختبار:

- published
- hidden
- protected
- draft
- search/catalog rules

## 12. Errors

اختبار حالات مثل:

- 400
- 404
- 422
- 503 عند الحاجة

---

# 17) ما لم يتم بناؤه بعد

Customer API الحالية ليست Commerce API كاملة بعد.

المتبقي لاحقًا:

```text
Guest Session
↓
Cart
↓
Customer Login
↓
Cart Merge
↓
Logout behavior
↓
Checkout
↓
Payments
↓
Orders
```

ثم:

- Wishlist
- Follow Seller
- Price Tracking

---

# 18) قرارات مهمة قبل Cart / Checkout

لا يتم تنفيذ Cart بسرعة بدون تصميم الجلسات.

يجب أولًا تحديد:

- Guest session
- Cookie strategy
- CORS
- `credentials: include`
- CSRF
- login merge
- logout behavior
- cart versioning
- stock revalidation
- price revalidation
- checkout idempotency
- payment webhook idempotency

## قاعدة أساسية

الـFrontend لا يحمل:

- WooCommerce Consumer Secret
- Application Password
- Admin credentials

ولا يعتمد على WooCommerce response shapes مباشرة.

---

# 19) Cloudflare Worker

يتم بعد نجاح الـCatalog Live Acceptance.

المسار المستهدف:

```text
https://api.eldokan.com/v1/customer/products
```

يتم تحويله داخليًا إلى:

```text
https://www.eldokan.com/wp-json/eldokan-customer/v1/products
```

## Worker v1 يجب أن يكون محدودًا

- Allowlist للمسارات
- Allowlist للطرق
- CORS مركزي
- لا يمرر `/wp-admin`
- لا يمرر كل `/wp-json`
- لا يحتوي business logic
- لا يحسب stock / price / commissions
- لا يخزن responses شخصية
- Error normalization
- Request tracing
- Rate limits المناسبة

---

# 20) ما لا يجب إعادة تحليله من الصفر

أي Work / Chat جديد يجب ألا يعيد Architecture Review كاملة إلا إذا ظهر سبب جديد حقيقي.

القرارات التالية تعتبر **مقفولة حاليًا**:

- الاستمرار على WooCommerce الآن
- امتلاك ElDokan للـAPI Contract
- فصل Customer / Seller / Admin منطقيًا
- `api.eldokan.com` كمدخل عام
- `developers.eldokan.com` للتوثيق فقط
- `/v1/customer/*`
- Translation Adapter
- `_eldokan_seller_user_id`
- Public IDs الحالية
- عدم بناء Backend مستقل الآن
- عدم بناء Microservices الآن
- عدم بناء Admin جديدة الآن
- عدم نقل Seller API أثناء مرحلة Customer Catalog

---

# 21) Workflow العمل المعتمد

## Chat العادي

يستخدم لـ:

- الشرح
- اتخاذ القرار
- تحليل نتائج الاختبارات
- المراجعة المستقلة
- Final Audit
- تحديد المهمة التالية

## Work + Sol

يستخدم لـ:

- تعديل الملفات
- بناء releases
- تحديث OpenAPI
- تحديث docs
- تشغيل verification
- إنتاج ZIPs

## طريقة العمل

```text
نحدد المطلوب
↓
Work ينفذ
↓
Independent Review
↓
Patch صغير إذا لزم
↓
Live Test
↓
إغلاق المرحلة
```

## قاعدة مهمة لتقليل الاستهلاك

لا تطلب:

```text
راجع المشروع كله وحسن كل حاجة
```

إلا عند مراجعة معمارية مقصودة.

الأفضل:

```text
اقرأ هذا HANDOFF
ثم نفذ المهمة المحددة فقط
ولا تعيد تحليل القرارات المقفولة
```

---

# 22) Prompt مقترح لأي Work جديد

استخدم:

```text
Read ELDOKAN-WORK-HANDOFF.md first.

Treat it as the current authoritative project state.

Do not restart the architecture review.
Do not revisit completed decisions unless the handoff explicitly marks them as open.

Inspect only the files relevant to the current task.

Before making changes:
1. Summarize the exact current version.
2. State the files you need to touch.
3. State what you will NOT change.

Then perform only the requested task, run the relevant verification, update the necessary changelog/handoff/version references, and produce final ZIPs only after verification.
```

---

# 23) معيار نجاح مرحلة Catalog

نعتبر Catalog API جاهزة كـProduction Baseline عندما:

- جميع Live Acceptance Tests الأساسية تنجح.
- محمود يستطيع استهلاك العقد بدون معرفة WooCommerce internals.
- لا تظهر taxonomy keys أو WPML internals أو DB IDs في الـPublic Contract.
- Filters تعمل من السيرفر.
- Variable Products تعمل.
- Seller ownership صحيحة.
- Arabic/English تعمل.
- Cache تعمل بثبات.
- OpenAPI تطابق Runtime.
- Developer Portal يعكس النسخة المختبرة فعليًا.

بعدها تبدأ مرحلة:

```text
Guest Session → Cart → Login → Checkout → Payments
```

---

# 24) آخر قرار تشغيلي

**لا نعيد بناء الكتالوج من جديد.**

نحن الآن في نهاية مرحلة بناء Catalog Contract، والمطلوب هو:

```text
Documentation cleanup
→ Live Acceptance
→ api.eldokan.com
→ Frontend integration
```

ثم نبدأ Commerce flows.
