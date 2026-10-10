# Storefront experience update

## Implemented

- Shared Arabic and English components throughout discovery, comparison,
  product selection, cart, checkout, account and order follow-up.
- Locally hosted Cairo variable font for Arabic; existing Inter font for English.
  The Cairo license is included in `public/fonts/Cairo-OFL.txt`.
- Arabic labels and form validation in authentication, account and purchase
  actions; localized cart, wishlist and address error messages.
- Locale-aware prices on product cards, product details, search suggestions,
  chat, comparison, cart, checkout and invoices. Amounts remain API amounts.
- Discovery workspace links the existing assistant, comparison, favorites and
  cart, with counts from the existing providers.
- Category navigation restored on the home page. Home sections are assigned
  once, preventing the same deals section from rendering twice.
- Home catalog shelves and product recommendations stream separately from the
  main page. Category reads are shared within a server render. Cart hydration
  only fetches product images when the API has not already provided an image.
- Purchase progress, desktop checkout summary, mobile total near confirmation,
  account shortcuts and readable order cards.
- Order progress and separate seller fulfillment states use API statuses.
  No delivery date, courier tracking number or bank approval is invented.
- Recent product snapshots refresh in the active language with current prices
  and stock, while retaining cached snapshots if the product read fails.
- Logical spacing and positioning for Arabic, keyboard focus, skip navigation,
  and reduced-motion support.
- Announcement ticker geometry remains left-to-right in both document
  directions, while each Arabic announcement retains right-to-left text. Two
  equal copies create the continuous right-to-left loop.

## API content requirement

The store still needs Arabic catalog content returned for `lang=ar`: product
names, descriptions, seller-provided specifications, banners, and delivery or
warranty text. Brand names and identifiers intentionally remain proper names.
The UI cannot translate arbitrary merchant copy reliably using a label
dictionary. English fallback content remains available rather than hiding a
product when its Arabic version is missing.

## Validation and limits

TypeScript compilation and targeted ESLint checks passed. No browser session,
real payment, cancellation or end-to-end checkout was executed for this update.
The changes reduce blocking requests, but a checkout completion time in seconds
has not been measured; API latency and hosted Paymob steps still affect timing.

Existing stock checks, authoritative totals, order recovery, payment routing,
installment selection and cancellation eligibility remain in place. This update
does not require another WordPress plugin package.
