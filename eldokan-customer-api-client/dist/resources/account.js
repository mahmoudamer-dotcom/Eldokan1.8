export class AccountResource {
    http;
    sessionState;
    constructor(http, sessionState) {
        this.http = http;
        this.sessionState = sessionState;
    }
    me() {
        return this.http.get('/me', {
            credentials: 'include',
            includeLanguage: false,
        });
    }
    update(input) {
        return this.http.patch('/me', {
            body: input,
            credentials: 'include',
            csrfToken: this.sessionState.requireCsrfToken(),
            includeLanguage: false,
        });
    }
}
//# sourceMappingURL=account.js.map