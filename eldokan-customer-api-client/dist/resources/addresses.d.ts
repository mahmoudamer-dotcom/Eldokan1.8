import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { AddressBookResponse, AddressCreate, AddressDeletedResponse, AddressResponse, AddressUpdate } from '../types.js';
export type AddressId = `adr_${string}`;
export declare class AddressesResource {
    private readonly http;
    private readonly sessionState;
    constructor(http: EldokanHttpClient, sessionState: CustomerSessionState);
    list(): Promise<AddressBookResponse>;
    create(input: AddressCreate): Promise<AddressResponse>;
    update(id: AddressId, input: AddressUpdate): Promise<AddressResponse>;
    remove(id: AddressId): Promise<AddressDeletedResponse>;
}
//# sourceMappingURL=addresses.d.ts.map