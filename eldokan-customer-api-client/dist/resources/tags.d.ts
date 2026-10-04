import type { EldokanHttpClient } from '../client.js';
import type { CatalogTermListParams, TagListResponse } from '../types.js';
export declare class TagsResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    list(params?: CatalogTermListParams): Promise<TagListResponse>;
}
//# sourceMappingURL=tags.d.ts.map