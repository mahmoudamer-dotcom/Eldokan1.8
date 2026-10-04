export class HealthResource {
    http;
    constructor(http) {
        this.http = http;
    }
    get() {
        return this.http.get('/health', { includeLanguage: false });
    }
}
//# sourceMappingURL=health.js.map