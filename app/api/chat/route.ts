import { isSameOriginRequest } from '@/lib/request-origin'
import { answerCustomer } from '@/lib/store-assistant'
import { appendCustomerApiCookies } from '@/lib/customer-api-transport'

export const runtime = 'nodejs'

const WINDOW_MS = 60_000
const LIMIT = 30
const hits = new Map<string, { count: number; reset: number }>()

/** Small per-client limit so the catalog cannot be flooded through the chat. */
function allowed(request: Request) {
  const key = request.headers.get('x-forwarded-for')?.split(',')[0].trim() || request.headers.get('x-real-ip') || 'local'
  const now = Date.now()
  const entry = hits.get(key)
  if (!entry || entry.reset < now) {
    if (hits.size >= 5000) hits.clear()
    hits.set(key, { count: 1, reset: now + WINDOW_MS })
    return true
  }
  return ++entry.count <= LIMIT
}

export async function POST(request: Request) {
  const headers = { 'Cache-Control': 'no-store' }
  if (!isSameOriginRequest(request)) return Response.json({ error: 'Forbidden' }, { status: 403, headers })
  if (!allowed(request)) return Response.json({ error: 'Too many messages. Wait a minute and try again.' }, { status: 429, headers })
  let body: { message?: unknown; locale?: unknown }
  try { body = await request.json() } catch { return Response.json({ error: 'Invalid request' }, { status: 400, headers }) }
  const message = typeof body.message === 'string' ? body.message.trim() : ''
  if (!message || message.length > 500) return Response.json({ error: 'Invalid message' }, { status: 400, headers })
  // Reply in the language the customer wrote in, whatever the site language is.
  const locale = /[؀-ۿ]/.test(message) ? 'ar' : /[a-z]/i.test(message) ? 'en' : body.locale === 'ar' ? 'ar' : 'en'
  const cookies: string[] = []
  const data = await answerCustomer(message, locale, request, (cookie) => cookies.push(cookie))
  return appendCustomerApiCookies(Response.json({ data }, { headers }), cookies)
}
