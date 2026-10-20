import { withCatalogLanguage } from './catalog-language'

/**
 * Requests that change nothing on the store: safe to send again when the first try
 * fails at the network level (for example a kept-alive connection the host closed).
 */
function retryable(url: URL, method: string) {
  return method === 'GET' || method === 'HEAD' || url.pathname.endsWith('/checkout/quote')
}

/** Isolated cookie jar for sequential API calls made by one storefront request. */
export function createCustomerApiTransport(request: Request, onCookie: (cookie: string) => void = () => {}) {
  const jar = new Map<string, string>()
  function remember(pair: string) {
    const value = pair.trim()
    const separator = value.indexOf('=')
    if (separator <= 0) return
    const name = value.slice(0, separator)
    if (name !== 'eldokan_order_recovery' && name !== 'eldokan_guest_order_history') jar.set(name, value)
  }
  request.headers.get('cookie')?.split(';').forEach(remember)
  const transport: typeof fetch = async (input, init) => {
    const headers = new Headers(init?.headers)
    if (jar.size) headers.set('cookie', [...jar.values()].join('; '))
    const nonce = request.headers.get('x-wp-nonce')
    if (nonce) headers.set('x-wp-nonce', nonce)
    const url = withCatalogLanguage(input)
    const options = { ...init, headers, cache: 'no-store' as const }
    let response: Response
    try {
      response = await fetch(url, options)
    } catch (cause) {
      if (!retryable(url, (init?.method ?? 'GET').toUpperCase()) || init?.signal?.aborted) throw cause
      await new Promise((resolve) => setTimeout(resolve, 250))
      response = await fetch(url, options)
    }
    for (const cookie of response.headers.getSetCookie()) {
      remember(cookie.split(';', 1)[0])
      onCookie(cookie)
    }
    return response
  }
  return transport
}

export function appendCustomerApiCookies(response: Response, cookies: string[]) {
  for (const cookie of cookies) {
    const localCookie = cookie.replace(/;\s*domain=[^;]*/i, '').replace(/;\s*path=[^;]*/i, '')
    response.headers.append('set-cookie', `${localCookie}; Path=/`)
  }
  return response
}
