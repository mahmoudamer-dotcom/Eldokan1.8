# Policy drafts and launch handoff

## Owner-supplied details
- Store name: الدكان / Eldokan.
- Address: 32 شارع صعب صالح، عين شمس، مصر.
- Email and telephone retain existing repository values; confirm ownership before launch.

## Implemented
- One footer in the root layout, with customer, merchant and policy navigation on every route.
- Eight bilingual draft policies at `/policies`: use, buyers, sellers, privacy, cookies, shipping, returns and prohibited goods.
- Draft notices and noindex metadata; no effective date or claim of legal approval.
- Policy references at registration, checkout and the product returns tab.
- Removed contradictory 15/30-day promises, unsupported free returns, refund timings and arbitrary refund percentages from the product returns tab.

## Commercial proposals, not system settings
- Proposed merchant commission: 10% of net goods after discounts and returned lines, excluding separately itemized tax and shipping.
- Proposed weekly settlement after collection, delivery and the applicable returns window, with itemized deductions.
- These values are review proposals, not operative seller agreements. They do not configure WooCommerce, bank transfers or payouts.

## Before final publication
- Confirm legal entity, registration/tax details, contacts and authorized return destination. Have the final policies reviewed for the actual business and current Egyptian requirements.
- Approve merchant rates, settlement eligibility, schedules, seller verification, fulfillment obligations and dispute handling; implement them in the backend and seller agreements.
- Review actual backend personal data, processors, cookies, analytics, retention and customer rights procedures. The frontend inventory alone cannot establish backend compliance.
- Add backend versioned terms acceptance when required: policy version, authenticated user, time and evidence, surfaced consistently in frontend flows. Current links do not claim acceptance is recorded.
- Finalize return/refund operations, delivery coverage and provider commitments. Do not promise automatic refunds or fixed delivery dates until supported.
- Existing shipping quote failures, Paymob payment verification and Bosta integration still need backend work described in API-BACKEND-REPORT.md and LIVE-READINESS.md.
- Replace draft notices and noindex only after final approval and operational alignment. No live deployment or successful payment was performed by this change.

Consumer-rights source is linked on the returns policy and product tab: Egyptian Consumer Protection Agency, general 14-day rights with exceptions and 30-day defective goods guidance. Draft store terms do not override statutory rights.
