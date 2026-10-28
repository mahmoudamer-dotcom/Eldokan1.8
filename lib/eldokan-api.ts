import { createEldokanCustomerApiClient, type Language } from '@eldokan/customer-api-client'
import { createCustomerApiTransport } from './customer-api-transport'
import { withCatalogLanguage } from './catalog-language'
import { localizingFetch } from './catalog-translations'

const DEFAULT_API_BASE_URL = 'https://www.eldokan.com/wp-json/eldokan-customer/v1'

/** Browser requests always go through the same-origin Next.js proxy. */
export function getBrowserEldokanApiBaseUrl() {
  if (typeof window === 'undefined') {
    throw new Error('getBrowserEldokanApiBaseUrl must only be called in the browser.')
  }
  return new URL('/api/customer', window.location.origin).toString()
}

// Public catalog reads are shared for 5 minutes. Checkout always re-prices and
// re-checks stock on the server, so a briefly older listing cannot be bought wrong.
const CATALOG_REVALIDATE_SECONDS = 300
const SEARCH_REVALIDATE_SECONDS = 120

/**
 * Anonymous server-side catalog reads are shared for a short time so every page
 * does not wait on the store for the same public data. Cart, checkout, account
 * and order requests never use this client; they go through the request-scoped
 * transport and stay uncached. Searches are public too and kept for 2 minutes.
 */
const cachedCatalogFetch: typeof fetch = (input, init) => {
  const url = withCatalogLanguage(input)
  const method = init?.method ?? 'GET'
  if (method !== 'GET') return fetch(url, init)
  // Home sections (hero slides, banners, shelves) are edited in WordPress and must show at
  // once; the plugin keeps its own cache and clears it on save, so this read stays fast.
  if (url.pathname.endsWith('/home')) return fetch(url, { ...init, cache: 'no-store' })
  const search = url.pathname.includes('/search') || url.searchParams.has('search')
  const { cache: _cache, ...rest } = init ?? {}
  void _cache
  return fetch(url, { ...rest, next: { revalidate: search ? SEARCH_REVALIDATE_SECONDS : CATALOG_REVALIDATE_SECONDS } })
}

function createClient(
  baseUrl = process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
  defaultLanguage: Language = 'en',
) {
  return createEldokanCustomerApiClient({
    baseUrl,
    defaultLanguage,
    // The store answers in English; the server swaps in local Arabic names (see catalog-translations).
    // Browser requests name the visitor's language so the same-origin proxy can do the same.
    fetch: typeof window === 'undefined'
      ? localizingFetch(cachedCatalogFetch, defaultLanguage)
      : (input, init) => {
          const headers = new Headers(init?.headers)
          headers.set('x-eldokan-locale', defaultLanguage)
          return fetch(input, { ...init, headers })
        },
  })
}

const browserClients: Record<string, ReturnType<typeof createClient>> = {}
const SESSION_REUSE_MS = 15_000
const sessionResets = new Set<() => void>()

/**
 * The navbar, cart, wishlist, reviews and pages all ask "who is signed in?" on
 * load. Share one answer for a few seconds instead of one request each. Signing
 * in, out or registering, or any session change event, asks again.
 */
function shareSessionReads(client: ReturnType<typeof createClient>) {
  let pending: ReturnType<typeof client.auth.session> | null = null
  let fetchedAt = 0
  const reset = () => { pending = null }
  sessionResets.add(reset)
  const read = client.auth.session.bind(client.auth)
  client.auth.session = () => {
    if (!pending || Date.now() - fetchedAt > SESSION_REUSE_MS) {
      fetchedAt = Date.now()
      pending = read()
      pending.catch(() => {})
    }
    return pending
  }
  for (const name of ['login', 'logout', 'register'] as const) {
    const action = client.auth[name].bind(client.auth) as (...args: unknown[]) => Promise<unknown>
    ;(client.auth[name] as unknown) = (...args: unknown[]) => { reset(); return action(...args).finally(reset) }
  }
  return client
}

if (typeof window !== 'undefined') {
  // Capture phase: cleared before any component listener reads the session again.
  for (const event of ['eldokan:session-changed', 'eldokan:signed-out']) {
    window.addEventListener(event, () => { for (const reset of sessionResets) reset() }, { capture: true })
  }
}

/** Keep browser session state in one client while isolating every server request. */
export function createEldokanApi(language: Language = 'en') {
  if (typeof window !== 'undefined') {
    // Keep browser requests same-origin; the route handler forwards them using
    // the same customer API client without browser CORS or third-party cookie limits.
    const browserBaseUrl = getBrowserEldokanApiBaseUrl()
    const cacheKey = `${language}:${browserBaseUrl}`
    return browserClients[cacheKey] ??= shareSessionReads(createClient(browserBaseUrl, language))
  }
  return createClient(undefined, language)
}

/** Create an isolated server client that forwards this request's HttpOnly API cookies. */
export function createEldokanApiForRequest(request: Request, language: Language = 'en', onCookie?: (cookie: string) => void) {
  return createEldokanCustomerApiClient({
    baseUrl: process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
    defaultLanguage: language,
    fetch: localizingFetch(createCustomerApiTransport(request, onCookie), language),
  })
}
