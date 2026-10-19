# 1.10.0-rc.7 — Product seller per-product assignment

- Products → ElDokan sellers now links each product without a seller to the seller account that created it, in one explicit staff action. Products created by staff are left unchanged.
- Choosing one seller now applies only to the products selected in the table, so products of different sellers are never assigned together.

# 1.10.0-rc.6 — Product seller assignment

- New embedded module `modules/product-seller`: product edit box, product list column and Products → ElDokan sellers page to set `_eldokan_seller_user_id`, so staff-created products can be bought through the Customer API instead of failing with `seller_missing`.
- Optional dedicated store seller account (`eldokan_owner`, random password) for products the store sells itself; staff accounts are not given seller roles.
- Pauses itself when the standalone ElDokan Product Seller plugin is active. No changes to existing modules, roles, commissions or the Customer API.

# 1.10.0-rc.5 — Functional stabilization candidate (UNAPPROVED)

- Preserve canonical item ownership; read known legacy owner snapshots for order display/filtering only. Order #22862 remains pending raw read-only evidence.
- Add a paginated customer/subscriber account destination protected by list_users; no new capability grants.
- Share Dashboard/product-list status and language query policy; preserve WPML filters and treat translations as separate product records.
- Preserve product commission search/status/category/page/language and return to the product row; expose enabled/disabled rules.
- Validate zero/percentage/fixed/hybrid rules; insert replacements and retire prior revisions atomically with checked failure/rollback paths. No schema changes; refuse unsafe nontransactional storage.
- Keep category term fees and legacy mirror changes in one transaction; prevent misleading deletion of an authoritative category fee through its mirror.
- Add request-local rate/category/count caches, paginated rule history, RTL/focus/save-state refinements, and synthetic regression/bootstrap/browser tests.
- No Live operations, API/WCFM edits, deployment or production acceptance. See docs/ui-rc5.

# 1.10.0-rc.4 — Consolidated Admin UI candidate (UNAPPROVED)

- Continue RC3 with RC2 design, six permission-aware sidebar groups and guarded aliases preserving hidden page callbacks/direct URLs.
- Native WordPress notice anchor outside hero, contextual breadcrumb, Products content grouping, optional seller section disclosures.
- Server product pagination and filter-preserving URLs; pending work and authorized latest audit events.
- Label bounded summary data accurately; existing financial calculations and REST routes remain unchanged.
- Localized/escaped WCFM readiness messages; WCFM remains active and retirement is not approved.
- Add read-only WCFM/Seller/Customer dependency and Client contract evidence. No API/Client/Frontend modifications.
- See docs/ui-rc4 for checks, remaining defects, financial limits and mandatory staging acceptance. No production deployment.

# 1.10.0-rc.3 — Navigation & operational UX (unapproved)

- Six visible ElDokan WordPress submenu destinations: Dashboard, Sellers, Orders, Products, Reports, Settings.
- All former admin pages remain registered and reachable through capability-gated, in-context links; Stories and Home Management are under Products.
- Move injected system notices out of the ElDokan page hero without suppressing them; page template still requires browser acceptance.
- Remove dead Customers dashboard link (keep count read-only until an authorized customer management view exists).
- Replace dangerous "Ready to Remove WCFM" UI copy with accurate mapping-only status; no WCFM migration or deactivation was executed.
- No API, DB schema, order/payment logic, WooCommerce status transitions, or access rules changed.
- Static/menu mocks passed; WordPress and SiteGround acceptance **not run**; staging only.

# 1.10.0-rc.2 — Admin Staff UI modernization (unapproved)

- Scoped workspace navigation; consistent compact page headers, cards, tables, forms and permission panels.
- Arabic gettext catalogue and logical RTL/LTR styles; integrated Checkout and native Stories presentation.
- Keyboard table regions, loaded-row search, optional compact rows and non-destructive dialog cancellation.
- Existing operational methods, inline scripts, capabilities, routes, storage keys and embedded-module behavior preserved.
- No live deployment. Staging sign-off required.

# Admin Staff 1.10.0-rc.1

Unapproved consolidation candidate. Based on Admin 1.9.1; embeds Checkout Setup 0.1.5 and Stories 1.1.1 with original persistent identifiers and public Stories response. Explicit staff capabilities, legacy provider deferral and guarded PDF fallback added. No customer order/payment engine moved. Plugin folder and bootstrap remain eldokan-admin/eldokan-admin.php. No uninstall/deactivation or settings reset is run.
