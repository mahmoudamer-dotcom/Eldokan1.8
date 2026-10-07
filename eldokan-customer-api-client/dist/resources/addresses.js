import { EldokanClientError } from '../errors.js';
const ADDRESS_ID = /^adr_[a-f0-9]{64}$/;
function addressIdOrThrow(id) {
    if (!ADDRESS_ID.test(id))
        throw new EldokanClientError({ message: 'Invalid address ID.', kind: 'validation', code: 'invalid_address_id' });
    return id;
}
export class AddressesResource {
    http;
    sessionState;
    constructor(http, sessionState) {
        this.http = http;
        this.sessionState = sessionState;
    }
    list() {
        return this.http.get('/me/addresses', { credentials: 'include', includeLanguage: false });
    }
    create(input) {
        return this.http.post('/me/addresses', { body: input, credentials: 'include', csrfToken: this.sessionState.requireCsrfToken(), includeLanguage: false });
    }
    update(id, input) {
        return this.http.patch(`/me/addresses/${encodeURIComponent(addressIdOrThrow(id))}`, { body: input, credentials: 'include', csrfToken: this.sessionState.requireCsrfToken(), includeLanguage: false });
    }
    remove(id) {
        return this.http.delete(`/me/addresses/${encodeURIComponent(addressIdOrThrow(id))}`, { credentials: 'include', csrfToken: this.sessionState.requireCsrfToken(), includeLanguage: false });
    }
}
//# sourceMappingURL=addresses.js.map