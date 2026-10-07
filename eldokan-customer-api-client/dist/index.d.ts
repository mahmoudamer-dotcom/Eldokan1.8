import type { EldokanClientConfig } from './config.js';
import { CategoriesResource } from './resources/categories.js';
import { HealthResource } from './resources/health.js';
import { HomeResource } from './resources/home.js';
import { ProductsResource } from './resources/products.js';
import { SearchResource } from './resources/search.js';
import { BrandsResource } from './resources/brands.js';
import { TagsResource } from './resources/tags.js';
import { SellersResource } from './resources/sellers.js';
import { AuthResource } from './resources/auth.js';
import { AccountResource } from './resources/account.js';
import { WishlistResource } from './resources/wishlist.js';
import { CartResource } from './resources/cart.js';
import { AddressesResource } from './resources/addresses.js';
import { CheckoutResource } from './resources/checkout.js';
import { OrdersResource } from './resources/orders.js';
export type { AddressId } from './resources/addresses.js';
export type { GuestOrderAccess, OrderAccessOptions, OrderId, OrderListOptions } from './resources/orders.js';
export * from './config.js';
export * from './errors.js';
export * from './filters.js';
export * from './types.js';
/**
 * Official client surface for ElDokan Customer API Contract v1.
 * Keep frontend components behind this class; do not call backend-specific endpoints directly.
 */
export declare class EldokanCustomerApiClient {
    readonly health: HealthResource;
    readonly home: HomeResource;
    readonly products: ProductsResource;
    readonly categories: CategoriesResource;
    readonly search: SearchResource;
    readonly brands: BrandsResource;
    readonly tags: TagsResource;
    readonly sellers: SellersResource;
    readonly auth: AuthResource;
    readonly account: AccountResource;
    readonly wishlist: WishlistResource;
    readonly cart: CartResource;
    readonly addresses: AddressesResource;
    readonly checkout: CheckoutResource;
    readonly orders: OrdersResource;
    constructor(config: EldokanClientConfig);
}
export declare function createEldokanCustomerApiClient(config: EldokanClientConfig): EldokanCustomerApiClient;
//# sourceMappingURL=index.d.ts.map