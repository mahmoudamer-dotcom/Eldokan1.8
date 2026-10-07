import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { CheckoutResponse, LanguageOptions, PlacementInput, PlacementResponse, PurchaseInput, CheckoutAttemptResponse, QuoteInput } from '../types.js';
export declare class CheckoutResource {
    private readonly http;
    private readonly sessionState;
    constructor(http: EldokanHttpClient, sessionState: CustomerSessionState);
    get(options?: LanguageOptions): Promise<CheckoutResponse>;
    quote(input: QuoteInput, options?: LanguageOptions): Promise<CheckoutResponse>;
    createAttempt(input: PurchaseInput, options?: LanguageOptions): Promise<CheckoutAttemptResponse>;
    placeOrder(input: PlacementInput, options?: LanguageOptions): Promise<PlacementResponse>;
    private ensureCsrf;
}
//# sourceMappingURL=checkout.d.ts.map