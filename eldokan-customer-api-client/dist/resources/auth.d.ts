import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { AuthSessionResponse, LoginRequest, LogoutResponse, RegisterRequest } from '../types.js';
export declare class AuthResource {
    private readonly http;
    private readonly sessionState;
    constructor(http: EldokanHttpClient, sessionState: CustomerSessionState);
    register(input: RegisterRequest): Promise<AuthSessionResponse>;
    login(input: LoginRequest): Promise<AuthSessionResponse>;
    session(): Promise<AuthSessionResponse>;
    logout(): Promise<LogoutResponse>;
}
//# sourceMappingURL=auth.d.ts.map