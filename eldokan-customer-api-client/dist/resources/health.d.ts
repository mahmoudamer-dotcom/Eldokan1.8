import type { EldokanHttpClient } from '../client.js';
import type { HealthResponse } from '../types.js';
export declare class HealthResource {
    private readonly http;
    constructor(http: EldokanHttpClient);
    get(): Promise<HealthResponse>;
}
//# sourceMappingURL=health.d.ts.map