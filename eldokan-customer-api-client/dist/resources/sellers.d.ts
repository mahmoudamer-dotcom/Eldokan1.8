import type { EldokanHttpClient } from '../client.js';
import type { LanguageOptions, SellerId, SellerPublicResponse } from '../types.js';
export declare class SellersResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    get(sellerId: SellerId, options?: LanguageOptions): Promise<SellerPublicResponse>;
}
//# sourceMappingURL=sellers.d.ts.map