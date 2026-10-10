# إعداد Checkout من Plugin 0.8.0

## الملف الذي ترفعه

`artifacts/eldokan-checkout-setup-0.1.1.zip` إضافة مساعدة مستقلة. ثبّتها وفعّلها بجانب `eldokan-customer-api` الموجود؛ لا تستبدل إضافة المدير بها.

واجهة الإضافة المساعدة 0.1.1 أصبحت بالإنجليزي. لو النسخة 0.1.0 مفعلة، عطّل الإضافة المساعدة القديمة أولًا ثم ارفع وفعّل 0.1.1. الإعدادات المحفوظة تظل موجودة.

- **Storefront origin (HTTPS):** دومين واجهة Next.js فقط، مثل `https://www.eldokan.com` إذا كانت الواجهة تعمل عليه بالفعل. لا تضع رابط Callback في هذه الخانة.
- **WordPress Paymob callback URL:** خانة مستقلة للنسخ تعرض رابط WordPress الفعلي تلقائيًا، مثل `https://www.eldokan.com/?wc-api=paymob_callback`. يوضع في إعدادات Callback / Webhook في Paymob.
- عند إدخال رابط غير صالح يظهر الخطأ داخل صفحة الإعدادات، ولا تتغير الإعدادات المحفوظة سابقًا.

1. افتح WordPress → Plugins → Add New → Upload Plugin وارفع الملف.
2. افتح WooCommerce → ElDokan Checkout.
3. اكتب رابط واجهة المتجر HTTPS فقط، دون مسار. مثال: `https://shop.example.com`. لا تضع دومين WordPress إذا كانت الواجهة على دومين آخر. محول المدير يشترط HTTPS؛ لا يقبل http://localhost، لكنه لا يمنع https://localhost. للتجربة المحلية اتبع الخطوات أدناه.
4. اختر بوابة Paymob hosted الفعلية. فعّلها واضبط مفاتيحها من إعدادات Paymob الأصلية.
5. لا تفعّل مربع Accept TRANSACTION إلا بعد تأكيد نوع Callback في حساب Paymob مع المدير. المربع يضبط خيار الـAPI ولا يغيّر إعداد حساب Paymob.
6. الصفحة تعرض نسخة Paymob المثبتة. `eldokan-customer-api` 0.8.0 يشترط `PAYMOB_VERSION === 4.1.15` حرفيًا. إن كانت النسخة مختلفة، يحتاج المدير مراجعة توافق المحول مع النسخة المثبتة؛ لم نزل هذا القيد.
7. من Shipping zones اجعل منطقة تغطي المحافظة، وطريقة Flat rate مفعلة بتكلفة 100 جنيه إذا كانت هذه التكلفة المطلوبة. Flat rate مدعومة تلقائيًا. لا تكتب تكلفة في الواجهة.
8. الطرق المخصصة (مثل شحن إضافات السوق) يمكن تصنيفها في الشاشة إلى توصيل أو استلام حسب عملها الحقيقي. التصنيف لا يصلح package بلا rates ولا ينشئ مناطق أو أسعارًا، ولا يربط Bosta API.
9. افتح Checkout في الواجهة وراجع الإجمالي. إذا ظهرت أكثر من طريقة شحن، اختر الطريقة المطلوبة ثم Paymob.
10. الطلب يتحول لصفحة الدفع؛ بعد الرجوع تُقرأ حالة الطلب من السيرفر. الفاتورة المدفوعة تظهر فقط عندما يقول السيرفر إن الدفع تم.

## إصلاحات الواجهة

- الرجوع إلى `/orders/ord_…` يستخدم استعادة نفس الطلب المحفوظ حتى للضيف، بدل إجباره على تسجيل الدخول. يتم التحقق من تطابق رقم الطلب، وتبقى صلاحية القراءة على الـBFF/Customer API.
- قراءة `payment.issue` تدعم كودًا نصيًا كما في PHP 0.8.0، وكذلك كائن Issue كما في عقد SDK.
- عند تعدد طرق الشحن يجب اختيار طريقة صراحة؛ لا تُختار أول طريقة (وقد تكون استلامًا) تلقائيًا.
- تعطيل تغيير الشحن والدفع أثناء العملية.

## حدود التسليم

