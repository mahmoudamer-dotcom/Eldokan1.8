# مصفوفة الوظائف والشاشات

الرموز: **E** اختبار تنفيذي معزول؛ **C** دوال نواة WordPress فعلية مع نماذج المستخدم/البيانات؛ **S** مراجعة مصدر؛ **R** قبول حقيقي لم ينفذ؛ **D** يعتمد على إضافة/خدمة. نجاح E أو C لا يساوي قبول الإنتاج.

| الشاشة / المسار | المعالجات الأساسية | التصنيف والنتيجة | المطلوب حيًا |
|---|---|---|---|
| Dashboard `eldokan-admin` | dashboard / overall_metrics | E,C,S؛ القالب والروابط والعينة مثبتة | R: مطابقة البيانات وتعريف KPIs |
| Sellers `eldokan-admin-sellers` | sellers / seller_detail / seller_status / rate_seller | E,C,S؛ منع الوصول، تغيير الحالة، parent-owner، وقسم UI معزول | R,D: حساب تاجر فعلي وتوافق Seller API |
| Registrations `eldokan-admin-registrations` | registrations / registration_status / delete_registration / view_registration_pdf | E,C,S؛ قبول/رفض وحماية nonce وأدوار وسجل، رفض الحذف/PDF دون إذن | R,D: تسجيل كامل وPDF وحذف بيانات اختبار مع إعادة الإسناد |
| Orders `eldokan-admin-orders` | orders / update_order_status | E,C,S؛ تحديث نفس كائن الطلب مرة واحدة، تكرار الحالة، رفض الحالة غير المدعومة | R,D: WooCommerce/HPOS وخطافات Seller API |
| Products `eldokan-admin-products` | products / save_product_commission | E,C,S؛ ٢٠٥ سجلات على صفحات حقيقية في نموذج الاستعلام، فلاتر وروابط ونماذج | R,D: SQL/WPML والبحث الفعلي؛ التحرير عبر WooCommerce الأصلي |
| Categories `eldokan-admin-categories` | categories / category attributes panel, save, resolve, dispatch | E,C,S؛ حفظ allowlist خصائص وتوريث وnone، رفض الرسوم دون صلاحية | R,D: REST الرسمي للـSeller API وتحرير term |
| Reports `eldokan-admin-reports` | reports / seller_financial_report_rows / summary / export_seller_report_excel / pdf | E,C,S؛ ٣٢٠ طلبًا، المدفوع والاسترداد الجزئي واللقطة والمطابقة الحسابية، رفض التصدير غير المصرح | R,D: WC taxes/refunds، توليد الملفات، بيانات حقيقية |
| Commissions `eldokan-admin-commissions` | commissions / save_commission / delete_commission / category/product fee handlers | E,C,S؛ guard لجميع المعالجات، وصيغ percentage/hybrid/cap | R,D: دورة تعديل القواعد وربط الفئات والطلبات وتاريخ اللقطات |
| Statements `eldokan-admin-statements` | statements / seller_order_metrics | E,C,S؛ القالب والصلاحية وحد العينة مثبت | R: تطابق رسمي، لا يستخدم للصرف |
| Performance `eldokan-admin-performance` | performance / automatic_cancel_rating | E,C,S؛ عرض/منع ونطاق العينة موضح | R: تعريف موحد للإلغاء وحالات rejected وغيرها |
| General `eldokan-admin-settings` | settings | E,C,S | R: الروابط الأصلية والتكاملات |
| Checkout `eldokan-checkout-setup` | page / save / payment_readiness / quote capture | E,C,S,D؛ ٣٣ تحققًا موروثًا تشمل المفاتيح والتصنيف والصلاحيات والـnonce | R: Paymob 4.1.15 وCOD والشحن وwebhook sandbox، دون دفعات حقيقية |
| Email `eldokan-admin-email-settings` | email_settings / save_email_settings / send_test_email | E,C,S؛ حفظ وتعقيم وحدود عرض الشعار، إرسال الموافقة إلى sink فقط، رفض الإرسال غير المصرح | R,D: SMTP ورفع صورة وتسليم بريد فعلي |
| Staff `eldokan-admin-team-permissions` | render_permissions_page / save_role_permissions / reset_role_permissions | E,C,S؛ حفظ الدور ورفض grant عشوائي مثل manage_options | R: مستخدمون حقيقيون وحفظ/استعادة الدور والـnative bridges |
| Seller templates `eldokan-admin-access-roles` | access_roles / save/delete/assign_access_role / account handlers | E,C,S؛ قوالب وقوائم، guards جميع الكتابات | R,D: أثر القالب في جلسات Seller API |
| Dashboard controls `eldokan-admin-dashboard-control` | dashboard_control / save_dashboard_control | E,C,S؛ الصلاحية داخل العرض مضافة، حفظ غير مصرح مرفوض | R,D: أثر الإعداد على لوحة التاجر |
| Activity `eldokan-admin-activity` | activity / export_activity_csv / write_audit_log / capture hooks | E,C,S؛ عرض/منع، redaction للحقول الحساسة العليا وسجل النموذج | R,D: جميع مصادر الأحداث وCSV وبيانات حقيقية؛ لم يختبر redaction متداخل شامل |
| WCFM index `eldokan-admin-wcfm-index` | wcfm_index_page / index_* / migration_* / verification_* | C,S,D؛ وصول وguard وعدم تغيير المعالجات | R: فهرس/Schema/SQL؛ لم تشغّل فهرسة أو إصلاح أو ترحيل |
| Home `eldokan-admin-home` | render_page / save_item / delete_item / save_sections | E,C,S؛ حفظ شريحة عربية، الوسائط والترتيب والبحث وcache generation، guards | R,D: نافذة الوسائط وWPML وجدولة وتوصيل المحتوى الفعلي |
| Stories `edit.php?post_type=eldokan_story` | register / boxes / fields / save / listing / picker AJAX | E,C,S,D؛ حفظ وملكية/لغة/انتهاء وCPT/caps والانتقال من Products | R,D: محرر WordPress الكامل، AJAX فعلي وتوصيل Customer API |
| REST Admin/Shared | rest_routes / category attributes dispatch / Stories listing | E,S,D؛ guard/routes محفوظة نصيًا، وحدات Stories دون تكرار | R: جلسات وتفويض وعقود فعلية؛ لا تغييرات للمسارات |

جميع معالجات admin-post/AJAX الموجودة في النموذج خضعت لاختبار الرفض قبل الكتابة، ويورد JSON أسماءها. هذا لا يدعي اختبار المسار الناجح لكل معالج. `ALL-FUNCTIONS.json` يحصر كل دالة ودرجتها، و`HOOK-INVENTORY.json` يسرد الارتباطات من المصدر.

## وظائف غير موجودة أو لا يمكن اعتماد وجودها

لا توجد شاشة Customers مسجلة، ولذلك العداد بلا رابط. لا يوجد هنا محرك صرف وتسويات أو بديل شحن متعدد التجار مثبت. تحديث حالات الطلبات لا ينشئ سياسة إرجاع أو عملية استرداد مالي كاملة. لم تُضف خلفية لهذه الميزات؛ العقود والوظائف الموجودة في الواجهات الأخرى بقيت كما هي.
