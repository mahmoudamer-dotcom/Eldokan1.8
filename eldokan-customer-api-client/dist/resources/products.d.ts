import type { EldokanHttpClient } from '../client.js';
import type { LanguageOptions, ProductDetailResponse, ProductId, ProductListParams, ProductListResponse } from '../types.js';
export declare class ProductsResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    bestSelling(category: string, options?: Pick<ProductListParams, 'perPage' | 'lang'>): Promise<ProductListResponse>;
    list(params?: ProductListParams): Promise<ProductListResponse>;
    get(productId: ProductId, options?: LanguageOptions): Promise<ProductDetailResponse>;
    lookupBySlug(slug: string, options?: LanguageOptions): Promise<ProductDetailResponse>;
}
//# sourceMappingURL=products.d.ts.map