تمت مراجعة PHP المرفق ومواءمة الواجهة معه. مصدر إضافة المدير محفوظ دون تعديل في `wordpress-plugin-source/eldokan-customer-api` للمراجعة. أفاد المستخدم بتثبيت الإضافة المساعدة على WordPress؛ لم تُنفذ من طرفنا عملية دفع أو إنشاء طلب حقيقي. بيئة التطوير لا تحتوي PHP أو WordPress لتشغيل الإضافة محليًا. يلزم أن يحفظ المسؤول الإعدادات ويجرب Sandbox قبل اعتمادها للإنتاج.

## للمدير إن استمرت مشكلة الشحن

محول `class-eldokan-customer-api-checkout-woo.php` لا يرجع وسيلة شحن إذا كان أي package بلا rate مدعوم، أو إذا كانت الحزم تجمع توصيلًا واستلامًا فقط بدون اختيار متجانس. افحص rates الفعلية لكل package ومنطقة الشحن وإعداد البائع. تصنيف الطريقة المخصصة يعالج استبعاد rate موجود فقط؛ لا يعالج غياب الـrate.

إن لم يظهر Paymob بعد اكتمال الشحن، افحص: إصدار 4.1.15، frontend origin HTTPS، callback profile `accept_transaction`، gateway ID، تفعيل البوابة وإعداد integration IDs، وكون `process_payment` من `Paymob_Payment` كما يشترط كود المدير. لا تُرسل مفاتيح أو client secrets في التقرير.

## تجربة الدفع من الجهاز المحلي

1. أوقف سيرفر التطوير الحالي بـ Ctrl+C، ثم شغّل من مجلد المشروع:

   ```powershell
   npm.cmd run dev:https
   ```

   Next.js ينشئ شهادة تطوير محلية وقد يطلب تثبيت الشهادة. افتح `https://localhost:3000` وتأكد أن المتصفح يثق بالشهادة المحلية قبل بدء الدفع.

2. في WooCommerce → ElDokan Checkout احفظ `https://localhost:3000` في Storefront origin. افتح الواجهة بنفس الرابط ونفس المتصفح عند إجراء الطلب والعودة إليه.
3. اضبط إضافة Paymob الأصلية على Test، واستخدم مفاتيح وIntegration IDs الخاصة بوضع Test. اختيار Test في واجهة أخرى وحده لا يكفي إذا ظلت البوابة تستخدم بيانات Live.
4. رابط WordPress Paymob callback URL يظل عنوان WordPress العام الظاهر في الإضافة، وليس localhost. اضبطه على تكاملات Paymob التجريبية المستخدمة، وراجع توافق Accept TRANSACTION.
5. أضف منتجًا للسلة واملأ العنوان، ثم حدّث الشحن والدفع واختر Paymob. استخدم بطاقة اختبار Paymob على صفحة Paymob فقط.
6. بعد العودة راجع حالة الطلب. إذا كانت معلقة، استخدم تحديث الحالة لنفس الطلب؛ تأكيد الدفع يعتمد على Callback الخادم.

المحلي HTTPS ممكن وفق فحص frontend_origin في مصدر 0.8.0؛ لم يتم تنفيذ دورة دفع كاملة بهذا الإعداد حتى الآن. إذا استُخدم دومين تجربة أو منفذ مختلف، يجب أن يطابق Storefront origin الرابط الفعلي المستخدم في المتصفح.

