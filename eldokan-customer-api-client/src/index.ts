import { EldokanHttpClient } from './client.js';
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
import { CustomerSessionState } from './session.js';

export * from './config.js';
export * from './errors.js';
export * from './filters.js';
export * from './types.js';

/**
 * Official client surface for ElDokan Customer API Contract v1.
 * Keep frontend components behind this class; do not call backend-specific endpoints directly.
 */
export class EldokanCustomerApiClient {
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

  constructor(config: EldokanClientConfig) {
    const http = new EldokanHttpClient(config);
    const customerSession = new CustomerSessionState();
    this.health = new HealthResource(http);
    this.home = new HomeResource(http);
    this.products = new ProductsResource(http);
    this.categories = new CategoriesResource(http);
    this.search = new SearchResource(http);
    this.brands = new BrandsResource(http);
    this.tags = new TagsResource(http);
    this.sellers = new SellersResource(http);
    this.auth = new AuthResource(http, customerSession);
    this.account = new AccountResource(http, customerSession);
    this.wishlist = new WishlistResource(http, customerSession);
    this.cart = new CartResource(http, customerSession);
  }
}

export function createEldokanCustomerApiClient(
  config: EldokanClientConfig,
): EldokanCustomerApiClient {
  return new EldokanCustomerApiClient(config);
}
