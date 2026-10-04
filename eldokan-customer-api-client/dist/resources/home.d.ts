import type { EldokanHttpClient } from '../client.js';
import type { HomeResponse, LanguageOptions } from '../types.js';
export declare class HomeResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    get(options?: LanguageOptions): Promise<HomeResponse>;
}
//# sourceMappingURL=home.d.ts.map