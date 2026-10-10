# تقرير متابعة Checkout وPaymob لمسؤول WordPress

## العطل المرصود

واجهة ElDokan تعرض السلة وسعر المنتج (149 EGP)، لكن مراجعة checkout لعنوان القاهرة ترجع:

`No supported shipping method covers the complete cart.`

آخر مرجع ظاهر في لقطة الشاشة:

`req_cea566c2a23941b2a5be152dc341f0d5`

لا تتوفر خيارات شحن، والإجمالي غير متاح، وتظل وسائل الدفع غير قابلة للحساب. هذا لا يثبت أن Paymob معطل؛ الشحن والإجمالي يمنعان الوصول لمرحلة الدفع. الحالة مستندة إلى صور المستخدم؛ لم يتم الحصول على JSON كامل لهذا الطلب.

## المطلوب من مسؤول السيرفر

1. تتبع رقم المرجع في سجلات إضافة `eldokan-customer-api` لطلب `POST /wp-json/eldokan-customer/v1/checkout/quote`.
2. تأكيد العنوان الذي استقبله السيرفر: `country: EG` وكود المحافظة المثبت في WooCommerce. الواجهة تستخدم `EGC` للقاهرة إذا كان ضمن أكواد API المعلنة، وتعرض الاسم مترجمًا فقط.
3. مقارنة نفس المنتج والعنوان في checkout الأصلي الخاص بـWooCommerce.
4. فحص تقسيم السلة إلى shipping packages، إعداد شحن البائع، اختيار المنطقة، والطرق المتاحة لكل package. الموقع يحتوي إضافات WCFM؛ تأثيرها على هذه الحالة يحتاج فحصًا ولا يعتبر سببًا مثبتًا.
5. مراجعة تحويل طرق WooCommerce الفعلية إلى خيارات `shp_*` التي يدعمها Customer API. لا نفترض أن Flat rate غير مدعوم؛ المطلوب فحص نتيجة المحول الفعلية.
6. إذا المطلوب شحن 100 جنيه مؤقتًا، ضبط التكلفة على السيرفر وإرجاعها ضمن quote والإجمالي الفعلي. لا يكفي عرض رقم في الواجهة.
7. بعد إصلاح الشحن، تأكيد أن `payment_availability_calculable` يسمح بالحساب، وأن `payment_methods` تتضمن `paymob` مع `available: true` عندما يكون مؤهلًا.
8. تأكيد توافق نسخة بوابة Paymob وإعداد hosted flow مع إضافة Customer API. مستند الحزمة يشير إلى Backend 0.8.0 وnative Paymob 4.1.15 / Accept TRANSACTION POST؛ النسخ الفعلية على الموقع لم يتم التحقق منها.
9. إتمام تجربة Sandbox: quote جاهز بإجمالي، prepare attempt، إنشاء نفس الطلب مرة واحدة، رابط دفع HTTPS، callback، ثم قراءة حالة الطلب authoritative كـpaid. رجوع المتصفح وحده لا يؤكد الدفع.

## ما يلزم إرساله للواجهة بدون ملفات الإضافة

- رد JSON لطلب quote المرتبط بالمرجع بعد إزالة بيانات العميل وأي tokens أو credentials.
- أكواد issues، خيارات الشحن المتاحة وأنواعها، totals، وpayment eligibility.
- نتيجة تجربة checkout الأصلي لنفس السلة والعنوان، والنسخ المثبتة من Customer API وPaymob.
- أي خطأ initiation/callback مع code وrequest_id، دون مفاتيح Paymob أو رابط جلسة الدفع.

## ما عولج محليًا

- حفظ CSRF من checkout واستعادة جلسة السلة عند الحاجة.
- تحديث كوكيز API خلال الطلب الواحد وإعادتها للمتصفح في مساري customer proxy وorder recovery، بما يشمل ردود الخطأ.
- اختيار الشحن والدفع من آخر quote وعدم إنشاء خيارات أو إجمالي وهمي.
- الاحتفاظ بـissues وrequest IDs وفحص حقول retry وGuest credentials.
- منع استبدال محاولة محفوظة بمحاولة جديدة، والتحقق من حالة الاستعادة قبل بدء شراء.
- ربط تحديث لغة المفضلة، وإتاحة تحديث quote وحالة الطلب.

## طلب إضافي: API التقييمات والتعليقات

تمت إضافة واجهة محلية مؤقتة للتقييمات والتعليقات. المطلوب تسليم عقد API رسمي قبل ربط النشر العام:

