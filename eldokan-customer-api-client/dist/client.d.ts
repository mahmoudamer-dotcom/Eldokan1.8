import { type EldokanClientConfig } from './config.js';
import type { Language } from './types.js';
export type QueryValue = string | number | boolean | null | undefined;
export type Query = Record<string, QueryValue>;
export interface RequestOptions {
    query?: Query;
    lang?: Language | undefined;
    signal?: AbortSignal | undefined;
    /** Health has no language parameter; other public catalog endpoints do. */
    includeLanguage?: boolean | undefined;
    credentials?: RequestCredentials | undefined;
    headers?: HeadersInit | undefined;
    body?: unknown;
    csrfToken?: string | undefined;
}
export declare class EldokanHttpClient {
    readonly baseUrl: string;
    readonly defaultLanguage: Language | undefined;
    private readonly timeoutMs;
    private readonly fetchImpl;
    private readonly defaultHeaders;
    private readonly credentials;
    private readonly cache;
    private readonly onResponse;
    constructor(config: EldokanClientConfig);
    get<T>(path: string, options?: RequestOptions): Promise<T>;
    post<T>(path: string, options?: RequestOptions): Promise<T>;
    patch<T>(path: string, options?: RequestOptions): Promise<T>;
    delete<T>(path: string, options?: RequestOptions): Promise<T>;
    private request;
}
//# sourceMappingURL=client.d.ts.map