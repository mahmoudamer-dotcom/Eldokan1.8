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
