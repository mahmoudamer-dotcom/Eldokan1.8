import { createEldokanCustomerApiClient, type Language } from '@eldokan/customer-api-client'
import { createCustomerApiTransport } from './customer-api-transport'
import { withCatalogLanguage } from './catalog-language'

const DEFAULT_API_BASE_URL = 'https://www.eldokan.com/wp-json/eldokan-customer/v1'

/** Browser requests always go through the same-origin Next.js proxy. */
export function getBrowserEldokanApiBaseUrl() {
  if (typeof window === 'undefined') {
    throw new Error('getBrowserEldokanApiBaseUrl must only be called in the browser.')
  }
  return new URL('/api/customer', window.location.origin).toString()
}

const CATALOG_REVALIDATE_SECONDS = 60

/**
 * Anonymous server-side catalog reads are shared for a short time so every page
 * does not wait on the store for the same public data. Cart, checkout, account
 * and order requests never use this client; they go through the request-scoped
 * transport and stay uncached. Free-text searches are not stored.
 */
const cachedCatalogFetch: typeof fetch = (input, init) => {
  const url = withCatalogLanguage(input)
  const method = init?.method ?? 'GET'
  if (method !== 'GET' || url.pathname.includes('/search') || url.searchParams.has('search')) return fetch(url, init)
  const { cache: _cache, ...rest } = init ?? {}
  void _cache
  return fetch(url, { ...rest, next: { revalidate: CATALOG_REVALIDATE_SECONDS } })
}

function createClient(
  baseUrl = process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
  defaultLanguage: Language = 'en',
) {
  return createEldokanCustomerApiClient({
    baseUrl,
    defaultLanguage,
    ...(typeof window === 'undefined' ? { fetch: cachedCatalogFetch } : {}),
  })
}

const browserClients: Record<string, ReturnType<typeof createClient>> = {}

/** Keep browser session state in one client while isolating every server request. */
export function createEldokanApi(language: Language = 'en') {
  if (typeof window !== 'undefined') {
    // Keep browser requests same-origin; the route handler forwards them using
    // the same customer API client without browser CORS or third-party cookie limits.
    const browserBaseUrl = getBrowserEldokanApiBaseUrl()
    const cacheKey = `${language}:${browserBaseUrl}`
    return browserClients[cacheKey] ??= createClient(browserBaseUrl, language)
  }
  return createClient(undefined, language)
}

/** Create an isolated server client that forwards this request's HttpOnly API cookies. */
export function createEldokanApiForRequest(request: Request, language: Language = 'en', onCookie?: (cookie: string) => void) {
  return createEldokanCustomerApiClient({
    baseUrl: process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
    defaultLanguage: language,
    fetch: createCustomerApiTransport(request, onCookie),
  })
}
