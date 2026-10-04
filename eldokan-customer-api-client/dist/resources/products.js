import { EldokanClientError } from '../errors.js';
import { serializeAttributeFilters } from '../filters.js';
const PRODUCT_ID = /^prd_[1-9][0-9]*$/;
function validation(message, code) {
    throw new EldokanClientError({ message, kind: 'validation', code });
}
function assertPositiveInteger(value, name, max) {
    if (value === undefined)
        return;
    if (!Number.isInteger(value) || value < 1 || (max !== undefined && value > max)) {
        validation(`${name} is outside the supported range.`, `invalid_${name}`);
    }
}
export class ProductsResource {
    http;
    constructor(http) {
        this.http = http;
    }
    list(params = {}) {
        assertPositiveInteger(params.page, 'page');
        assertPositiveInteger(params.perPage, 'per_page', 48);
        if (params.minPrice !== undefined && params.minPrice < 0)
            validation('minPrice cannot be negative.', 'invalid_min_price');
        if (params.maxPrice !== undefined && params.maxPrice < 0)
            validation('maxPrice cannot be negative.', 'invalid_max_price');
        if (params.minPrice !== undefined && params.maxPrice !== undefined && params.minPrice > params.maxPrice) {
            validation('minPrice must not exceed maxPrice.', 'invalid_price_range');
        }
        let attributes;
        if (typeof params.attributes === 'string')
            attributes = params.attributes;
        else if (params.attributes)
            attributes = serializeAttributeFilters(params.attributes);
        if (attributes && !params.category) {
            validation('category is required when attribute filters are used.', 'category_required_for_attributes');
        }
        const query = {
            page: params.page,
            per_page: params.perPage,
            search: params.search,
            category: params.category,
            brand: params.brand,
            tag: params.tag,
            min_price: params.minPrice,
            max_price: params.maxPrice,
            stock_status: params.stockStatus,
            on_sale: params.onSale,
            featured: params.featured,
            sort: params.sort,
            attributes,
        };
        return this.http.get('/products', {
            query,
            lang: params.lang,
        });
    }
    get(productId, options = {}) {
        if (!PRODUCT_ID.test(productId))
            validation(`Invalid product ID: ${productId}`, 'invalid_product_id');
        return this.http.get(`/products/${encodeURIComponent(productId)}`, {
            lang: options.lang,
        });
    }
    lookupBySlug(slug, options = {}) {
        const clean = slug.trim();
        if (!clean)
            validation('Product slug is required.', 'missing_product_slug');
        return this.http.get('/products/lookup', {
            query: { slug: clean },
            lang: options.lang,
        });
    }
}
//# sourceMappingURL=products.js.map