import { DEFAULT_TIMEOUT_MS, } from './config.js';
import { EldokanClientError, isErrorEnvelope } from './errors.js';
function normalizeBaseUrl(url) {
    const trimmed = url.trim().replace(/\/+$/, '');
    if (!/^https?:\/\//i.test(trimmed)) {
        throw new EldokanClientError({
            message: 'baseUrl must be an absolute http(s) URL.',
            kind: 'validation',
            code: 'invalid_base_url',
        });
    }
    return trimmed;
}
function contextFrom(response, fallbackUrl) {
    const h = response.headers;
    return {
        url: response.url || fallbackUrl,
        status: response.status,
        requestId: h.get('X-ElDokan-Request-ID'),
        apiVersion: h.get('X-ElDokan-API-Version'),
        contentLanguage: h.get('Content-Language'),
        cacheStatus: h.get('X-ElDokan-Cache'),
        cacheTtl: h.get('X-ElDokan-Cache-TTL'),
        cacheGeneration: h.get('X-ElDokan-Cache-Generation'),
        cacheKey: h.get('X-ElDokan-Cache-Key'),
        cacheBackend: h.get('X-ElDokan-Cache-Backend'),
        cacheStore: h.get('X-ElDokan-Cache-Store'),
        serverTiming: h.get('Server-Timing'),
    };
}
function isAbortError(error) {
    return error instanceof DOMException && error.name === 'AbortError';
}
export class EldokanHttpClient {
    baseUrl;
    defaultLanguage;
    timeoutMs;
    fetchImpl;
    defaultHeaders;
    credentials;
    cache;
    onResponse;
    constructor(config) {
        this.baseUrl = normalizeBaseUrl(config.baseUrl);
        this.defaultLanguage = config.defaultLanguage;
        this.timeoutMs = config.timeoutMs ?? DEFAULT_TIMEOUT_MS;
        if (!Number.isFinite(this.timeoutMs) || this.timeoutMs <= 0) {
            throw new EldokanClientError({
                message: 'timeoutMs must be a positive number.',
                kind: 'validation',
                code: 'invalid_timeout',
            });
        }
        this.fetchImpl = config.fetch ?? globalThis.fetch;
        if (typeof this.fetchImpl !== 'function') {
            throw new EldokanClientError({
                message: 'No fetch implementation is available.',
                kind: 'validation',
                code: 'missing_fetch',
            });
        }
        this.defaultHeaders = new Headers(config.headers);
        this.credentials = config.credentials ?? 'omit';
        this.cache = config.cache ?? 'no-store';
        this.onResponse = config.onResponse;
    }
    async get(path, options = {}) {
        return this.request('GET', path, options);
    }
    async post(path, options = {}) {
        return this.request('POST', path, options);
    }
    async patch(path, options = {}) {
        return this.request('PATCH', path, options);
    }
    async delete(path, options = {}) {
        return this.request('DELETE', path, options);
    }
    async request(method, path, options) {
        const url = new URL(`${this.baseUrl}${path.startsWith('/') ? path : `/${path}`}`);
        const query = { ...(options.query ?? {}) };
        if (options.includeLanguage !== false) {
            const lang = options.lang ?? this.defaultLanguage;
            if (lang)
                query.lang = lang;
        }
        for (const [key, value] of Object.entries(query)) {
            if (value === undefined || value === null || value === '')
                continue;
            url.searchParams.set(key, String(value));
        }
        const controller = new AbortController();
        let timedOut = false;
        const timeout = setTimeout(() => {
            timedOut = true;
            controller.abort();
        }, this.timeoutMs);
        const externalSignal = options.signal;
        const abortFromExternal = () => controller.abort();
        if (externalSignal) {
            if (externalSignal.aborted)
                controller.abort();
            else
                externalSignal.addEventListener('abort', abortFromExternal, { once: true });
        }
        const headers = new Headers(this.defaultHeaders);
        new Headers(options.headers).forEach((value, key) => headers.set(key, value));
        if (!headers.has('Accept'))
            headers.set('Accept', 'application/json');
        if (options.body !== undefined && !headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }
        if (options.csrfToken)
            headers.set('X-ElDokan-CSRF', options.csrfToken);
        try {
            const init = {
                method,
                headers,
                signal: controller.signal,
                credentials: options.credentials ?? this.credentials,
                cache: this.cache,
            };
            if (options.body !== undefined)
                init.body = JSON.stringify(options.body);
            // Preserve the global receiver required by browser Fetch implementations.
            const response = await Reflect.apply(this.fetchImpl, globalThis, [url, init]);
            const context = contextFrom(response, url.toString());
            try {
                this.onResponse?.(context);
            }
            catch {
                // Observability hooks must never break catalog requests.
            }
            let body;
            try {
                body = await response.json();
            }
            catch (error) {
                throw new EldokanClientError({
                    message: `ElDokan API returned non-JSON content (HTTP ${response.status}).`,
                    kind: 'invalid_response',
                    code: 'invalid_json_response',
                    status: response.status,
                    requestId: context.requestId,
                    context,
                    cause: error,
                });
            }
            if (!response.ok) {
                const envelope = isErrorEnvelope(body) ? body : null;
                throw new EldokanClientError({
                    message: envelope?.error.message ?? `ElDokan API request failed with HTTP ${response.status}.`,
                    kind: 'api',
                    code: envelope?.error.code ?? `http_${response.status}`,
                    status: response.status,
                    requestId: envelope?.meta.request_id ?? context.requestId,
                    context,
                });
            }
            if (!body || typeof body !== 'object' || body.success !== true) {
                const maybeError = isErrorEnvelope(body) ? body : null;
                throw new EldokanClientError({
                    message: maybeError?.error.message ?? 'ElDokan API success response has an invalid envelope.',
                    kind: 'invalid_response',
                    code: maybeError?.error.code ?? 'invalid_success_envelope',
                    status: response.status,
                    requestId: maybeError?.meta.request_id ?? context.requestId,
                    context,
                });
            }
            return body;
        }
        catch (error) {
            if (error instanceof EldokanClientError)
                throw error;
            if (timedOut) {
                throw new EldokanClientError({
                    message: `ElDokan API request timed out after ${this.timeoutMs}ms.`,
                    kind: 'timeout',
                    code: 'request_timeout',
                    cause: error,
                });
            }
            if (isAbortError(error) || externalSignal?.aborted) {
                throw new EldokanClientError({
                    message: 'ElDokan API request was aborted.',
                    kind: 'network',
                    code: 'request_aborted',
                    cause: error,
                });
            }
            throw new EldokanClientError({
                message: 'Network error while calling ElDokan API.',
                kind: 'network',
                code: 'network_error',
                cause: error,
            });
        }
        finally {
            clearTimeout(timeout);
            externalSignal?.removeEventListener('abort', abortFromExternal);
        }
    }
}
//# sourceMappingURL=client.js.map
