import { EldokanClientError } from './errors.js';
export class CustomerSessionState {
    csrfToken = null;
    setCsrfToken(value) {
        this.csrfToken = value;
    }
    clear() {
        this.csrfToken = null;
    }
    requireCsrfToken() {
        if (!this.csrfToken) {
            throw new EldokanClientError({
                message: 'Restore or create a customer session before this mutation.',
                kind: 'validation',
                code: 'missing_csrf_token',
            });
        }
        return this.csrfToken;
    }
}
//# sourceMappingURL=session.js.map