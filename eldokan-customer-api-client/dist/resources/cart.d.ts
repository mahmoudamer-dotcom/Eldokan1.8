import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { CartAddInput, CartItemId, CartMutationResponse, CartQuantityInput, CartResponse, LanguageOptions } from '../types.js';
export declare class CartResource {
    private readonly http;
    private readonly sessionState;
    private cartRequest;
    constructor(http: EldokanHttpClient, sessionState: CustomerSessionState);
    get(options?: LanguageOptions): Promise<CartResponse>;
    add(input: CartAddInput, options?: LanguageOptions): Promise<CartMutationResponse>;
    update(itemId: CartItemId, input: CartQuantityInput, options?: LanguageOptions): Promise<CartMutationResponse>;
    remove(itemId: CartItemId, options?: LanguageOptions): Promise<CartMutationResponse>;
    private ensureCsrf;
}
//# sourceMappingURL=cart.d.ts.map