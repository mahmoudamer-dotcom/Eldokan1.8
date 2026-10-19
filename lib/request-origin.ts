import { getStorefrontUrl } from './site-url'

function firstHeaderValue(request: Request, name: string) {
  return request.headers.get(name)?.split(',')[0].trim() || undefined
}

/**
 * Behind a reverse proxy `request.url` is the internal listener address, so the
 * public host comes from the forwarded headers the proxy sets.
 */
function publicHost(request: Request) {
  return firstHeaderValue(request, 'x-forwarded-host') ?? request.headers.get('host') ?? new URL(request.url).host
}

/** Same-origin check for mutations. A cross-site browser request cannot forge Origin or Host. */
export function isSameOriginRequest(request: Request) {
  const origin = request.headers.get('origin')
  if (!origin) return false
  if (origin === new URL(request.url).origin || origin === getStorefrontUrl()?.origin) return true
  try {
    return new URL(origin).host === publicHost(request)
  } catch { return false }
}

export function isSecureRequest(request: Request) {
  if (new URL(request.url).protocol === 'https:' || firstHeaderValue(request, 'x-forwarded-proto') === 'https') return true
  const storefront = getStorefrontUrl()
  return storefront?.protocol === 'https:' && storefront.host === publicHost(request)
}
