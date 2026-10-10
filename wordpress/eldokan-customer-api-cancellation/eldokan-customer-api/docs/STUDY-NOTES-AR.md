# ملاحظات مذاكرة — ElDokan Customer API 0.3.0

> **CURRENT:** الأقسام العامة في بداية الملف محدثة للإصدار الحالي. الأقسام المسماة `0.1.x` بالأسفل محفوظة للتاريخ فقط ومعلّمة بوضوح `HISTORICAL / SUPERSEDED`؛ لا تُستخدم كمصدر للعقد الحالي.

## 1) إحنا بنبني إيه؟

الإضافة دي **مش متجر جديد** ومش بتكرر WooCommerce.

هي Adapter:

```text
React
  ↓
ElDokan Customer API
  ↓
WooCommerce
```

WooCommerce لسه فيه المنتجات والفئات والمخزون والأسعار.
الإضافة فقط تقرأ البيانات وترجعها بشكل ElDokan الثابت.

---

## 2) أهم مصطلحات تحفظها

### API
وسيط برمجي بين الواجهة والـBackend.

### Endpoint
عنوان لوظيفة محددة داخل الـAPI.

مثال:

```text
GET /products
```

يعني: هات المنتجات.

### Request
الطلب اللي الواجهة تبعته.

### Response
البيانات اللي الـAPI ترجعها.

### JSON
الشكل القياسي اللي البيانات بتتنقل بيه.

### GET
قراءة بيانات فقط.

في الإصدار 0.3.0 ما زالت الـCustomer API الحالية للكتالوج العام Read-only وتعتمد على GET فقط.

### API Contract
الاتفاق الثابت بين Frontend وBackend:
- أسماء الـEndpoints
- Parameters
- Response shape
- Error shape

الـContract هو اللي عايزين نحافظ عليه حتى لو WooCommerce اتشال بعدين.

### Adapter
الكود الحالي اللي يترجم بيانات WooCommerce إلى ElDokan API.

---

## 3) الملفات بتعمل إيه؟

### `eldokan-customer-api.php`
بوابة تشغيل الإضافة.

وظيفته:
- يعرف اسم ورقم إصدار الإضافة.
- يحمل باقي الملفات.
- يشغل الـAPI.

### `class-eldokan-customer-api.php`
الـRouter الرئيسي.

هو اللي يقول لوردبريس:

```text
لما يجي GET /products
شغل كود المنتجات.
```

وكمان يوحد شكل Success/Error Responses.

### `class-eldokan-customer-api-utils.php`
أدوات مشتركة:
- الأسعار.
- الصور.
- اللغة.
- IDs الخاصة بالدكان.
- اكتشاف Brand taxonomy.

### `class-eldokan-customer-api-language.php`
يعزل التعامل مع اللغة عن باقي الإضافة:

```text
lang=ar|en
→ ElDokan Language Resolver
→ WPML Adapter حاليًا أو Native Fallback
```

الواجهة لا تعتمد على WPML مباشرة، و`Content-Language` يوضح اللغة الفعلية للرد.

### `class-eldokan-customer-api-cache.php`
مسؤول عن الكاش القصير للكتالوج، مفاتيح الكاش المطبّعة، الـinvalidation والـdiagnostic headers.

### `class-eldokan-customer-api-products.php`
مسؤول عن:
- قائمة المنتجات.
- البحث.
- Product Details.
- Variations.
- السعر.
- المخزون.
- الصور.
- الخصائص.

### `class-eldokan-customer-api-categories.php`
مسؤول عن:
- الفئات.
- الفئات الفرعية.
- Category-specific attributes/filters.

وبيقرأ نفس إعدادات Attributes الموجودة حاليًا:

```text
_eldokan_attribute_mode
_eldokan_attribute_ids
```

### `class-eldokan-customer-api-sellers.php`
يقرأ التاجر المرتبط بالمنتج.

مصدر الملكية الوحيد المعتمد:

```text
_eldokan_seller_user_id
```

لا توجد fallbacks إلى `post_author` أو مفاتيح WCFM/Dokan العامة. لو الحقل غير موجود، أو المستخدم ليس بدور تاجر مقبول، أو اسم المتجر غير مضبوط، ترجع `seller: null`.

### `class-eldokan-customer-api-home.php`
يبني Sections الصفحة الرئيسية:

```text
Categories
Featured Deals
Best Sellers
New Arrivals
Brands
```

الـHero Slider والـPromo Banners المُدارة من إضافة ElDokan Admin ترجع الآن ضمن `/home`. أما Why ElDokan وFAQ وfeature sections فلا ترجع كبيانات placeholder لأنها ليست ضمن عقد Home الحالي.

