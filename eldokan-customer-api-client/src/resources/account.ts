import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { AccountUpdateRequest, CustomerAccountResponse } from '../types.js';

export class AccountResource {
  constructor(
    private readonly http: EldokanHttpClient,
    private readonly sessionState: CustomerSessionState,
  ) {}

  me(): Promise<CustomerAccountResponse> {
    return this.http.get<CustomerAccountResponse>('/me', {
      credentials: 'include',
      includeLanguage: false,
    });
  }

  update(input: AccountUpdateRequest): Promise<CustomerAccountResponse> {
    return this.http.patch<CustomerAccountResponse>('/me', {
      body: input,
      credentials: 'include',
      csrfToken: this.sessionState.requireCsrfToken(),
      includeLanguage: false,
    });
  }
}
