# WCFM Retirement — Evidence and Required Gates (READ-ONLY ASSESSMENT)

**State: NOT APPROVED TO DEACTIVATE.** Source code proves **some dependency reduction** but cannot validate production data, operational shipping, payouts or installed WCFM modules.

## What is source-confirmed

| Surface | Dependency or autonomous behavior | Risk |
|---|---|---|
| Admin Staff | Legacy `wcfm_vendor`/`vendor` roles; `wcfmmp_profile_settings`; vendor metadata, WCFM order tables; WCFM historical indexes and role assignment UI | Historical mapping, seller discovery and operational backoffice role management still interact with WCFM data |
| Seller Dashboard API v1.75.70-rc.1 | Explicit fallback fields `vendor_id`, `_vendor_id`, `_wcfm_vendor_id` in attribution; financial and order functions work through WooCommerce/ElDokan rules | Removing legacy role metadata or changing owner attribution could silently misattribute orders |
| Customer API 0.10.0 | Recognizes `wcfm_vendor` among seller roles in order/customer flows and `wcfmmp_store_name` as legacy fallback | Seller discovery and order readiness need verified migration |
| Admin | `_eldokan_seller_user_id` and ElDokan commission rules; WooCommerce CRUD for orders | Significant independence exists but *not* proof of settlement/payout engine |
| WCFM migration page | `ready` indicates current mapping counts, **not** shipping, payouts, hooks or refunds | UI was misleading; RC3 revises message, does not weaken checks |

No full WCFM plugin files, active plugin list, live DB dump, seller invoices, payouts ledger or current WPVibe connection were available. Seller Portal uses Seller API, but a full live seller acceptance is not performed.

## Required no-write discovery gates

1. Inventory **exact active components** WCFM Marketplace, WCFM Frontend Manager, Ultimate and extensions with versions, licensing and which scheduled jobs are registered. Ultimate licensing notice alone does not prove core WCFM is inactive.
2. Count source sellers and ElDokan owners; compare seller/child-account roles, statuses, ownership of products/variations and **all** WPML languages.
3. Reconcile order-line ownership, refunds, canceled/failed statuses, legacy seller shipping, coupon/discount/tax treatment, all authoritative HPOS orders and historical records. Check current WCFM order/commission data against ElDokan snapshots.
4. Reconcile individual seller owed balances, withdrawals, paid/held settlements, credits/debits and payout providers. A report of estimated net revenue is not a payout ledger.
5. Map shipping methods and vendor zones, tax invoices, WCFM store policies, approval queues, email/webhooks, cron/action scheduler tasks and reports.
6. Perform *staging-only* controlled deactivation one WCFM module at a time and run complete Seller API / Seller Portal / Admin / Customer checkout acceptance, including cross-seller isolation and Paymob callbacks.
7. Document named owner, zero-difference criteria, rollback and production observation. Only then approve deactivation. **Never delete WCFM tables during cutover.**

## Important financial-data finding

`Admin::overall_metrics('30d')` reads at most the **latest 300 WooCommerce orders across all dates**, then filters by period and by linked seller; it adds line totals/estimated commissions irrespective of paid status. It is not a guaranteed complete, settled 30-day finance source. Seller API financial reports use paid/refund-aware logic, a different denominator. The difference can explain discrepancies/zeros and must be reconciled before finance sign-off. Do **not** "fix" finance calculations without confirming agreed business definitions and test fixtures.

## Proposed sequence

- **Phase W0 / Now:** non-mutating plugin/data/service inventory and test-fixture ledger; retain WCFM active.
- **Phase W1:** stable ElDokan seller ownership/permissions mapping and read-only WCFM compatibility adapter with parity checks.
- **Phase W2:** shipping, order routing, refunds, commissions, balances and withdrawals with authoritative persistence and idempotence.
- **Phase W3:** Staging disablement rehearsals, reconciliation, role/security QA and zero-difference settlement acceptance.
- **Phase W4:** limited production disablement with rollback; archive historical data unchanged. Removal only if zero dependencies are proven.
