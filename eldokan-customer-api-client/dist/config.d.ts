import type { Language, EldokanResponseContext } from './types.js';
export interface EldokanClientConfig {
    /**
     * Set from one environment variable in the frontend application.
     * The client deliberately does not embed any current backend hostname.
     */
    baseUrl: string;
    defaultLanguage?: Language;
    timeoutMs?: number;
    fetch?: typeof globalThis.fetch;
    headers?: HeadersInit;
    /** Default for public calls. Auth/Account/Wishlist always override this with include. */
    credentials?: RequestCredentials;
    /** Defaults to no-store so Next/browser caching cannot silently outlive ElDokan price/stock policy. */
    cache?: RequestCache;
    onResponse?: (context: EldokanResponseContext) => void;
}
export declare const DEFAULT_TIMEOUT_MS = 10000;
//# sourceMappingURL=config.d.ts.map