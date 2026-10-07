import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type { CustomerSessionState } from '../session.js';
import type { AddressBookResponse, AddressCreate, AddressDeletedResponse, AddressResponse, AddressUpdate } from '../types.js';

export type AddressId = `adr_${string}`;
const ADDRESS_ID = /^adr_[a-f0-9]{64}$/;

function addressIdOrThrow(id: AddressId): AddressId {
  if (!ADDRESS_ID.test(id)) throw new EldokanClientError({ message: 'Invalid address ID.', kind: 'validation', code: 'invalid_address_id' });
  return id;
}

export class AddressesResource {
  constructor(private readonly http: EldokanHttpClient, private readonly sessionState: CustomerSessionState) {}

  list(): Promise<AddressBookResponse> {
    return this.http.get('/me/addresses', { credentials: 'include', includeLanguage: false });
  }

  create(input: AddressCreate): Promise<AddressResponse> {
    return this.http.post('/me/addresses', { body: input, credentials: 'include', csrfToken: this.sessionState.requireCsrfToken(), includeLanguage: false });
  }

  update(id: AddressId, input: AddressUpdate): Promise<AddressResponse> {
    return this.http.patch(`/me/addresses/${encodeURIComponent(addressIdOrThrow(id))}`, { body: input, credentials: 'include', csrfToken: this.sessionState.requireCsrfToken(), includeLanguage: false });
  }

  remove(id: AddressId): Promise<AddressDeletedResponse> {
    return this.http.delete(`/me/addresses/${encodeURIComponent(addressIdOrThrow(id))}`, { credentials: 'include', csrfToken: this.sessionState.requireCsrfToken(), includeLanguage: false });
  }
}
