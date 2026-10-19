ElDokan Admin Staff 1.10.0-rc.5 — functional stabilization candidate
UNAPPROVED RELEASE CANDIDATE. WordPress acceptance and database rollback tests required.

Includes order-list ownership snapshot fallback, protected customer list, consistent product-language counts, filter-preserving fee forms, transactional commission saves and failure checks. Existing REST contracts, payment integrations, historical order records and WCFM remain unchanged. Fee writes require InnoDB tables; no automatic engine conversion or migration is performed. See docs/ui-rc5 for checks and limitations.

ElDokan Admin
=============

Version: 1.10.0-rc.5


Release 1.9.1 is a focused Home Management hardening patch. Search CTA values
are now sanitized as human search text instead of category/title slugs. The
public Home contract and all existing permissions remain unchanged.

Home Management 1.9.0 adds structured bilingual Hero Slide and Promo Banner
records plus independent dynamic-section settings. Dedicated capabilities,
nonces, sanitization, audit logging and Home-only cache invalidation protect
the workflow without adding a page builder or changing other backoffice flows.

ElDokan Admin is the internal WordPress administration plugin used to manage
the ElDokan marketplace.

It is designed for ElDokan platform employees and is separate from the
ElDokan Seller Dashboard used by sellers and their team members.


MAIN RESPONSIBILITIES
=====================

ElDokan Admin currently provides management interfaces for:

- Dashboard overview
- Sellers
- Seller registrations
- Products
- Orders
- Product categories and category attributes
- Seller reports
- Seller commissions
- Activity logs
- Email settings
- ElDokan settings
- Internal employee roles and permissions


BACKOFFICE ROLES
================

Version 1.7.0 introduced dedicated ElDokan employee roles:

- ElDokan Manager
- ElDokan Developer
- ElDokan Staff
- ElDokan Viewer

These roles are separate from:

- WordPress Administrator
- WooCommerce Shop Manager
- ElDokan Seller Dashboard roles

The plugin does not modify the default WordPress or WooCommerce roles.

Backoffice permissions can be managed from:

ElDokan Admin > Team Permissions


SELLER ROLES
============

Seller Dashboard roles are managed separately by the ElDokan Dashboard API.

Current seller roles include:

- ElDokan Seller Owner
- ElDokan Seller Admin
- ElDokan Seller Operator
- ElDokan Seller Finance
- ElDokan Seller Viewer
- ElDokan Seller Pending

Backoffice employee roles and seller roles must remain separate.


SECURITY MODEL
==============

ElDokan Admin uses custom WordPress capabilities for internal employees.

Examples include:

- eldokan_backoffice_view_dashboard
- eldokan_backoffice_view_sellers
- eldokan_backoffice_manage_sellers
- eldokan_backoffice_view_products
- eldokan_backoffice_manage_products
- eldokan_backoffice_view_orders
- eldokan_backoffice_manage_orders
- eldokan_backoffice_manage_categories
- eldokan_backoffice_view_reports
- eldokan_backoffice_export_reports
- eldokan_backoffice_manage_commissions
- eldokan_backoffice_view_activity
- eldokan_backoffice_manage_email
- eldokan_backoffice_manage_settings

Sensitive legacy operations remain restricted to WordPress Administrator.

Product deletion is intentionally not granted to ElDokan custom employee roles
for ElDokan custom employee roles.


MODERN ADMIN UI
===============

Version 1.8.0 introduces a modern visual system for ElDokan Admin while
preserving the existing marketplace data model, handlers, nonces and server-side
permission checks.

The redesign includes:

- Modern ElDokan page headers and dashboard cards
- Responsive tables, filters, forms, badges and action controls
- Permission-aware dashboard links
- Read-only action presentation for Viewer users on Orders and Registrations
- A redesigned Team Permissions screen
- Updated dashboard charts and responsive layouts

The redesign is intentionally presentation-focused. It does not replace the
existing order, seller, commission, report, migration or API business logic.


PRODUCT MANAGEMENT
==================

Product permissions are currently separated into:

- View Products
- Manage Products (Create / Edit)

Product deletion remains restricted.

Users who can manage products receive only the minimum native WordPress /
WooCommerce capabilities required for product editing.

The plugin does not grant manage_options or manage_woocommerce to ElDokan
employee roles.


CATEGORY MANAGEMENT
===================

Employees with product management permission can assign existing product
categories.

Employees with Manage Categories permission can create and edit product
categories and category attributes.

Category deletion is not granted to ElDokan custom roles by default.


INTEGRATION
===========

ElDokan Admin works with:

- WordPress
- WooCommerce
- ElDokan Dashboard API
- ElDokan Seller Dashboard

The Seller Dashboard frontend communicates with the ElDokan Dashboard API.

ElDokan Admin and the Seller Dashboard API operate on the same WordPress /
WooCommerce data while maintaining separate access-control responsibilities.


LEGACY WCFM MIGRATION
=====================

The current codebase still contains legacy WCFM migration and indexing tools.

These tools are temporary and remain Administrator-only.

The long-term architecture does not depend on WCFM.

The migration process is intended only to preserve required historical data,
including:

- Sellers and seller ownership
- Product ownership
- Historical seller/order-item relationships

After migration and verification are completed, WCFM dependencies and legacy
migration code are intended to be removed from the production codebase.


FILES
=====

Main plugin file:

eldokan-admin.php

Core admin controller:

includes/class-eldokan-admin.php

Backoffice employee roles and permissions:

includes/class-eldokan-backoffice-roles.php

Category attribute management:

includes/class-eldokan-category-attributes.php

Seller report PDF support:

includes/class-eldokan-report-pdf.php

Admin visual system:

assets/admin.css


RELEASE DOCUMENTATION
=====================

Release-specific changes are documented under:

docs/releases/

For version 1.8.0 see:

docs/releases/V1.8.0-MODERN-ADMIN-UI.md

Previous release:

docs/releases/V1.7.0-BACKOFFICE-ROLES.md


IMPORTANT
=========

Do not directly grant manage_options to ElDokan employee roles.

Do not merge Seller Dashboard permissions with Backoffice employee permissions.

Do not enable legacy WCFM migration tools for non-Administrator accounts.

Changes to roles and capabilities must be tested using separate test accounts
before being used on production.


COPYRIGHT
=========

ElDokan
Internal marketplace administration system.
