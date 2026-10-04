import type { EldokanHttpClient } from '../client.js';
import type { SearchSuggestionParams, SuggestionListResponse } from '../types.js';
export declare class SearchResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    suggestions(q: string, params?: SearchSuggestionParams): Promise<SuggestionListResponse>;
}
//# sourceMappingURL=search.d.ts.map