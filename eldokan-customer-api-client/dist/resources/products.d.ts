import type { EldokanHttpClient } from '../client.js';
import type { LanguageOptions, ProductDetailResponse, ProductId, ProductListParams, ProductListResponse } from '../types.js';
export declare class ProductsResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    list(params?: ProductListParams): Promise<ProductListResponse>;
    get(productId: ProductId, options?: LanguageOptions): Promise<ProductDetailResponse>;
    lookupBySlug(slug: string, options?: LanguageOptions): Promise<ProductDetailResponse>;
}
//# sourceMappingURL=products.d.ts.map