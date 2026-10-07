import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { LanguageOptions, OrderDetailResponse, OrderListResponse, PaymentInput, PaymentResponse } from '../types.js';
export type OrderId = `ord_${string}`;
export type GuestOrderAccess = {
    credential: string;
    csrfToken: string;
};
export type OrderAccessOptions = LanguageOptions & {
    guestAccess?: GuestOrderAccess;
};
export type OrderListOptions = LanguageOptions & {
    page?: number;
    perPage?: number;
};
export declare class OrdersResource {
    private readonly http;
    private readonly sessionState;
    constructor(http: EldokanHttpClient, sessionState: CustomerSessionState);
    list(options?: OrderListOptions): Promise<OrderListResponse>;
    get(orderId: OrderId, options?: OrderAccessOptions): Promise<OrderDetailResponse>;
    payment(orderId: OrderId, input?: PaymentInput, options?: OrderAccessOptions): Promise<PaymentResponse>;
}
//# sourceMappingURL=orders.d.ts.map