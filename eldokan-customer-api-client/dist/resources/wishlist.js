import { EldokanClientError } from '../errors.js';
const PRODUCT_ID = /^prd_[1-9][0-9]*$/;
function productIdOrThrow(productId) {
    if (!PRODUCT_ID.test(productId)) {
        throw new EldokanClientError({
            message: 'Invalid product ID.',
            kind: 'validation',
            code: 'invalid_product_id',
        });
    }
    return productId;
}
export class WishlistResource {
    http;
    sessionState;
    constructor(http, sessionState) {
        this.http = http;
        this.sessionState = sessionState;
    }
    get(options = {}) {
        return this.http.get('/wishlist', {
            lang: options.lang,
            credentials: 'include',
        });
    }
    add(productId, options = {}) {
        return this.http.post('/wishlist/items', {
            body: { product_id: productIdOrThrow(productId) },
            lang: options.lang,
            credentials: 'include',
            csrfToken: this.sessionState.requireCsrfToken(),
        });
    }
    remove(productId, options = {}) {
        return this.http.delete(`/wishlist/items/${encodeURIComponent(productIdOrThrow(productId))}`, {
            lang: options.lang,
            credentials: 'include',
            csrfToken: this.sessionState.requireCsrfToken(),
        });
    }
}
//# sourceMappingURL=wishlist.js.map