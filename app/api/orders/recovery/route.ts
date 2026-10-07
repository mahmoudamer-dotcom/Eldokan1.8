import { createEldokanApiForRequest } from '@/lib/eldokan-api'
import { EldokanClientError, type Language } from '@eldokan/customer-api-client'
import { appendCustomerApiCookies } from '@/lib/customer-api-transport'

export const runtime = 'nodejs'

const COOKIE = 'eldokan_order_recovery'
type RecoveryState = {
  checkout_attempt_id: string
  purchase: Record<string, unknown>
  order_id?: string
  guest_access?: { credential: string; csrf_token: string }
}

function readState(request: Request): RecoveryState | null {
  const value = request.headers.get('cookie')?.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${COOKIE}=`))?.slice(COOKIE.length + 1)
  if (!value) return null
  try {
    const state = JSON.parse(Buffer.from(decodeURIComponent(value), 'base64url').toString('utf8')) as RecoveryState
    if (!/^chk_[a-f0-9]{64}$/.test(state.checkout_attempt_id)) return null
    if (!state.purchase || typeof state.purchase !== 'object' || Array.isArray(state.purchase)) return null
    if (state.order_id && !/^ord_[a-f0-9]{64}$/.test(state.order_id)) return null
    if (state.guest_access && (!/^gok_[a-f0-9]{64}$/.test(state.guest_access.credential) || typeof state.guest_access.csrf_token !== 'string')) return null
    return state
  } catch { return null }
}

function stateCookie(value: RecoveryState, request: Request) {
  const encoded = Buffer.from(JSON.stringify(value)).toString('base64url')
  if (encoded.length > 3500) throw new Error('Recovery state exceeds cookie size limit.')
  return `${COOKIE}=${encodeURIComponent(encoded)}; Path=/; HttpOnly; SameSite=Lax; Max-Age=86400${new URL(request.url).protocol === 'https:' ? '; Secure' : ''}`
}

function locale(request: Request): Language {
  return request.headers.get('x-eldokan-locale') === 'ar' ? 'ar' : 'en'
}

function failureDetails(cause: unknown) {
  return cause instanceof EldokanClientError
    ? { code: cause.code, request_id: cause.requestId, issues: cause.issues }
    : {}
}

async function readOrder(request: Request, cookies: string[]) {
  const recovery = readState(request)
  if (recovery && !recovery.order_id) return Response.json({ state: 'attempt_pending' }, { headers: { 'Cache-Control': 'private, no-store' } })
  if (!recovery?.order_id) return Response.json({ success: false, error: { code: 'recovery_unavailable', message: 'No recoverable order is available.' } }, { status: 404, headers: { 'Cache-Control': 'no-store' } })
  try {
    const options = recovery.guest_access ? { guestAccess: { credential: recovery.guest_access.credential, csrfToken: recovery.guest_access.csrf_token } } : {}
    const order = await createEldokanApiForRequest(request, locale(request), (cookie) => cookies.push(cookie)).orders.get(recovery.order_id as `ord_${string}`, options)
    const headers = new Headers({ 'Cache-Control': 'private, no-store' })
    if (recovery.guest_access) headers.set('X-ElDokan-Recovery-Guest', 'true')
    return Response.json(order, { headers })
  } catch (cause) {
    const details = failureDetails(cause)
    return Response.json({ success: false, error: { code: 'order_read_unavailable', message: 'Order status is not available yet.', request_id: details.request_id ?? null } }, { status: 502, headers: { 'Cache-Control': 'no-store' } })
  }
}

async function recoveryAction(request: Request, cookies: string[]) {
  if (request.headers.get('origin') !== new URL(request.url).origin) return Response.json({ error: 'Request origin is not allowed.' }, { status: 403, headers: { 'Cache-Control': 'no-store' } })
  let body: Record<string, unknown>
  try { body = await request.json() as Record<string, unknown> } catch { return Response.json({ error: 'Invalid request.' }, { status: 400, headers: { 'Cache-Control': 'no-store' } }) }
  if (!body || typeof body !== 'object' || Array.isArray(body)) return Response.json({ error: 'Request body must be an object.' }, { status: 400, headers: { 'Cache-Control': 'no-store' } })
  const existing = readState(request)

  if (body.action === 'save-attempt') {
    if (existing && (existing.order_id || existing.checkout_attempt_id !== body.checkout_attempt_id)) return Response.json({ error: 'Recover the existing checkout before starting another order.', code: 'checkout_recovery_required' }, { status: 409, headers: { 'Cache-Control': 'no-store' } })
    if (typeof body.checkout_attempt_id !== 'string' || !/^chk_[a-f0-9]{64}$/.test(body.checkout_attempt_id) || !body.purchase || typeof body.purchase !== 'object' || Array.isArray(body.purchase)) return Response.json({ error: 'Invalid checkout recovery state.' }, { status: 400, headers: { 'Cache-Control': 'no-store' } })
    const state: RecoveryState = { checkout_attempt_id: body.checkout_attempt_id, purchase: body.purchase as Record<string, unknown> }
    try { return Response.json({ saved: true }, { headers: { 'Cache-Control': 'no-store', 'Set-Cookie': stateCookie(state, request) } }) }
    catch { return Response.json({ error: 'Checkout recovery details are too large to store safely.' }, { status: 413, headers: { 'Cache-Control': 'no-store' } }) }
  }

  if (body.action === 'place') {
    if (!existing) return Response.json({ error: 'No checkout attempt is available for recovery.' }, { status: 404, headers: { 'Cache-Control': 'no-store' } })
    try {
      const result = await createEldokanApiForRequest(request, locale(request), (cookie) => cookies.push(cookie)).checkout.placeOrder({
        ...existing.purchase,
        checkout_attempt_id: existing.checkout_attempt_id,
      } as import('@eldokan/customer-api-client').PlacementInput)
      const guest = result.data.guest_access
      const updated: RecoveryState = { ...existing, order_id: result.data.order_id, ...(guest ? { guest_access: { credential: guest.credential, csrf_token: guest.csrf_token } } : {}) }
      try { return Response.json({ success: true, data: { order_id: result.data.order_id, payment: result.data.payment }, meta: result.meta }, { headers: { 'Cache-Control': 'private, no-store', 'Set-Cookie': stateCookie(updated, request) } }) }
      catch { return Response.json({ error: 'Order may be placed, but recovery state could not be saved. Keep this same attempt and contact support.' }, { status: 502, headers: { 'Cache-Control': 'no-store' } }) }
    } catch (cause) {
      return Response.json({ error: 'Order placement is uncertain. Keep this checkout and recover using the same attempt.', ...failureDetails(cause) }, { status: 502, headers: { 'Cache-Control': 'no-store' } })
    }
  }

  if (!existing?.order_id) return Response.json({ error: 'No recoverable order is available.' }, { status: 404, headers: { 'Cache-Control': 'no-store' } })
  const access = existing.guest_access ? { guestAccess: { credential: existing.guest_access.credential, csrfToken: existing.guest_access.csrf_token } } : {}
  const api = createEldokanApiForRequest(request, locale(request), (cookie) => cookies.push(cookie))

  if (body.action === 'payment') {
    const retry = body.retry === true
    if (retry && (!Number.isInteger(body.expected_generation) || Number(body.expected_generation) < 0)) return Response.json({ error: 'A current payment generation is required.' }, { status: 400 })
    try {
      if (!existing.guest_access) await api.auth.session()
      const payment = await api.orders.payment(existing.order_id as `ord_${string}`, retry ? { retry: true, expected_generation: Number(body.expected_generation) } : {}, access)
      const redirect = payment.data.requires_redirect && payment.data.redirect_url ? new URL(payment.data.redirect_url) : null
      if (redirect && (redirect.protocol !== 'https:' || redirect.hostname !== 'accept.paymob.com')) return Response.json({ error: 'The hosted payment URL failed validation.' }, { status: 502 })
      return Response.json(payment, { headers: { 'Cache-Control': 'no-store' } })
    } catch (cause) {
      return Response.json({ error: 'Payment state is uncertain. Keep this order and try recovery again.', ...failureDetails(cause) }, { status: 502, headers: { 'Cache-Control': 'no-store' } })
    }
  }

  if (body.action === 'clear') return Response.json({ cleared: true }, { headers: { 'Cache-Control': 'no-store', 'Set-Cookie': `${COOKIE}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0` } })
  return Response.json({ error: 'Unsupported recovery action.' }, { status: 400 })
}

export async function GET(request: Request) {
  const cookies: string[] = []
  return appendCustomerApiCookies(await readOrder(request, cookies), cookies)
}

export async function POST(request: Request) {
  const cookies: string[] = []
  return appendCustomerApiCookies(await recoveryAction(request, cookies), cookies)
}
