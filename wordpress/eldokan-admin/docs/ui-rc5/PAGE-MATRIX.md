# مصفوفة الصفحات والصلاحيات — RC5

الفحص المحلي: منع الوصول دون الصلاحية، ومراجعة التسجيل والـaliases. اختبارات المسارات الناجحة داخل WordPress الحقيقي مؤجلة لكل الصفحات. صفحات Dashboard/Products/Customers/Commissions رُسمت بالدوال الفعلية ببيانات اصطناعية واختُبرت في Chrome بالعربية وRTL والإنجليزية.

| الصفحة | الصلاحية | المعالج |
|---|---|---|
| `eldokan-admin` | `eldokan_backoffice_view_dashboard` | `dashboard` |
| `eldokan-admin-sellers` | `eldokan_backoffice_view_sellers` | `sellers` |
| `eldokan-admin-registrations` | `eldokan_backoffice_view_registrations` | `registrations` |
| `eldokan-admin-customers` | `list_users` | `customers` |
| `eldokan-admin-products` | `eldokan_backoffice_view_products` | `products` |
| `eldokan-admin-orders` | `eldokan_backoffice_view_orders` | `orders` |
| `eldokan-admin-categories` | `eldokan_backoffice_view_categories` | `categories` |
| `eldokan-admin-dashboard-control` | `manage_options` | `dashboard_control` |
| `eldokan-admin-access-roles` | `manage_options` | `access_roles` |
| `eldokan-admin-wcfm-index` | `manage_options` | `wcfm_index_page` |
| `eldokan-admin-commissions` | `eldokan_backoffice_view_commissions` | `commissions` |
| `eldokan-admin-statements` | `eldokan_backoffice_view_reports` | `statements` |
| `eldokan-admin-performance` | `eldokan_backoffice_view_reports` | `performance` |
| `eldokan-admin-reports` | `eldokan_backoffice_view_reports` | `reports` |
| `eldokan-admin-activity` | `eldokan_backoffice_view_activity` | `activity` |
| `eldokan-admin-email-settings` | `eldokan_backoffice_manage_email` | `email_settings` |
| `eldokan-admin-settings` | `eldokan_backoffice_manage_settings` | `settings` |
| Home Management | `eldokan_backoffice_view_home` / `eldokan_backoffice_manage_home` | `render_page` / save/delete guards |
| Team Permissions | `manage_options` | `render_permissions_page` / save/reset guards |
| Checkout | `eldokan_backoffice_manage_checkout` | page/save guard |
| Stories (native CPT) | `eldokan_backoffice_manage_stories` | CPT caps/save/picker guards؛ المحرر الأصلي مؤجل |

Category Attributes كذلك محمي بـ`eldokan_backoffice_manage_categories` و`edit_term` عند الحفظ. لا صلاحيات جديدة تُمنح تلقائيًا. العملاء يتطلبون `list_users`؛ الموظف الذي لا يملكها لا يرى البطاقة أو بيانات الحسابات.

الأفعال الرئيسية المسجلة (34):
- `admin_post_eldokan_admin_save_commission` → `save_commission`
- `admin_post_eldokan_admin_save_category_commission` → `save_category_commission`
- `admin_post_eldokan_admin_clear_category_fee` → `clear_category_fee`
- `admin_post_eldokan_admin_save_product_commission` → `save_product_commission`
- `admin_post_eldokan_admin_delete_commission` → `delete_commission`
- `admin_post_eldokan_admin_rate_seller` → `rate_seller`
- `admin_post_eldokan_admin_seller_status` → `seller_status`
- `admin_post_eldokan_admin_save_dashboard_control` → `save_dashboard_control`
- `admin_post_eldokan_admin_save_access_role` → `save_access_role`
- `admin_post_eldokan_admin_delete_access_role` → `delete_access_role`
- `admin_post_eldokan_admin_assign_access_role` → `assign_access_role`
- `admin_post_eldokan_admin_update_seller_account_role` → `update_seller_account_role`
- `admin_post_eldokan_admin_delete_seller_account` → `delete_seller_account`
- `admin_post_eldokan_admin_update_wcfm_role` → `update_wcfm_role`
- `admin_post_eldokan_admin_bulk_wcfm_role` → `bulk_wcfm_role`
- `admin_post_eldokan_admin_save_email_settings` → `save_email_settings`
- `admin_post_eldokan_admin_send_test_email` → `send_test_email`
- `admin_post_eldokan_admin_registration_status` → `registration_status`
- `admin_post_eldokan_admin_delete_registration` → `delete_registration`
- `admin_post_eldokan_admin_view_registration_pdf` → `view_registration_pdf`
- `admin_post_eldokan_admin_update_order_status` → `update_order_status`
- `wp_ajax_eldokan_wcfm_index_scan` → `wcfm_index_scan`
- `wp_ajax_eldokan_wcfm_index_batch` → `wcfm_index_batch`
- `wp_ajax_eldokan_wcfm_index_reset` → `wcfm_index_reset`
- `wp_ajax_eldokan_wcfm_index_rebuild` → `wcfm_index_rebuild`
- `wp_ajax_eldokan_wcfm_migration_scan` → `wcfm_migration_scan`
- `wp_ajax_eldokan_wcfm_migration_batch` → `wcfm_migration_batch`
- `wp_ajax_eldokan_wcfm_migration_reset` → `wcfm_migration_reset`
- `wp_ajax_eldokan_wcfm_migration_verify` → `wcfm_migration_verify`
- `wp_ajax_eldokan_wcfm_migration_verify_detailed` → `wcfm_migration_verify_detailed`
- `wp_ajax_eldokan_wcfm_migration_repair` → `wcfm_migration_repair`
- `admin_post_eldokan_admin_export_activity` → `export_activity_csv`
- `admin_post_eldokan_admin_export_seller_report` → `export_seller_report_excel`
- `admin_post_eldokan_admin_export_seller_report_pdf` → `export_seller_report_pdf`
