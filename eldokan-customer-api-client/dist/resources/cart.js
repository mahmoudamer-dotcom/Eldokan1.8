import { EldokanClientError } from '../errors.js';
const PRODUCT_ID = /^prd_[1-9][0-9]*$/;
const VARIATION_ID = /^var_[1-9][0-9]*$/;
const CART_ITEM_ID = /^cit_[a-f0-9]{32}$/;
function quantityOrThrow(quantity) {
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > 999) {
        throw new EldokanClientError({
            message: 'Quantity must be an integer between 1 and 999.',
            kind: 'validation',
            code: 'invalid_quantity',
        });
    }
    return quantity;
}
function publicIdOrThrow(value, pattern, code) {
    if (!pattern.test(value)) {
        throw new EldokanClientError({ message: 'Invalid public ID.', kind: 'validation', code });
    }
    return value;
}
export class CartResource {
    http;
    sessionState;
    cartRequest = null;
    constructor(http, sessionState) {
        this.http = http;
        this.sessionState = sessionState;
    }
    get(options = {}) {
        if (this.cartRequest)
            return this.cartRequest;
        const request = this.http.get('/cart', {
            lang: options.lang,
            credentials: 'include',
        }).then((response) => {
            this.sessionState.setCsrfToken(response.data.csrf_token);
            return response;
        });
        this.cartRequest = request;
        void request.finally(() => {
            if (this.cartRequest === request)
                this.cartRequest = null;
        }).catch(() => { });
        return request;
    }
    async add(input, options = {}) {
        const productId = publicIdOrThrow(input.productId, PRODUCT_ID, 'invalid_product_id');
        const variationId = input.variationId
            ? publicIdOrThrow(input.variationId, VARIATION_ID, 'invalid_variation_id')
            : null;
        const quantity = quantityOrThrow(input.quantity);
        const csrfToken = await this.ensureCsrf(options);
        return this.http.post('/cart/items', {
            body: {
                product_id: productId,
                ...(variationId ? { variation_id: variationId } : {}),
                quantity,
            },
            lang: options.lang,
            credentials: 'include',
            csrfToken,
        });
    }
    async update(itemId, input, options = {}) {
        const id = publicIdOrThrow(itemId, CART_ITEM_ID, 'invalid_cart_item_id');
        const quantity = quantityOrThrow(input.quantity);
        const csrfToken = await this.ensureCsrf(options);
        return this.http.patch(`/cart/items/${encodeURIComponent(id)}`, {
            body: { quantity },
            lang: options.lang,
            credentials: 'include',
            csrfToken,
        });
    }
    async remove(itemId, options = {}) {
        const id = publicIdOrThrow(itemId, CART_ITEM_ID, 'invalid_cart_item_id');
        const csrfToken = await this.ensureCsrf(options);
        return this.http.delete(`/cart/items/${encodeURIComponent(id)}`, {
            lang: options.lang,
            credentials: 'include',
            csrfToken,
        });
    }
    async ensureCsrf(options) {
        if (!this.sessionState.getCsrfToken()) {
            await this.get(options);
        }
        return this.sessionState.requireCsrfToken();
    }
}
//# sourceMappingURL=cart.js.map