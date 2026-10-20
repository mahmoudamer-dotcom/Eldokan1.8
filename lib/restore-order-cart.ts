import type { OrderDetail, ProductId, VariationId } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'

// Orders whose items were already put back, so a reload does not add them twice.
const KEY = 'eldokan:restored-orders'

/** An online payment that was started and abandoned: the order exists but nothing was paid. */
export function isAbandonedOnlinePayment(order: OrderDetail) {
  return order.payment_method === 'paymob'
    && ['unpaid', 'failed'].includes(order.payment_status)
    && !['cancelled', 'refunded'].includes(order.status)
}

function restoredIds(): string[] {
  try { const value = JSON.parse(window.localStorage.getItem(KEY) ?? '[]'); return Array.isArray(value) ? value : [] } catch { return [] }
}

/**
 * WooCommerce empties the cart when an order is created, before the customer
 * pays. When they come back from the payment page without paying, put the
 * order's items back in the cart so they can pay again or choose another
 * method. The unpaid order is left for WooCommerce to cancel (Hold stock).
 * Returns how many lines were added.
 */
export async function restoreOrderToCart(order: OrderDetail, locale: 'ar' | 'en') {
  if (restoredIds().includes(order.id)) return 0
  const api = createEldokanApi(locale)
  await api.cart.get({ lang: locale })
  let added = 0
  for (const line of order.lines) {
    try {
      await api.cart.add({ productId: line.product_id as ProductId, variationId: (line.variation_id ?? undefined) as VariationId | undefined, quantity: line.quantity }, { lang: locale })
      added++
    } catch { /* Out of stock or unavailable now: the rest still go back. */ }
  }
  try { window.localStorage.setItem(KEY, JSON.stringify([...restoredIds(), order.id].slice(-20))) } catch { /* Storage blocked. */ }
  return added
}

/**
 * For pages other than checkout (the cart page): if the order saved in this
 * browser is an abandoned online payment, put its items back and retire the
 * saved checkout so a new purchase can start. Works for guests and accounts.
 * Returns the order whose items were restored, or null.
 */
export async function restoreAbandonedPayment(locale: 'ar' | 'en') {
  try {
    const response = await fetch('/api/orders/recovery', { cache: 'no-store', headers: { 'x-eldokan-locale': locale } })
    if (!response.ok) return null
    const recovery = await response.json() as { success?: boolean; data?: OrderDetail }
    const order = recovery.success ? recovery.data : undefined
    if (!order || !isAbandonedOnlinePayment(order)) return null
    const added = await restoreOrderToCart(order, locale)
    await fetch('/api/orders/recovery', { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale }, body: JSON.stringify({ action: 'clear', expected_order_id: order.id }) })
    return added > 0 ? order : null
  } catch { return null }
}
