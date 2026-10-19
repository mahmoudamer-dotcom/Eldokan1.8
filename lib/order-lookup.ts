import type { OrderDetail } from '@eldokan/customer-api-client'
import { createEldokanApiForRequest } from '@/lib/eldokan-api'
import { GUEST_ORDER_HISTORY_COOKIE, readGuestOrderHistory, type SavedGuestOrder } from '@/lib/guest-order-history'
import { orderNumber, orderStatusLabel, paymentStatusLabel } from '@/lib/order-status'
import { BUSINESS } from '@/lib/legal-policies'

type Locale = 'ar' | 'en'
export type OrderAnswer = { text: string; links: Array<{ href: string; label: string }> }

/** "فاتورة 22931", "طلب رقم #22931", "invoice 22931" → "22931". */
export function requestedOrderNumber(text: string) {
  if (!/فاتور|ايصال|طلب|اوردر|order|invoice|receipt|#/i.test(text)) return null
  return text.match(/#\s*(\d{2,10})\b/)?.[1] ?? text.match(/(?:^|\s)(\d{2,10})(?:\s|$)/)?.[1] ?? null
}

function cookieValue(request: Request, name: string) {
  return request.headers.get('cookie')?.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`))?.slice(name.length + 1)
}

/** Guest orders this browser can open: the latest checkout plus the saved guest history. */
function guestOrders(request: Request): SavedGuestOrder[] {
  const saved = readGuestOrderHistory(cookieValue(request, GUEST_ORDER_HISTORY_COOKIE))
  try {
    const value = cookieValue(request, 'eldokan_order_recovery')
    const state = value ? JSON.parse(Buffer.from(decodeURIComponent(value), 'base64url').toString('utf8')) as Partial<SavedGuestOrder> : null
    if (state?.order_id && /^ord_[a-f0-9]{64}$/.test(state.order_id) && state.guest_access && /^gok_[a-f0-9]{64}$/.test(state.guest_access.credential) && typeof state.guest_access.csrf_token === 'string') {
      saved.push({ order_id: state.order_id, guest_access: state.guest_access })
    }
  } catch { /* Unreadable recovery state: only the history is checked. */ }
  return saved
}

/**
 * Finds an order by its number among the orders this visitor may already see:
 * their signed-in account orders and the guest orders saved in this browser.
 * Another customer's order is never looked up, whatever number is asked for.
 */
export async function lookupOrder(request: Request, number: string, locale: Locale, onCookie: (cookie: string) => void): Promise<OrderAnswer> {
  const ar = locale === 'ar'
  const api = createEldokanApiForRequest(request, locale, onCookie)
  const matches = (order: { id: string; number?: unknown }) => order.number === number || order.id.endsWith(number)
  let found: OrderDetail | null = null

  for (const saved of guestOrders(request)) {
    try {
      const { data } = await api.orders.get(saved.order_id, { guestAccess: { credential: saved.guest_access.credential, csrfToken: saved.guest_access.csrf_token } })
      if (matches(data)) { found = data; break }
    } catch { /* Expired guest access: skip it. */ }
  }
  let signedIn = false
  if (!found) {
    try {
      for (let page = 1; page <= 5 && !found; page++) {
        const result = await api.orders.list({ page, perPage: 50 })
        signedIn = true
        const summary = result.data.items.find((item) => matches(item as typeof item & { number?: unknown }))
        if (summary) found = (await api.orders.get(summary.id)).data
        if (page >= result.data.pagination.total_pages) break
      }
    } catch { /* Not signed in, or orders unavailable. */ }
  }

  if (!found) {
    return {
      text: ar
        ? `مش لاقي طلب رقم #${number} ${signedIn ? 'في حسابك' : 'محفوظ على المتصفح ده'}. للأمان بقدر أعرض بس طلباتك انت${signedIn ? '' : '، فلو عامل الطلب بحساب سجّل دخول الأول'}. لو متأكد من الرقم كلم الدعم على ${BUSINESS.phone}.`
        : `I couldn’t find order #${number} ${signedIn ? 'in your account' : 'saved in this browser'}. For your security I can only show your own orders${signedIn ? '' : ', so sign in if you ordered with an account'}. If the number is right, contact support on ${BUSINESS.phone}.`,
      links: signedIn ? [] : [{ href: '/account', label: ar ? 'تسجيل الدخول' : 'Sign in' }],
    }
  }

  const date = new Intl.DateTimeFormat(ar ? 'ar-EG' : 'en-EG', { dateStyle: 'medium' }).format(new Date(found.created_at))
  const items = found.lines.reduce((sum, line) => sum + line.quantity, 0)
  const paid = found.payment_status === 'paid'
  return {
    text: ar
      ? `فاتورة الطلب ${orderNumber(found)}\nالتاريخ: ${date}\nالحالة: ${orderStatusLabel(found.status, locale)}\nالدفع: ${paymentStatusLabel(found.payment_status, locale)}\nعدد المنتجات: ${items}\nالإجمالي: ${found.total.formatted}${paid ? '\nافتح الطلب عشان تشوف الفاتورة كاملة وتطبعها.' : '\nافتح الطلب عشان تشوف التفاصيل كاملة.'}`
      : `Invoice for order ${orderNumber(found)}\nDate: ${date}\nStatus: ${orderStatusLabel(found.status, locale)}\nPayment: ${paymentStatusLabel(found.payment_status, locale)}\nItems: ${items}\nTotal: ${found.total.formatted}${paid ? '\nOpen the order to see and print the full invoice.' : '\nOpen the order for full details.'}`,
    links: [{ href: `/orders/${found.id}`, label: ar ? 'افتح الفاتورة' : 'Open invoice' }],
  }
}
