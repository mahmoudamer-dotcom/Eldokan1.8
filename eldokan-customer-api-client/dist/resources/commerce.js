/** Customer commerce extensions. Provider secrets and shipping APIs stay on the server. */
export class CommerceResource {
    http;
    session;
    constructor(http, session) {
        this.http = http;
        this.session = session;
    }
    write(path, body) {
        return this.http.post(path, { body, credentials: 'include', csrfToken: this.session.requireCsrfToken(), includeLanguage: false });
    }
    forgotPassword(email) {
        return this.http.post('/auth/forgot-password', { body: { email }, credentials: 'include', includeLanguage: false });
    }
    resetPassword(input) {
        return this.http.post('/auth/reset-password', { body: input, credentials: 'include', includeLanguage: false });
    }
    returns(id) {
        return this.http.get(`/orders/${encodeURIComponent(id)}/returns`, { credentials: 'include', includeLanguage: false });
    }
    requestReturn(id, input) {
        return this.write(`/orders/${encodeURIComponent(id)}/returns`, input);
    }
    alerts() {
        return this.http.get('/me/product-alerts', { credentials: 'include', includeLanguage: false });
    }
    saveAlert(input) {
        return this.write('/me/product-alerts', input);
    }
    removeAlert(productId) {
        return this.http.delete(`/me/product-alerts/${encodeURIComponent(productId)}`, { credentials: 'include', csrfToken: this.session.requireCsrfToken(), includeLanguage: false });
    }
    decision() {
        return this.http.get('/me/decision', { credentials: 'include', includeLanguage: false });
    }
    saveDecision(input) { return this.write('/me/decision', input); }
    feedback(reviewId, action, reason = '') {
        return this.write(`/reviews/${encodeURIComponent(reviewId)}/feedback`, { action, reason });
    }
    addReviewImage(reviewId, image) {
        return this.write(`/reviews/${encodeURIComponent(reviewId)}/images`, { image });
    }
    sitemap(page = 1) { return this.http.get('/catalog/sitemap', { query: { page }, includeLanguage: false }); }
}
//# sourceMappingURL=commerce.js.map