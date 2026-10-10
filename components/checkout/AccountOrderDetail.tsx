'use client'
import OrderReturns from './OrderReturns'

import OrderProgress from './OrderProgress'
import Money from '@/components/i18n/Money'
import Link from 'next/link'
import { useCallback, useState } from 'react'
import { EldokanClientError, type OrderId } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { paymentIssueCode } from '@/lib/payment-issue'
import { canRecoverPayment, orderStatusLabel, paymentStatusLabel } from '@/lib/order-status'
import { useLocale } from '@/components/i18n/LocaleProvider'
import OrderInvoice from './OrderInvoice'
import { useOrderStatus } from './useOrderStatus'

export default function AccountOrderDetail({ orderId }: { orderId: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState('')
  const [retryGeneration, setRetryGeneration] = useState<number | null>(null)
  const [paymentIssue, setPaymentIssue] = useState<string | null>(null)
  const text = useCallback((en: string, arabic: string) => ar ? arabic : en, [ar])

  const read = useCallback(async (signal: AbortSignal) => {
    try {
      const api = createEldokanApi(locale)
      await api.auth.session()
      if (signal.aborted) throw new DOMException('Aborted', 'AbortError')
      const result = await api.orders.get(orderId as OrderId, { lang: locale })
      return result.data
    } catch (cause) {
      throw new Error(cause instanceof EldokanClientError && cause.status === 401
        ? text('Sign in to view this order.', 'سجّل الدخول لعرض هذا الطلب.')
        : cause instanceof EldokanClientError
          ? [text('This order is unavailable.', 'هذا الطلب غير متاح.'), cause.code, cause.requestId].filter(Boolean).join(' · ')
          : text('This order is unavailable.', 'هذا الطلب غير متاح.'))
    }
  }, [locale, orderId, text])
  const { order, loading, refreshing, watching, error, refresh } = useOrderStatus(read, busy)

  async function recoverPayment(retry = false) {
    if (busy || !order || !canRecoverPayment(order) || (retry && retryGeneration === null)) return
    setBusy(true); setActionError('')
    try {
      const api = createEldokanApi(locale)
      await api.auth.session()
      const result = await api.orders.payment(order.id, retry
        ? { retry: true, expected_generation: retryGeneration! }
        : {}, { lang: locale })
      if (result.data.requires_redirect && result.data.redirect_url) {
        const target = new URL(result.data.redirect_url)
        if (target.protocol !== 'https:' || target.hostname !== 'accept.paymob.com') throw new Error(text('Hosted payment destination failed validation.', 'تعذر التحقق من رابط الدفع. الطلب محفوظ.'))
        window.location.assign(target.toString()); return
      }
      setRetryGeneration(result.data.retryable && Number.isInteger(result.data.generation) ? result.data.generation : null)
      setPaymentIssue(paymentIssueCode(result.data.issue))
      await refresh()
    } catch (cause) {
      setActionError(cause instanceof EldokanClientError
        ? [text('Payment needs follow-up on this same order.', 'الدفع يحتاج متابعة على نفس الطلب.'), cause.code, cause.requestId].filter(Boolean).join(' · ')
        : cause instanceof Error ? cause.message : text('Payment status is uncertain. Recover this same order.', 'لم تتأكد حالة الدفع. استعد نفس الطلب.'))
    } finally { setBusy(false) }
  }

  if (loading) return <p className="rounded-xl border bg-card p-8 text-center">{text('Loading order…', 'جارٍ تحميل الطلب…')}</p>
  if (!order) return <section className="rounded-xl border bg-card p-8 text-center">
    <p role="alert">{error}</p>
    <button type="button" disabled={refreshing} onClick={() => void refresh()} className="mt-4 rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{text('Refresh status', 'تحديث الحالة')}</button>
    <Link href="/account/orders" className="ms-4 mt-4 inline-flex underline">{text('Back to orders', 'العودة للطلبات')}</Link>
  </section>

  const recoverable = canRecoverPayment(order) && (order as typeof order & { cancellation?: { state: string | null } }).cancellation?.state !== 'requested'
  return <section dir={ar ? 'rtl' : 'ltr'} className="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
    <Link href="/account/orders" className="text-sm font-semibold underline">{text('Back to orders', 'العودة للطلبات')}</Link>
    <h1 className="mt-4 break-all text-2xl font-bold">{text('Order', 'الطلب')} · {order.id}</h1>
    <dl className="mt-5 grid gap-3 border-b pb-5 text-sm sm:grid-cols-2">
      <div><dt className="text-muted-foreground">{text('Created', 'تاريخ الطلب')}</dt><dd>{new Date(order.created_at).toLocaleString(ar ? 'ar-EG' : 'en-EG')}</dd></div>
      <div><dt className="text-muted-foreground">{text('Order status', 'حالة الطلب')}</dt><dd className="font-semibold">{orderStatusLabel(order.status, locale)}</dd></div>
      <div><dt className="text-muted-foreground">{text('Payment status', 'حالة الدفع')}</dt><dd className="font-semibold">{paymentStatusLabel(order.payment_status, locale)}</dd></div>
      <div><dt className="text-muted-foreground">{text('Total', 'الإجمالي')}</dt><dd className="font-bold"><Money value={order.total} /></dd></div>
    </dl>
    {watching && <p role="status" className="mt-4 rounded-lg bg-shop-soft p-3 text-sm text-shop-accent">{text('Checking for payment confirmation automatically…', 'جارٍ متابعة تأكيد الدفع تلقائيًا…')}</p>}
    <OrderProgress status={order.status} fulfillments={order.fulfillments} />
    <OrderInvoice order={order} />
    <OrderReturns orderId={orderId as OrderId} orderStatus={order.status} />
    <div className="mt-5 flex flex-wrap gap-3 print:hidden">
      {recoverable && <button type="button" disabled={busy || refreshing} onClick={() => void recoverPayment()} className="rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-primary-foreground disabled:opacity-50">{busy ? text('Recovering…', 'جارٍ الاستعادة…') : text('Recover payment', 'استعادة الدفع')}</button>}
      {recoverable && retryGeneration !== null && <button type="button" disabled={busy || refreshing} onClick={() => { if (window.confirm(text('Start a new payment session for this same order?', 'بدء جلسة دفع جديدة لنفس الطلب؟'))) void recoverPayment(true) }} className="rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{text('Confirm payment retry', 'تأكيد إعادة المحاولة')}</button>}
      <button type="button" disabled={busy || refreshing} onClick={() => void refresh()} className="rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{refreshing ? text('Refreshing…', 'جارٍ التحديث…') : text('Refresh status', 'تحديث الحالة')}</button>
    </div>
    {(actionError || error) && <p role="alert" className="mt-4 text-sm text-danger-foreground">{actionError || error}</p>}
    {recoverable && paymentIssue && <p role="status" className="mt-4 text-sm text-shop-accent">{text('Payment needs follow-up on this same order.', 'الدفع يحتاج متابعة على نفس الطلب.')} <span dir="ltr">{paymentIssue}</span></p>}
  </section>
}
