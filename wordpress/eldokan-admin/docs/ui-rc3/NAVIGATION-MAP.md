# ElDokan Admin Staff — RC3 Navigation Map

**Release candidate only.** This is a menu/presentation restructuring, **not** a migration of business logic or vendor data.

## Only six visible WordPress admin submenu entries

| Main menu | Direct URL | Capability | Nested contextual destinations |
|---|---|---|---|
| Dashboard | `admin.php?page=eldokan-admin` | `eldokan_backoffice_view_dashboard` | Overview |
| Sellers | `admin.php?page=eldokan-admin-sellers` | `eldokan_backoffice_view_sellers` | Registrations (`eldokan_backoffice_view_registrations`) |
| Orders | `admin.php?page=eldokan-admin-orders` | `eldokan_backoffice_view_orders` | Original order filters and actions remain in page |
| Products | `admin.php?page=eldokan-admin-products` | `eldokan_backoffice_view_products` | Categories/Attributes, Home/Banners, native Stories editor |
| Reports | `admin.php?page=eldokan-admin-reports` | `eldokan_backoffice_view_reports` | Commission Rules, Seller Statements, Performance |
| Settings | `admin.php?page=eldokan-admin-settings` | `eldokan_backoffice_manage_settings` | Checkout, Email, Team Permissions, Seller Role Templates, Seller Dashboard Control, Activity, WCFM Data Index |

**Role-sensitive:** No unauthorized link is emitted. Only capabilities already present in RC2 are used. Sections with one usable link do not show an extra bar. Some users will not see Settings if they lack its capability, while they can still access specifically authorized hidden pages by direct links.

**No broken links:** All registered page slugs, callbacks and existing action/form targets remain registered; their menu entries are removed from the sidebar only. Native Stories post type keeps `show_ui=true` but is no longer an automatically inserted first submenu; its list/edit URL remains `edit.php?post_type=eldokan_story`.

**No WCFM shutdown:** WCFM historical index and migration remain accessible to admin under Settings; the only change there is the unsafe "Ready to Remove WCFM" presentation string, now accurately limited to mapping readiness.

## Notices

WordPress core/plugin notices are not hidden or deregistered. Context navigation prints **after** `admin_notices` callbacks. JavaScript only relocates notices inserted **inside ElDokan's page hero**, preserving their original elements/dismissal. Theme/plugin-generated notices rendered elsewhere are untouched. Confirm actual placement with WPML/WCFM on staging.
