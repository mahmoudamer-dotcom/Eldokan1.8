'use client'

import OrderProgress from './OrderProgress'
import Money from '@/components/i18n/Money'
import Link from 'next/link'
import { useCallback, useEffect, useState } from 'react'
import type { OrderDetail } from '@eldokan/customer-api-client'
import { paymentIssueCode } from '@/lib/payment-issue'
import { canRecoverPayment, orderStatusLabel, paymentStatusLabel } from '@/lib/order-status'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useCart } from '@/components/cart/CartProvider'
import OrderInvoice from './OrderInvoice'
import { useOrderStatus } from './useOrderStatus'

type Envelope = { success: true; data: OrderDetail }
type PaymentEnvelope = { success: true; data: { requires_redirect: boolean; redirect_url: string | null; retryable: boolean; generation?: number; issue?: string | { code: string; message: string } | null } }
type SafeError = string | { code?: string; message?: string; request_id?: string | null }

function errorLabel(error: SafeError | undefined, fallback: string) {
  if (typeof error === 'string') return error
  return [error?.message ?? fallback, error?.code, error?.request_id].filter(Boolean).join(' · ')
}

export default function OrderResult({ expectedOrderId }: { expectedOrderId?: string } = {}) {
  const { locale } = useLocale()
  const { refresh: refreshCart } = useCart()
  const ar = locale === 'ar'
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState('')
  const [pendingAttempt, setPendingAttempt] = useState(false)
  const [retryGeneration, setRetryGeneration] = useState<number | null>(null)
  const [paymentIssue, setPaymentIssue] = useState<string | null>(null)
  const text = (en: string, arabic: string) => ar ? arabic : en

  const read = useCallback(async (signal: AbortSignal) => {
    const path = expectedOrderId ? `/api/orders/recovery?order_id=${encodeURIComponent(expectedOrderId)}` : '/api/orders/recovery'
    const response = await fetch(path, { signal, cache: 'no-store', headers: { 'x-eldokan-locale': locale } })
    const result = await response.json() as Envelope | { state?: string; error?: SafeError }
    if (!('data' in result) && result.state === 'attempt_pending') {
      if (!signal.aborted) setPendingAttempt(true)
      throw new Error(ar ? 'هناك محاولة شراء محفوظة. استعد نفس المحاولة لمعرفة حالة الطلب.' : 'A purchase attempt is saved. Recover the same attempt to check the order.')
    }
    if (!response.ok || !('data' in result)) throw new Error(errorLabel('error' in result ? result.error : undefined, ar ? 'حالة الطلب غير متاحة حاليًا.' : 'Order status is not available yet.'))
    if (expectedOrderId && result.data.id !== expectedOrderId) throw new Error(ar ? 'الطلب المحفوظ لا يطابق هذه الصفحة.' : 'The saved order does not match this return page.')
    if (!signal.aborted) setPendingAttempt(false)
    return result.data
  }, [locale, ar, expectedOrderId])
  const { order, loading, refreshing, watching, error, refresh } = useOrderStatus(read, busy)
  const savedOrderId = order?.id
  useEffect(() => {
    if (savedOrderId) void refreshCart()
  }, [savedOrderId, refreshCart])

  async function recoverPlacement() {
    if (busy) return
    setBusy(true); setActionError('')
    try {
      const response = await fetch('/api/orders/recovery', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale },
        body: JSON.stringify({ action: 'place' }),
      })
      const result = await response.json() as { data?: { payment?: { requires_redirect?: boolean; redirect_url?: string | null } }; error?: SafeError; code?: string; request_id?: string | null }
      if (!response.ok || !result.data) throw new Error([errorLabel(result.error, text('Order recovery is still unresolved.', 'لم يتأكد مصير الطلب بعد.')), result.code, result.request_id].filter(Boolean).join(' · '))
      if (result.data.payment?.requires_redirect && result.data.payment.redirect_url) {
        const target = new URL(result.data.payment.redirect_url)
        if (target.protocol !== 'https:' || target.hostname !== 'accept.paymob.com') throw new Error(text('The hosted payment destination failed validation.', 'تعذر التحقق من رابط الدفع. الطلب محفوظ.'))
        window.location.assign(target.toString()); return
      }
      await refresh()
    } catch (cause) { setActionError(cause instanceof Error ? cause.message : text('Keep the saved attempt and try recovery again.', 'احتفظ بالمحاولة المحفوظة وحاول استعادتها مرة أخرى.')) }
    finally { setBusy(false) }
  }

  async function recoverPayment(retry = false, generation?: number) {
    if (busy || !order || !canRecoverPayment(order) || (retry && !Number.isInteger(generation))) return
    setBusy(true); setActionError('')
    try {
      const response = await fetch('/api/orders/recovery', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale },
        body: JSON.stringify({ action: 'payment', order_id: order.id, retry, ...(retry ? { expected_generation: generation } : {}) }),
      })
      const result = await response.json() as PaymentEnvelope | { error?: SafeError; code?: string; request_id?: string | null }
      if (!response.ok || !('data' in result)) throw new Error([errorLabel('error' in result ? result.error : undefined, text('Payment status is not available yet.', 'حالة الدفع غير متاحة حاليًا.')), 'code' in result ? result.code : '', 'request_id' in result ? result.request_id : ''].filter(Boolean).join(' · '))
      if (result.data.requires_redirect && result.data.redirect_url) {
        const target = new URL(result.data.redirect_url)
        if (target.protocol !== 'https:' || target.hostname !== 'accept.paymob.com') throw new Error(text('The hosted payment destination failed validation.', 'تعذر التحقق من رابط الدفع. الطلب محفوظ.'))
        window.location.assign(target.toString()); return
      }
      setRetryGeneration(result.data.retryable && Number.isInteger(result.data.generation) ? result.data.generation! : null)
      setPaymentIssue(paymentIssueCode(result.data.issue))
      await refresh()
    } catch (cause) { setActionError(cause instanceof Error ? cause.message : text('Payment status is uncertain. Keep this order and try recovery again.', 'لم تتأكد حالة الدفع. احتفظ بنفس الطلب وحاول استعادة الدفع.')) }
    finally { setBusy(false) }
  }

  if (loading) return <section className="rounded-2xl border bg-card p-8 text-center">{text('Loading order status…', 'جارٍ تحميل حالة الطلب…')}</section>
  if (!order) return <section dir={ar ? 'rtl' : 'ltr'} className="rounded-2xl border bg-card p-8 text-center">
    <h1 className="text-xl font-bold">{pendingAttempt ? text('Recover your purchase', 'استعادة عملية الشراء') : text('Order status unavailable', 'حالة الطلب غير متاحة')}</h1>
    <p role="alert" className="mt-3 text-muted-foreground">{actionError || error || text('The order may still be processing. Do not place another order.', 'قد يكون الطلب قيد المعالجة. لا تنشئ طلبًا آخر.')}</p>
    {pendingAttempt && <button type="button" disabled={busy || refreshing} onClick={() => void recoverPlacement()} className="mt-5 rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-primary-foreground disabled:opacity-50">{busy ? text('Recovering…', 'جارٍ الاستعادة…') : text('Recover the same checkout attempt', 'استعادة نفس محاولة الطلب')}</button>}
    <button type="button" disabled={busy || refreshing} onClick={() => void refresh()} className="ms-3 mt-5 rounded-lg border px-5 py-3 disabled:opacity-50">{text('Refresh status', 'تحديث الحالة')}</button>
  </section>

  const paid = order.payment_status === 'paid'
  const recoverable = canRecoverPayment(order)
  return <section dir={ar ? 'rtl' : 'ltr'} className="mx-auto max-w-3xl rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
    <p role="status" className={paid ? 'font-semibold text-success-foreground' : 'font-semibold text-shop-accent'}>{paid ? text('Payment confirmed by the order service', 'تم تأكيد الدفع من خدمة الطلبات') : `${text('Order saved · ', 'تم حفظ الطلب · ')}${paymentStatusLabel(order.payment_status, locale)}`}</p>
    <h1 className="mt-2 text-2xl font-bold">{text('Order details', 'تفاصيل الطلب')}</h1>
    <dl className="mt-5 grid gap-3 border-b pb-5 text-sm sm:grid-cols-2">
      <div><dt className="text-muted-foreground">{text('Order reference', 'رقم الطلب')}</dt><dd className="break-all font-semibold">{order.id}</dd></div>
      <div><dt className="text-muted-foreground">{text('Order status', 'حالة الطلب')}</dt><dd className="font-semibold">{orderStatusLabel(order.status, locale)}</dd></div>
      <div><dt className="text-muted-foreground">{text('Payment status', 'حالة الدفع')}</dt><dd className="font-semibold">{paymentStatusLabel(order.payment_status, locale)}</dd></div>
      <div><dt className="text-muted-foreground">{text('Total', 'الإجمالي')}</dt><dd className="font-semibold"><Money value={order.total} /></dd></div>
    </dl>
    {watching && <p role="status" className="mt-4 rounded-lg bg-shop-soft p-3 text-sm text-shop-accent">{text('Checking for payment confirmation automatically…', 'جارٍ متابعة تأكيد الدفع تلقائيًا…')}</p>}
    <OrderProgress status={order.status} fulfillments={order.fulfillments} />
    <OrderInvoice order={order} />
    <div className="mt-5 flex flex-wrap gap-3 print:hidden">
      {recoverable && <button type="button" disabled={busy || refreshing} onClick={() => void recoverPayment()} className="rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-primary-foreground disabled:opacity-50">{busy ? text('Recovering…', 'جارٍ الاستعادة…') : text('Recover hosted payment', 'استعادة الدفع')}</button>}
      {recoverable && retryGeneration !== null && <button type="button" disabled={busy || refreshing} onClick={() => { if (window.confirm(text('Start a new payment session for this same order?', 'بدء جلسة دفع جديدة لنفس الطلب؟'))) void recoverPayment(true, retryGeneration) }} className="rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{text('Confirm payment retry', 'تأكيد إعادة محاولة الدفع')}</button>}
      <button type="button" disabled={busy || refreshing} onClick={() => void refresh()} className="rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{refreshing ? text('Refreshing…', 'جارٍ التحديث…') : text('Refresh status', 'تحديث الحالة')}</button>
      <Link href="/" className="rounded-lg border px-5 py-3 font-semibold">{text('Continue shopping', 'متابعة التسوق')}</Link>
    </div>
    {(actionError || error) && <p role="alert" className="mt-4 text-sm text-danger-foreground">{actionError || error}</p>}
    {recoverable && paymentIssue && <p role="status" className="mt-4 text-sm text-shop-accent">{text('Payment needs follow-up on this same order.', 'الدفع يحتاج متابعة على نفس الطلب.')} <span dir="ltr">{paymentIssue}</span></p>}
  </section>
}
