import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { LanguageOptions, ProductId, WishlistMutationResponse, WishlistResponse } from '../types.js';
export declare class WishlistResource {
    private readonly http;
    private readonly sessionState;
    constructor(http: EldokanHttpClient, sessionState: CustomerSessionState);
    get(options?: LanguageOptions): Promise<WishlistResponse>;
    add(productId: ProductId, options?: LanguageOptions): Promise<WishlistMutationResponse>;
    remove(productId: ProductId, options?: LanguageOptions): Promise<WishlistMutationResponse>;
}
//# sourceMappingURL=wishlist.d.ts.map