import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { AccountUpdateRequest, CustomerAccountResponse } from '../types.js';
export declare class AccountResource {
    private readonly http;
    private readonly sessionState;
    constructor(http: EldokanHttpClient, sessionState: CustomerSessionState);
    me(): Promise<CustomerAccountResponse>;
    update(input: AccountUpdateRequest): Promise<CustomerAccountResponse>;
}
//# sourceMappingURL=account.d.ts.map