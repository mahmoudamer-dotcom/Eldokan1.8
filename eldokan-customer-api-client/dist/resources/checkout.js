export class CheckoutResource {
    http;
    sessionState;
    constructor(http, sessionState) {
        this.http = http;
        this.sessionState = sessionState;
    }
    async get(options = {}) {
        const result = await this.http.get('/checkout', { lang: options.lang, credentials: 'include' });
        this.sessionState.setCsrfToken(result.data.cart.csrf_token);
        return result;
    }
    async quote(input, options = {}) {
        const csrfToken = await this.ensureCsrf(options);
        return this.http.post('/checkout/quote', { body: input, lang: options.lang, credentials: 'include', csrfToken });
    }
    async createAttempt(input, options = {}) {
        const csrfToken = await this.ensureCsrf(options);
        return this.http.post('/checkout/attempts', { body: input, lang: options.lang, credentials: 'include', csrfToken });
    }
    async placeOrder(input, options = {}) {
        const csrfToken = await this.ensureCsrf(options);
        return this.http.post('/checkout/orders', { body: input, lang: options.lang, credentials: 'include', csrfToken });
    }
    async ensureCsrf(options) {
        if (!this.sessionState.getCsrfToken()) {
            const response = await this.http.get('/cart', { lang: options.lang, credentials: 'include' });
            this.sessionState.setCsrfToken(response.data.csrf_token);
        }
        return this.sessionState.requireCsrfToken();
    }
}
//# sourceMappingURL=checkout.js.map