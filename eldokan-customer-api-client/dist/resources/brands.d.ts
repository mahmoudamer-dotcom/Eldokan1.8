import type { EldokanHttpClient } from '../client.js';
import type { BrandListResponse, CatalogTermListParams } from '../types.js';
export declare class BrandsResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    list(params?: CatalogTermListParams): Promise<BrandListResponse>;
}
//# sourceMappingURL=brands.d.ts.map