---

## 4) ليه ID مثل `prd_123` بدل 123؟

عشان الواجهة ما تعتمدش على حقيقة إن 123 هو WordPress Post ID.

الواجهة تشوف:

```json
"id": "prd_123"
```

مستقبلًا لو نقلنا المنتج لقاعدة بيانات مختلفة نقدر نحافظ على نفس الـContract.

نفس الفكرة:

```text
prd_ = product
cat_ = category
sel_ = seller
var_ = variation
brd_ = brand
att_ = attribute
atr_ = attribute option
```

---

## 5) مثال Request

```text
GET /wp-json/eldokan-customer/v1/products?page=1&per_page=24
```

الإضافة:
1. تستقبل الطلب.
2. تبحث داخل منتجات WooCommerce.
3. تحول كل Product إلى شكل ElDokan.
4. ترجع JSON.

---

## 6) مثال Response

```json
{
  "success": true,
  "data": [
    {
      "id": "prd_123",
      "slug": "iphone-17-pro-max",
      "name": "iPhone 17 Pro Max",
      "pricing": {},
      "stock": {},
      "seller": {}
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 24,
    "total": 200,
    "total_pages": 9,
    "request_id": "req_..."
  }
}
```

---

## 7) اللغة

الـAPI تقبل:

```text
?lang=ar
```

أو:

```text
Accept-Language: ar
```

لو WPML شغال، الـWPML Adapter يبدل لغة الاستعلام مؤقتًا ثم يرجع للغة الأصلية. ولو WPML غير متاح، تستخدم الإضافة Native Fallback بدون Fatal error.

---

## 8) ليه بدأنا Read-only؟

لأن:

```text
Products / Categories
```

قراءة بيانات.

لكن:

```text
Cart / Checkout / Orders / Payment
```

بتغير بيانات وفلوس ومخزون.

فنثبت القراءة الأول، نشغل محمود، وبعدها ندخل على Cart وCheckout بهدوء.

---

## 9) إيه اللي محمود يقدر يبدأه بعد Live Acceptance لـ0.3.0؟

بعد ما Endpoints ترجع بيانات صحيحة:

```text
Home
Category
Search
Product Listing
Product Details
```

هو لا يحتاج انتظار Cart وCheckout ليبدأ الشغل.

---

## 10) القاعدة الأهم

محمود يتعامل مع:

```text
ElDokan Customer API
```

وليس:

```text
WooCommerce API
```

WooCommerce بالنسبة للواجهة غير مرئي.

---

## 11) ما الذي سنعمله بعد نجاح Live Acceptance لـ0.3.0؟

المرحلة التالية:

```text
Cart + Guest Session
```

ثم:

```text
Checkout + Order Creation + Payment Adapter
```

ثم:

```text
Customer Account
Wishlist
Follow Seller
Price Tracker
```


---

# تحديث مذاكرة 0.1.1

> **HISTORICAL / SUPERSEDED:** هذا القسم يشرح قرارًا تاريخيًا. العقد الحالي موجود في README وOpenAPI، والمسار القديم `by-slug` غير مستخدم.

## ليه غيرنا Product Detail من slug إلى ID؟

الـslug جزء من عنوان صفحة المنتج وSEO، وممكن WordPress أو Rank Math يعملوا له Redirect.

لذلك المرجع الأساسي في الـAPI أصبح:

```text
GET /products/prd_21417
```

في تلك المرحلة التاريخية كان البحث بالـslug مكتوبًا هكذا:

```text
GET /products/by-slug/product-slug
```

المسار الحالي المعتمد بدلًا منه هو:

```text
GET /products/lookup?slug=product-slug
```

الفكرة:

```text
Slug
= عنوان قابل للتغيير

Product Public ID
= مرجع ثابت للـFrontend
```

## معنى Normalization

WooCommerce يقول:

```text
instock
```

لكن عقد ElDokan يقول:

```text
in_stock
```

الـAdapter يترجم بين الاثنين.

وده مثال عملي على إن الواجهة لا تعتمد على لغة WooCommerce الداخلية.

## معنى Cleanup

WordPress ممكن يخزن اسم:

```text
Black &amp; White
```

الـAPI ترجع:

```text
Black & White
```

وممكن وصف المنتج يحتوي بيانات Editor داخل HTML مثل:

```text
data-pm-node
data-index-in-node
```

دي تفاصيل داخلية لا تخص React، لذلك الـAPI تشيلها قبل الـResponse.


---

