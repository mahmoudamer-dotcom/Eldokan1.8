import { EldokanClientError } from '../errors.js';
function validate(params) {
    if (params.page !== undefined && (!Number.isInteger(params.page) || params.page < 1)) {
        throw new EldokanClientError({ message: 'page must be a positive integer.', kind: 'validation', code: 'invalid_page' });
    }
    if (params.perPage !== undefined && (!Number.isInteger(params.perPage) || params.perPage < 1 || params.perPage > 100)) {
        throw new EldokanClientError({ message: 'perPage must be between 1 and 100.', kind: 'validation', code: 'invalid_per_page' });
    }
}
export class TagsResource {
    http;
    constructor(http) {
        this.http = http;
    }
    list(params = {}) {
        validate(params);
        return this.http.get('/tags', {
            query: {
                page: params.page,
                per_page: params.perPage,
                search: params.search,
            },
            lang: params.lang,
        });
    }
}
//# sourceMappingURL=tags.js.map