مراجع Paymob: [إعداد إضافة WooCommerce](https://developers.paymob.com/paymob-docs/integration-paths/plugins/wordpress)، [بيانات الاختبار في مستودع Paymob الرسمي](https://github.com/PaymobAccept/Paymob-AI-Integration-Skill/blob/main/skills/paymob-integration/references/test-credentials.md).

## متابعة الكود في 10 أكتوبر 2026

- تعبئة العنوان الافتراضي الذي يرجعه Customer API، مع الحفاظ على البيانات التي عدّلها العميل عند تغيير اللغة.
- تحويل أرقام الهاتف العربية والفارسية إلى أرقام ASCII، وتنظيف المسافات قبل إرسال العنوان.
- تحديث ملخص السلة من آخر quote، واختيار Paymob تلقائيًا إذا كان متاحًا ولم يكن العميل قد اختار وسيلة أخرى.
- تعطيل تعديل العنوان والملاحظات أثناء إرسال الطلب أو وجود محاولة محفوظة.
- إتاحة بدء شراء جديد بعد تأكيد حالة الطلب السابق من السيرفر: المدفوع، الملغي، المسترد، أو طلب الدفع عند الاستلام الذي تم قبوله. المحاولة غير المحسومة والدفع المعلق يظلان محفوظين.
- حفظ الوصول إلى آخر 3 طلبات سابقة للضيف في كوكي HttpOnly لنفس المتصفح لمدة 24 ساعة. يظل الوصول خاضعًا لتفويض Customer API وصلاحية بيانات الضيف. الرابط وحده لا يمنح الوصول، وهذه الميزة ليست أرشيفًا دائمًا للطلبات.
- إتاحة استعادة المحاولة السابقة من صفحة النتيجة حتى عندما أصبحت السلة فارغة.
- توحيد تفاصيل الفاتورة وطباعة الفاتورة المدفوعة في نتيجة Checkout وصفحة الطلب من سجل الطلبات.
- متابعة حالة الدفع من API بطلبات قراءة متتابعة ومحدودة، دون إنشاء جلسات دفع أو تغيير حالة الطلب أثناء المتابعة.
- ترجمة حالات الطلب والدفع، وإخفاء استعادة الدفع للطلبات المدفوعة أو الملغاة أو المستردة.

تم فحص TypeScript وESLint للملفات المعدلة. لم تُنفذ دورة دفع Sandbox أو عملية إنشاء طلب على السيرفر. محاولة قراءة health من أداة التصفح لم تكن متاحة؛ هذا لا يثبت توقف الـAPI.
## بدء طلب جديد مع وجود طلب سابق — 10 أكتوبر 2026

يمكن الآن بدء طلب جديد بالسلة الحالية حتى عندما يكون الطلب السابق بانتظار الدفع أو غير مدفوع. يظهر زر «ابدأ طلب جديد بالسلة الحالية». يتحقق BFF من وجود الطلب السابق وتطابق مرجعه، ويحفظ وصول الضيف إلى صفحة الطلب السابق قبل إنهاء كوكي الاستعادة الحالية. لا يلغي الطلب السابق ولا ينشئ جلسة دفع إضافية له، ولا يغيّر محتويات السلة الحالية.

الطلب المعروف والمقروء لا يمنع شراءً جديدًا بسبب حالة الدفع. محاولات الإنشاء التي ليس لها رقم طلب مؤكد تظل تتطلب استعادة نفس المحاولة لتجنب تكرار الإنشاء. احتفاظ الضيف ما زال لآخر 3 طلبات على نفس المتصفح لمدة 24 ساعة، وخاضعًا لصلاحية تفويض الـAPI. تجاوز الفحوص المحلية: TypeScript وESLint؛ لم تنفذ عملية شراء على السيرفر في هذه المتابعة.
# Installment plans inside storefront Checkout (local API 0.8.5)

Upload `artifacts/eldokan-customer-api-0.8.5-installment-invoice-fix.zip` as a replacement for the active Customer API. Enable the native Bank Installments gateway, then enable Paymob's Affordability Widget and explicitly save that same integration. Keep the helper's Bank installments mapping matched to it. The Next.js Checkout renders the actual Paymob plan selector and carries the selection into payment recovery. Invoices show the recorded duration and monthly estimate separately from the store total. This release fixes rejection of ten-field installment material in older prepared attempts; recover the same attempt after uploading. No database changes or attempt deletion are needed. Older completed orders without display information show plan ID only. PHP execution and real payment verification remain outstanding locally.
# Customer cancellation and account orders (local API 0.8.6)

Replace the active Customer API with `artifacts/eldokan-customer-api-0.8.6-order-cancellation.zip` for the new cancellation endpoint. This includes the previous checkout/installment/invoice fixes. Account → My orders now leads to `/account/orders`; orders were removed from the navbar. Eligible unpaid COD orders can be cancelled before dispatch after providing/reviewing a reason. Paid and Paymob orders submit a cancellation request for store review, without claiming a completed refund. See `CANCELLATION.md` in the ZIP for authorization, state and verification limits.