# تحديث مذاكرة 0.1.2

> **HISTORICAL / SUPERSEDED:** محفوظ لفهم تطور الفلاتر والمسارات فقط. السلوك الحالي يُؤخذ من OpenAPI و`docs/API-CONTRACT.md`.

## لماذا كانت Smartphones ترجع unconfigured؟

الإعداد القديم في ElDokan Admin لا يعني أن الفئة التي ليس لها Custom Rule بدون Attributes.

السلوك الحقيقي هو:

```text
Custom → استخدم الاختيارات
None → بدون خصائص
Inherit → ابحث في الأب
لا يوجد Rule → استنتج من المنتجات
```

في 0.1.1 كنا نقف عند "لا يوجد Rule".
في 0.1.2 أكملنا نفس السلوك الموجود أصلًا في النظام.

## لماذا لم ننسخ كود Seller API حرفيًا؟

Seller API القديمة كانت تستطيع فحص مئات المنتجات واحدًا واحدًا:

```text
Product ID
→ wc_get_product()
→ get_attributes()
```

Customer API عامة وقد تُستدعى كثيرًا، لذلك استخدمنا جداول Taxonomy Relationships مباشرة لاكتشاف الخصائص المستخدمة.

الفكرة ليست "SQL دائمًا أفضل"، لكن في هذه المسألة نحن نحتاج علاقات Taxonomy فقط، فلا داعي لتحميل Product Objects كاملة.

## configured vs inherited vs inferred

```text
configured
= الفئة نفسها عليها Custom Rule

inherited
= أقرب Parent عليه Custom Rule

inferred_from_products
= لا توجد Rule صريحة، فاستنتجنا من المنتجات

none
= يوجد قرار صريح بعدم وجود Attributes
```

## لماذا غيرنا by-slug؟

المسار:

```text
/products/by-slug/product-name
```

كان ينتهي بنفس slug صفحة WooCommerce، فـWordPress كان يستطيع عمل Canonical Redirect.

استخدمنا بدلًا منه:

```text
/products/lookup?slug=product-name
```

الـID يظل المرجع الرئيسي:

```text
/products/prd_21417
```

## لماذا نمنع Variation كمنتج مستقل؟

الـVariation جزء من Parent Product مثل:

```text
iPhone 256GB / Black
```

ولا يجب أن يظهر كبطاقة منتج مستقلة في Home أو Search.

لذلك Public Catalog يعرض Parent Products فقط، والـVariations تظهر داخل Product Detail.


---

# تحديث مذاكرة 0.1.3 — الأداء والتوثيق

> **HISTORICAL / SUPERSEDED:** محفوظ كسجل لبدء طبقة الكاش. سياسة الكاش الحالية تُؤخذ من `docs/PERFORMANCE.md` و`docs/HANDOFF.md`.

## ليه Cache مهمة؟

بدون Cache:

```text
كل Request
→ Query
→ wc_get_product
→ Seller
→ Brand
→ JSON
```

ولو 100 عميل طلبوا نفس Home خلال دقيقة، نكرر نفس الشغل 100 مرة.

مع Cache قصيرة:

```text
أول Request = MISS
→ نبني النتيجة

الطلبات التالية = HIT
→ نرجع Read Model جاهزة
```

المصدر الحقيقي يفضل WooCommerce.

## هل Cache ممكن تعرض مخزون قديم؟

لفترة قصيرة جدًا نعم، لذلك:

```text
Catalog = معلومات تصفح
Checkout = إعادة تحقق إلزامية من السعر والمخزون
```

دي قاعدة مهمة في التجارة الإلكترونية.

## إزاي أعرف Cache اشتغلت؟

من Network Headers:

```text
X-ElDokan-Cache: MISS
```

ثم في الطلب التالي غالبًا:

```text
X-ElDokan-Cache: HIT
```

وكمان:

```text
Server-Timing: eldokan;dur=...
```

## ليه بنحط Documentation جوه البلجن؟

عشان المعرفة ما تبقاش عند شخص واحد.

لو المشروع اتفتح في Chat جديد أو Work أو لمطور جديد، الملفات دي تعطيه:

```text
ما هو المشروع؟
ليه اتبنى بالشكل ده؟
كل ملف بيعمل إيه؟
إيه اللي اتجرب؟
إيه اللي لسه ناقص؟
إيه القرارات اللي ممنوع نكسرها بدون قصد؟
إزاي نقيس الأداء؟
إزاي نطلع Release؟
```

ابدأ دائمًا:

```text
README.md
→ docs/HANDOFF.md
→ docs/FILE-MAP.md
```
