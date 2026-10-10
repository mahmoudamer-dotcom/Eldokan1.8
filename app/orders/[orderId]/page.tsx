import AccountOrderDetail from '@/components/checkout/AccountOrderDetail'
import OrderResult from '@/components/checkout/OrderResult'
import { cookies } from 'next/headers'
import { getLocale } from '@/lib/server-locale'
import { GUEST_ORDER_HISTORY_COOKIE, readGuestOrderHistory } from '@/lib/guest-order-history'

export default async function AccountOrderPage({ params }: { params: Promise<{ orderId: string }> }) {
  const [{ orderId }, locale, cookieStore] = await Promise.all([params, getLocale(), cookies()])
  let matchesSavedOrder = false
  const saved = cookieStore.get('eldokan_order_recovery')?.value
  if (saved && saved.length < 5000 && /^ord_[a-f0-9]{64}$/.test(orderId)) {
    try {
      const state = JSON.parse(Buffer.from(decodeURIComponent(saved), 'base64url').toString('utf8'))
      matchesSavedOrder = state?.order_id === orderId
    } catch { /* A malformed recovery cookie does not grant order access. */ }
  }
  const archivedGuestOrder = readGuestOrderHistory(cookieStore.get(GUEST_ORDER_HISTORY_COOKIE)?.value).some((order) => order.order_id === orderId)
  // This chooses the UI only; the recovery BFF still authorizes the actual order read.
  return <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-10 sm:py-14" dir={locale === 'ar' ? 'rtl' : 'ltr'}>
    {matchesSavedOrder || archivedGuestOrder ? <OrderResult key={orderId} expectedOrderId={orderId} /> : <AccountOrderDetail key={orderId} orderId={orderId} />}
  </main>
}
