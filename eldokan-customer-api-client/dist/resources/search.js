import { EldokanClientError } from '../errors.js';
export class SearchResource {
    http;
    constructor(http) {
        this.http = http;
    }
    suggestions(q, params = {}) {
        const clean = q.trim();
        if ([...clean].length < 2) {
            throw new EldokanClientError({
                message: 'Search query must be at least 2 characters.',
                kind: 'validation',
                code: 'search_too_short',
            });
        }
        if (params.limit !== undefined && (!Number.isInteger(params.limit) || params.limit < 1 || params.limit > 20)) {
            throw new EldokanClientError({
                message: 'Search suggestion limit must be between 1 and 20.',
                kind: 'validation',
                code: 'invalid_suggestion_limit',
            });
        }
        return this.http.get('/search/suggestions', {
            query: { q: clean, limit: params.limit },
            lang: params.lang,
        });
    }
}
//# sourceMappingURL=search.js.map