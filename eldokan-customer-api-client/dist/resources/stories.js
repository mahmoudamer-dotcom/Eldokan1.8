/** Requires the ElDokan Stories companion plugin on WordPress. */
export class StoriesResource {
    http;
    constructor(http) {
        this.http = http;
    }
    list(options = {}) {
        return this.http.get('/stories', { lang: options.lang });
    }
}
//# sourceMappingURL=stories.js.map