- قائمة paginated للمنتج مع sort/filter ومتوسط النجوم وrating_count وتوزيع النجوم المحسوب على السيرفر.
- إنشاء وتعليق وتعديل وحذف بتفويض العميل، مع تعريف واضح لسياسة أكثر من تقييم لنفس المنتج ومواعيد التعديل.
- تحقق rating من 1 إلى 5، وحدود اسم/عنوان/نص، وتنظيف المحتوى، وmoderation ومكافحة السبام.
- هوية المراجع العامة فقط، وverified_purchase محسوبة من الطلبات الفعلية، وعدم كشف بريد/هاتف العميل.
- أخطاء معيارية وrequest IDs وCSRF/session وفق نمط Customer API الحالي.
- تحديد ما إذا كانت التعليقات على product ID للبائع أو catalog ID موحد، خصوصًا عروض نفس المنتج من عدة بائعين.

لم يتم افتراض endpoint أو ربط POST غير موجود. التعليقات المحلية لا ترفع تلقائيًا ولا تدخل في متوسط التقييم العام.

## حدود المراجعة

الحزمة `eldokan-customer-api-client-0.6.0.tgz` تحتوي مكتبة TypeScript/JavaScript ومستندات؛ لا تحتوي PHP للسيرفر. طلب health من بيئة التطوير الحالية مُنع برمز الشبكة `EACCES`؛ لذلك لا نستنتج أن الموقع متوقف. لم يتم إنشاء طلب تجريبي أو إجراء دفع حقيقي، ولا يمكن تأكيد نجاح Paymob حتى يمر السيناريو على السيرفر.

## مراجعة مصدر PHP المرسل في 8 أكتوبر 2026

وصلت إضافة `eldokan-customer-api-v0.8.0.zip` وتمت مراجعة كود Checkout وPaymob الحقيقي. هذا يحدّث حدود المراجعة القديمة أعلاه التي كانت تستند إلى SDK فقط.

- طرق `flat_rate` و`free_shipping` و`local_pickup` مدعومة؛ الطرق الأخرى تحتاج تصنيفًا صريحًا. كل shipping package يجب أن يقدم rate متوافقًا.
- Paymob يتطلب 4.1.15 حرفيًا، وخيارات frontend origin HTTPS وcallback profile وgateway. ملف المدير لا يوفر شاشة إعداد لهذه الخيارات؛ جهزنا إضافة إعداد منفصلة باستخدام خياراته وفلتره الأصليين.
- عولج تعارض الرجوع للضيف إلى صفحة `/orders/ord_…` التي كانت تتطلب تسجيل الدخول، مع إبقاء بيانات الوصول في BFF والتحقق من تطابق رقم الطلب.
- عولج اختلاف `payment.issue`: PHP يرجع string بينما SDK يتوقع Issue object.
- لم يتم تثبيت أو تفعيل شيء على WordPress البعيد، ولا تأكيد Checkout أو دفع حقيقي. خطوات المسؤول والملف الجاهز في [CHECKOUT-SETUP.md](CHECKOUT-SETUP.md).

## متابعة الواجهة في 10 أكتوبر 2026

تمت إضافة الفاتورة إلى صفحة الطلب في سجل الطلبات، وتحديث حالة الدفع تلقائيًا بطلبات قراءة محدودة. أُصلح منع شراء جديد بعد انتهاء الطلب المحفوظ، مع التحقق من حالة الطلب على السيرفر قبل إنهاء محاولة الاستعادة الحالية، وحفظ وصول الضيف لآخر 3 طلبات على نفس المتصفح في كوكي HttpOnly لمدة 24 ساعة.

لم يتغير محول Paymob أو Callback أو حساب أسعار الشحن في PHP. ما زالت تجربة Sandbox كاملة مطلوبة لتأكيد: خيارات الشحن والإجمالي، ظهور Paymob، التحويل إلى صفحة الدفع، وصول Accept TRANSACTION والتحقق من HMAC، والعودة إلى حالة paid في قراءة الطلب.

لم يمكن قراءة health من أداة التصفح في هذه المتابعة؛ لا توجد استجابة HTTP يمكن اعتمادها لإثبات تعطل السيرفر. لا تطلبوا إرسال مفاتيح Paymob أو بيانات وصول الضيف أو رابط جلسة الدفع لتشخيص المشكلة.
## Local payment options extension — 10 October 2026

The user confirmed shipping works after the local 0.8.1 singleton-context fix, then requested COD, card and bank installments in checkout. The frontend now renders native available choices and re-quotes payment selection through the local 0.8.2 extension. Checkout Setup 0.1.3 maps public card/installment options to distinct native hosted single-integration Paymob gateways and exposes an explicit WooCommerce COD toggle. Existing COD restrictions and the audited native callback remain active.

See `wordpress/eldokan-customer-api-options/eldokan-customer-api/PAYMENT-OPTIONS.md` for additive API fields, deployment, source changes and runtime limits. Bank/plan selection is still on Paymob; a complete bank-plan selector inside Next.js is not implemented. Individual payment option support requires both prepared ZIP updates and correctly configured native integrations. PHP execution and sandbox payment checks remain outstanding; TypeScript and targeted ESLint passed.