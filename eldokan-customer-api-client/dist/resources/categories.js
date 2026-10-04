import { EldokanClientError } from '../errors.js';
function slugOrThrow(slug) {
    const clean = slug.trim();
    if (!clean) {
        throw new EldokanClientError({
            message: 'Category slug is required.',
            kind: 'validation',
            code: 'missing_category_slug',
        });
    }
    return clean;
}
export class CategoriesResource {
    http;
    constructor(http) {
        this.http = http;
    }
    list(params = {}) {
        return this.http.get('/categories', {
            query: { parent: params.parent },
            lang: params.lang,
        });
    }
    get(slug, options = {}) {
        return this.http.get(`/categories/${encodeURIComponent(slugOrThrow(slug))}`, { lang: options.lang });
    }
    filters(slug, options = {}) {
        return this.http.get(`/categories/${encodeURIComponent(slugOrThrow(slug))}/filters`, { lang: options.lang });
    }
}
//# sourceMappingURL=categories.js.map