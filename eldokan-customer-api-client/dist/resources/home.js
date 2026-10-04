export class HomeResource {
    http;
    constructor(http) {
        this.http = http;
    }
    get(options = {}) {
        return this.http.get('/home', { lang: options.lang });
    }
}
//# sourceMappingURL=home.js.map