import { createEldokanCustomerApiClient, type Language } from '@eldokan/customer-api-client'
import { createCustomerApiTransport } from './customer-api-transport'

const DEFAULT_API_BASE_URL = 'https://www.eldokan.com/wp-json/eldokan-customer/v1'

/** Browser requests always go through the same-origin Next.js proxy. */
export function getBrowserEldokanApiBaseUrl() {
  if (typeof window === 'undefined') {
    throw new Error('getBrowserEldokanApiBaseUrl must only be called in the browser.')
  }
  return new URL('/api/customer', window.location.origin).toString()
}

function createClient(
  baseUrl = process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
  defaultLanguage: Language = 'en',
) {
  return createEldokanCustomerApiClient({
    baseUrl,
    defaultLanguage,
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
