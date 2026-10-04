export class AuthResource {
    http;
    sessionState;
    constructor(http, sessionState) {
        this.http = http;
        this.sessionState = sessionState;
    }
    async register(input) {
        const response = await this.http.post('/auth/register', {
            body: input,
            credentials: 'include',
            includeLanguage: false,
        });
        this.sessionState.setCsrfToken(response.data.csrf_token);
        return response;
    }
    async login(input) {
        const response = await this.http.post('/auth/login', {
            body: input,
            credentials: 'include',
            includeLanguage: false,
        });
        this.sessionState.setCsrfToken(response.data.csrf_token);
        return response;
    }
    async session() {
        const response = await this.http.get('/auth/session', {
            credentials: 'include',
            includeLanguage: false,
        });
        this.sessionState.setCsrfToken(response.data.csrf_token);
        return response;
    }
    async logout() {
        const response = await this.http.post('/auth/logout', {
            credentials: 'include',
            csrfToken: this.sessionState.requireCsrfToken(),
            includeLanguage: false,
        });
        this.sessionState.clear();
        return response;
    }
}
//# sourceMappingURL=auth.js.map