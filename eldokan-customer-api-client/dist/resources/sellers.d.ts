import type { EldokanHttpClient } from '../client.js';
import type { LanguageOptions, SellerId, SellerProfileResponse, ProductListParams, ProductListResponse } from '../types.js';
export declare class SellersResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    get(sellerId: SellerId, options?: LanguageOptions): Promise<SellerProfileResponse>;
    products(sellerId: SellerId, params?: Pick<ProductListParams, 'page' | 'perPage' | 'sort' | 'lang'>): Promise<ProductListResponse>;
}
//# sourceMappingURL=sellers.d.ts.map