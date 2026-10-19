# التثبيت والقبول والرجوع — مرشح غير معتمد للإنتاج

لم يُنشر RC4. المطلوب مراجعة مستقلة ثم تثبيت في بيئة staging مماثلة للموقع. لا تثبّت Checkout أو Stories كإضافتين منفصلتين؛ هما مدمجان. أبقِ WCFM وخدماته مفعّلة.

## قبل التثبيت

1. سجّل نسخ WordPress/PHP/WooCommerce/WCFM/WPML/Paymob والإضافات التابعة الفعلية، وبصمة Admin المركب. اختبارات وظائف نواة القوائم هنا استخدمت مصدر WordPress 7.1.3 منزّلًا للاختبار؛ ليست توصية بترقية WordPress أو بيانًا لنسخة Live.
2. خذ snapshot متسقًا لقاعدة البيانات وملفات plugins/uploads وخيارات وأدوار الموظفين؛ احتفظ بملف Admin السابق المطابق وجرّب الاستعادة.
3. استخدم بيانات اختبار، بريد sandbox وPaymob sandbox. تأكد أن staging لا يطلق cron/webhooks/رسائل نحو عملاء حقيقيين.
4. تحقق من SHA256 للحزمة. استبدل مجلد `eldokan-admin` في بيئة الاختبار بنفس slug والـbootstrap. استخدم التحديث العادي دون uninstall أو reset. امسح cache الأصول فقط وتأكد من ظهور rc.4.

RC4 لا يضيف schema أو option keys أو migrations. لكن plugin الأساس يملك أصلًا schema ensure على admin_init، وقد تشغّل صفحة WCFM Index dbDelta/ALTER إذا كانت الجداول أو الأعمدة ناقصة؛ هذه المسارات محفوظة. لا يُفهم من «تحديث UI» أن تفعيل النسخة على قاعدة غير مكتملة لا يكتب شيئًا. لا تضغط scan/rebuild/repair/migration أثناء القبول البصري.

## بوابات القبول الفعلي — كلها لم تنفذ هنا

| البوابة | المطلوب واعتماد النتيجة |
|---|---|
| التنقل والصلاحيات | المدير يرى Dashboard/Sellers/Orders/Products/Reports/Settings بالترتيب؛ كل URL قديم يعمل؛ طفل-only يصل لوظيفته؛ حساب بلا cap ممنوع؛ Stories إبراز Products |
| حدود الأصول | صفحات WordPress/Woo/WCFM وedit product/order لا تحمل workspace إلا شاشة Story المحددة؛ لا collision مع notice/media/metabox |
| العربية/الإنجليزية | لغة مستخدم ar/en، desktop1440/tablet768/mobile390، لا overflow عام؛ تمرير جدول داخلي وpagination/filter، focus وترتيب Tab وEscape |
| نماذج الموظفين | منع رفع الصلاحيات، nonce منتهي، حفظ/استعادة roles، مستخدمان بصلاحيات مختلفة؛ قوالب التاجر تُرى في Seller API كما قبل |
| التجار والمنتجات | تسجيل/قبول/رفض على بيانات اختبار، owner/team/canonical، تعليق/تفعيل، بحث وصفحات، خصائص الأقسام والرسوم ومراجعة WPML |
| الطلبات والمال | الحالة تُحفظ على نفس WC order وتعمل hooks Seller؛ مصفوفة المال الكاملة ومطابقة جزئية/كلية refunds وHPOS on/off حسب الدعم الفعلي |
| Checkout | الشحن متعدد التجار، COD/Paymob readiness، quote/place/attempt/payment recovery/webhook sandbox؛ لا دفع حي |
| Stories/Home | native editor وpicker AJAX وmedia وfeatured image، draft/publish/schedule/expiry/order/translation، /stories و/home بنفس العقد |
| البريد والتصدير والتدقيق | إرسال sandbox، ملفات PDF/Excel/CSV ومحتواها وcap/nonce، السجل والتفويض والتعقيم |
| العميل والعقود | تشغيل typecheck/build للعميل المرفق ثم تكامل HTTP/cookies/CORS/Guest وresponse schemas. لا تغيير Customer Frontend في هذه المرحلة |
| الأداء | أحجام كبيرة وبطء query واقعي؛ خصوصًا قوائم الطلبات والتقارير والتجار ذات الحدود القديمة، بلا تشغيل ترحيل |

دوّن نسخة البيئة والشاشة والحساب والخطوات والنتيجة وصورة browser وrequest ID خالٍ من الأسرار لكل فشل. قبول المظهر وحده لا يعتمد المال أو WCFM retirement. أي تغيير عمليات أو API يحتاج checkpoint مستقلًا.

## الرجوع

قبل أي تشغيل أو تغيير بيانات: استبدل مجلد Admin بالنسخة السابقة المطابقة وامسح cache الأصول، ثم تحقق من الأدوار والروابط والعرض. لا تحذف tables/options/posts/metadata ولا تشغّل uninstall. إذا نُفذت عمليات عمل أثناء التجربة، فالرجوع بالملفات لا يلغيها؛ استعد snapshot متسقًا كاملًا على staging فقط أو استخدم إجراء rollback مدققًا لكل عملية. لا تستعد DB قديمة فوق طلبات/دفعات حيّة حديثة. احتفظ بـWCFM ونسخة المصدر السابقة أثناء كل الخطوات.
