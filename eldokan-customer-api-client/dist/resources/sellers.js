import { EldokanClientError } from '../errors.js';
const SELLER_ID = /^sel_[1-9][0-9]*$/;
function idOrThrow(sellerId) {
    if (!SELLER_ID.test(sellerId)) {
        throw new EldokanClientError({
            message: 'Invalid seller ID.',
            kind: 'validation',
            code: 'invalid_seller_id',
        });
    }
    return sellerId;
}
export class SellersResource {
    http;
    constructor(http) {
        this.http = http;
    }
    get(sellerId, options = {}) {
        return this.http.get(`/sellers/${encodeURIComponent(idOrThrow(sellerId))}`, { lang: options.lang });
    }
    products(sellerId, params = {}) {
        return this.http.get(`/sellers/${encodeURIComponent(idOrThrow(sellerId))}/products`, { query: { page: params.page, per_page: params.perPage, sort: params.sort }, lang: params.lang });
    }
}
//# sourceMappingURL=sellers.js.map