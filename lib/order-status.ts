import type { OrderDetail, OrderStatus, PaymentStatus } from '@eldokan/customer-api-client'

const orderLabels: Record<OrderStatus, [string, string]> = {
  pending_payment: ['Awaiting payment', 'بانتظار الدفع'],
  processing: ['Processing', 'جارٍ تجهيز الطلب'],
  awaiting_pickup: ['Ready for pickup', 'جاهز للاستلام'],
  shipped: ['Shipped', 'تم الشحن'],
  delivered: ['Delivered', 'تم التوصيل'],
  cancelled: ['Cancelled', 'ملغي'],
  failed: ['Failed', 'تعذر إكمال الطلب'],
  refunded: ['Refunded', 'تم رد المبلغ'],
}

const paymentLabels: Record<PaymentStatus, [string, string]> = {
  unpaid: ['Unpaid', 'لم يتم الدفع'],
  pending: ['Awaiting confirmation', 'بانتظار تأكيد الدفع'],
  paid: ['Paid', 'تم الدفع'],
  failed: ['Payment failed', 'لم تنجح عملية الدفع'],
  cancelled: ['Payment cancelled', 'تم إلغاء الدفع'],
  refunded: ['Refunded', 'تم رد المبلغ'],
}

export function orderStatusLabel(status: OrderStatus, locale: string) {
  return orderLabels[status]?.[locale === 'ar' ? 1 : 0] ?? status
}

export function paymentStatusLabel(status: PaymentStatus, locale: string) {
  return paymentLabels[status]?.[locale === 'ar' ? 1 : 0] ?? status
}

export function canStartNewCheckout(order: OrderDetail) {
  // A confirmed, readable order is separate from a new purchase, including
  // an unpaid order. Retain its recovery access before retiring the active
  // checkout cookie. Unresolved attempts without an order remain protected.
  return /^ord_[a-f0-9]{64}$/.test(order.id)
}

export function canRecoverPayment(order: OrderDetail) {
  return order.payment_method === 'paymob'
    && !['paid', 'refunded'].includes(order.payment_status)
    && !['cancelled', 'refunded'].includes(order.status)
}

export function shouldWatchPayment(order: OrderDetail | null) {
  return !!order && order.payment_method === 'paymob'
    && ['unpaid', 'pending'].includes(order.payment_status)
    && ['pending_payment', 'processing'].includes(order.status)
}
