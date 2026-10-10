# Endpoint status — Customer API 0.8.0 / Contract v1

Current inventory: **32 paths / 40 operations**. All listed operations are implemented/code-complete and locally verified in the completed checkpoint/release work. Real staging acceptance remains pending; this is not Live acceptance. Auth classes are defined in API-CONTRACT.md.

| Method | Path | Auth / CSRF | Status |
| --- | --- | --- | --- |
| GET | `/health` | P | Implemented/code-complete; real staging pending |
| POST | `/auth/register` | A | Implemented/code-complete; real staging pending |
| POST | `/auth/login` | A | Implemented/code-complete; real staging pending |
| GET | `/auth/session` | L | Implemented/code-complete; real staging pending |
| POST | `/auth/logout` | L+ | Implemented/code-complete; real staging pending |
| GET | `/me` | L | Implemented/code-complete; real staging pending |
| POST | `/me` | L+ | Implemented/code-complete; real staging pending |
| PUT | `/me` | L+ | Implemented/code-complete; real staging pending |
| PATCH | `/me` | L+ | Implemented/code-complete; real staging pending |
| GET | `/me/addresses` | L | Implemented/code-complete; real staging pending |
| POST | `/me/addresses` | L+ | Implemented/code-complete; real staging pending |
| PATCH | `/me/addresses/{address_id}` | L+ | Implemented/code-complete; real staging pending |
| DELETE | `/me/addresses/{address_id}` | L+ | Implemented/code-complete; real staging pending |
| GET | `/wishlist` | L | Implemented/code-complete; real staging pending |
| POST | `/wishlist/items` | L+ | Implemented/code-complete; real staging pending |
| DELETE | `/wishlist/items/{product_id}` | L+ | Implemented/code-complete; real staging pending |
| GET | `/cart` | C | Implemented/code-complete; real staging pending |
| POST | `/cart/items` | C+ | Implemented/code-complete; real staging pending |
| POST | `/cart/items/{item_id}` | C+ | Implemented/code-complete; real staging pending |
| PUT | `/cart/items/{item_id}` | C+ | Implemented/code-complete; real staging pending |
| PATCH | `/cart/items/{item_id}` | C+ | Implemented/code-complete; real staging pending |
| DELETE | `/cart/items/{item_id}` | C+ | Implemented/code-complete; real staging pending |
| GET | `/checkout` | C | Implemented/code-complete; real staging pending |
| POST | `/checkout/quote` | C+ | Implemented/code-complete; real staging pending |
| POST | `/checkout/attempts` | C+ | Implemented/code-complete; real staging pending |
| POST | `/checkout/orders` | C+ | Implemented/code-complete; real staging pending |
| GET | `/orders` | L | Implemented/code-complete; real staging pending |
| GET | `/orders/{order_id}` | O | Implemented/code-complete; real staging pending |
| POST | `/orders/{order_id}/payment` | O+ | Implemented/code-complete; real staging pending |
| GET | `/home` | P | Implemented/code-complete; real staging pending |
| GET | `/categories` | P | Implemented/code-complete; real staging pending |
| GET | `/categories/{slug}` | P | Implemented/code-complete; real staging pending |
| GET | `/categories/{slug}/filters` | P | Implemented/code-complete; real staging pending |
| GET | `/brands` | P | Implemented/code-complete; real staging pending |
| GET | `/tags` | P | Implemented/code-complete; real staging pending |
| GET | `/sellers/{seller_id}` | P | Implemented/code-complete; real staging pending |
| GET | `/products` | P | Implemented/code-complete; real staging pending |
| GET | `/products/lookup` | P | Implemented/code-complete; real staging pending |
| GET | `/products/{product_id}` | P | Implemented/code-complete; real staging pending |
| GET | `/search/suggestions` | P | Implemented/code-complete; real staging pending |

COD, native hosted Paymob and SAME-Order payment recovery/retry are implemented under their documented eligibility/security rules. Seller Fulfillment mutation compatibility, Seller Account, Reviews, Seller Ratings, refund initiation and legacy Order migration are separate/deferred scope.
