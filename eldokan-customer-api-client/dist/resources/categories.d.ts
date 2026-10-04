import type { EldokanHttpClient } from '../client.js';
import type { CategoryDetailResponse, CategoryFiltersResponse, CategoryListParams, CategoryListResponse, LanguageOptions } from '../types.js';
export declare class CategoriesResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    list(params?: CategoryListParams): Promise<CategoryListResponse>;
    get(slug: string, options?: LanguageOptions): Promise<CategoryDetailResponse>;
    filters(slug: string, options?: LanguageOptions): Promise<CategoryFiltersResponse>;
}
//# sourceMappingURL=categories.d.ts.map