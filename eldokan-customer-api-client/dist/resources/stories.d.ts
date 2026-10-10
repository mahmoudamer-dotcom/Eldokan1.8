import type { EldokanHttpClient } from '../client.js';
import type { LanguageOptions, ProductCard } from '../types.js';
export interface StoreStory {
    id: string;
    title: string;
    image: {
        url: string;
        alt: string;
    } | null;
    product: ProductCard;
}
export interface StoriesResponse {
    success: true;
    data: {
        items: StoreStory[];
    };
    meta: {
        request_id: string;
    };
}
/** Requires the ElDokan Stories companion plugin on WordPress. */
export declare class StoriesResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    list(options?: LanguageOptions): Promise<StoriesResponse>;
}
//# sourceMappingURL=stories.d.ts.map