import { EldokanClientError } from '../errors.js';
const ORDER_ID = /^ord_[a-f0-9]{64}$/;
function orderIdOrThrow(id) {
    if (!ORDER_ID.test(id))
        throw new EldokanClientError({ message: 'Invalid order ID.', kind: 'validation', code: 'invalid_order_id' });
    return id;
}
function guestHeaders(access) {
    if (!access)
        return undefined;
    if (!/^gok_[a-f0-9]{64}$/.test(access.credential)) {
        throw new EldokanClientError({ message: 'Invalid guest order access.', kind: 'validation', code: 'invalid_guest_access' });
    }
    return { 'X-ElDokan-Order-Access': access.credential };
}
export class OrdersResource {
    http;
    sessionState;
    constructor(http, sessionState) {
        this.http = http;
        this.sessionState = sessionState;
    }
    list(options = {}) {
        if (options.page !== undefined && (!Number.isInteger(options.page) || options.page < 1 || options.page > 10_000)) {
            throw new EldokanClientError({ message: 'Order page must be an integer between 1 and 10000.', kind: 'validation', code: 'invalid_page' });
        }
        if (options.perPage !== undefined && (!Number.isInteger(options.perPage) || options.perPage < 1 || options.perPage > 50)) {
            throw new EldokanClientError({ message: 'Order page size must be an integer between 1 and 50.', kind: 'validation', code: 'invalid_per_page' });
        }
        return this.http.get('/orders', { query: { page: options.page, per_page: options.perPage }, lang: options.lang, credentials: 'include' });
    }
    get(orderId, options = {}) {
        const id = orderIdOrThrow(orderId);
        return this.http.get(`/orders/${encodeURIComponent(id)}`, {
            lang: options.lang,
            credentials: 'include',
            headers: guestHeaders(options.guestAccess),
        });
    }
    payment(orderId, input = {}, options = {}) {
        const id = orderIdOrThrow(orderId);
        if (Object.keys(input).some((key) => key !== 'retry' && key !== 'expected_generation')
            || (input.retry !== undefined && typeof input.retry !== 'boolean')
            || (input.expected_generation !== undefined && (!Number.isSafeInteger(input.expected_generation) || input.expected_generation < 0))
            || (input.retry === true && input.expected_generation === undefined)) {
            throw new EldokanClientError({ message: 'Invalid payment retry fields.', kind: 'validation', code: 'invalid_payment_fields' });
        }
        const headers = guestHeaders(options.guestAccess);
        const csrfToken = options.guestAccess ? options.guestAccess.csrfToken : this.sessionState.requireCsrfToken();
        if (!csrfToken)
            throw new EldokanClientError({ message: 'Guest payment requires its own session token.', kind: 'validation', code: 'missing_guest_csrf_token' });
        return this.http.post(`/orders/${encodeURIComponent(id)}/payment`, {
            body: input,
            lang: options.lang,
            credentials: 'include',
            headers,
            csrfToken,
        });
    }
}
//# sourceMappingURL=orders.js.map