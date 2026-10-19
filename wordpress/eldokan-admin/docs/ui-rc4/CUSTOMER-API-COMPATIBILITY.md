# توافق Customer API Client — إضافة القراءة فقط

المرفقات المعتمدة: Customer API 0.10.0، Seller API 1.75.70-rc.1، Client 0.8.2-rc.2. لم يتغير أي ملف في هذه المدخلات: تحقق hash لجميع **309 ملفات** قبل/بعد الفحوص. اختبارات العميل التي تكتب observations شُغلت على نسخة مؤقتة منفصلة؛ لا يُضم العميل إلى plugin ZIP.

## النتيجة

نجح تطابق **50 path / 65 method+path** في OpenAPI الحالي للعميل مع تسجيل routes المنفذ من مصادر PHP المرفقة. تشمل العمليات المحمية بالـcookies وCSRF والـPOST/PUT/PATCH aliases. `/stories` يقدمه Admin Staff الموحّد، وليس تسجيلًا مكررًا داخل Customer API. 155 schemas في snapshot الحالي؛ لم يُعدّل frozen Contract v1 التاريخي.

نجحت **39 اختبارات Node** للـdist المرفق، ونجح مدققا الحزمة JavaScript وPython. نتيجة النقل تقارن 40 عملية سابقة و25 إضافة RC2. المخرجات: client-tests.tap وcustomer-client-compatibility.json وcustomer-route-inventory.json. يجري اختبار المسارات وقيم الطلبات/headers بواسطة fetch مصطنع؛ لا تمثل هذه النتائج تطابق جميع responses الحقيقية مع كل schema.

| البند | ما تحقق محليًا | القبول الباقي |
|---|---|---|
| الموارد العامة واللغة | paths/params والقيم وعزل credentials حسب المورد | WPML والسعر والمخزون والـcache على Woo فعلي |
| تسجيل الدخول والحساب والسلة | cookies وCSRF في الذاكرة وحقول envelope والأخطاء | HttpOnly/SameSite/CORS وتدوير/انتهاء الجلسات في المتصفح |
| Checkout وPaymob | payloads وattempt/order IDs وعدم اختراع طرق دفع، وعدم retry تلقائي | quote حقيقي، webhook Sandbox، منع تكرار طلب/دفعة، partial failure |
| الطلبات والإلغاء | opaque IDs، validation، method/route، عدم تسريب credentials، single mutation | أهلية الحالات وخطافات Woo/Seller API والتزامن |
| Guest والمرتجعات | header capability لا URL/body، dedicated CSRF للكتابة بلا fallback إلى customer/cart، GET بلا CSRF | التفويض الفعلي بين عميل/ضيف/موظف، الأقفال، تسلسل الإرجاع والاسترداد |
| Reviews/alerts/decision/Stories | 25 عملية RC2 وحدود input ذات الصلة وقيم النقل | رفع وسائط فعلي، moderation، mail/cron، expiry وترجمة القصص |

**لم ينفذ:** typecheck/build من TypeScript لعدم توفر compiler في البيئة؛ اختُبر dist المرفق فقط. لم تُعد توليد الأنواع أو build داخل المصدر، ولم يشغّل smoke حي أو Customer Frontend أو بناء Next.js؛ كلها خارج تنفيذ Admin الحالي.

## ملاحظات المصدر

- README يحتفظ بجملة تاريخية «Current release 0.6.0» رغم تنويه RC2 في مقدمته؛ المرجع الأدق package.json وRC2-INTEGRATION.md. عيب توثيق قائم، لم نعدّل العميل.
- transport يفحص envelope العام ولا يفرض runtime schema لكل data؛ نجاح TypeScript أو route matching لا يغني عن schema/response integration tests لاحقة.
- طلب الإرجاع يسجل بياناته وحالته على الطلب ويذكر صراحة عدم إصدار refund. يلزم refund منفصل في Woo/مزود الدفع ومطابقة عكس العمولة والتسوية.
- اختلافات seller ownership الواردة في WCFM-DEPENDENCY-ASSESSMENT.md تمنع وصف منظومة السوق كلها بالمستقلة أو المتطابقة اعتمادًا على نجاح العميل فقط.

القرار: **متوافق على مستوى العقد والنقل الذي فُحص محليًا، مع قبول تكاملي فعلي لم ينفذ.** لم تتغير routes أو الردود أو قواعد العميل أو الواجهات الأخرى في هذا checkpoint.
