'use client'

import Link from 'next/link'
import { useCallback, useEffect, useState } from 'react'
import { EldokanClientError, type OrderDetail, type OrderId } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function AccountOrderDetail({ orderId }: { orderId: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [order, setOrder] = useState<OrderDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [retryGeneration, setRetryGeneration] = useState<number | null>(null)
  const [paymentIssue, setPaymentIssue] = useState<string | null>(null)
  const text = useCallback((en: string, arabic: string) => ar ? arabic : en, [ar])

  const load = useCallback(async () => {
    setLoading(true); setError('')
    try {
      const api = createEldokanApi(locale)
      await api.auth.session()
      const result = await api.orders.get(orderId as OrderId, { lang: locale })
      setOrder(result.data)
    } catch (cause) {
      setError(cause instanceof EldokanClientError && cause.status === 401
        ? text('Sign in to view this order.', 'سجّل الدخول لعرض هذا الطلب.')
        : cause instanceof EldokanClientError
          ? [text('This order is unavailable.', 'هذا الطلب غير متاح.'), cause.code, cause.requestId].filter(Boolean).join(' · ')
          : text('This order is unavailable.', 'هذا الطلب غير متاح.'))
    } finally { setLoading(false) }
  }, [locale, orderId, text])

  useEffect(() => { queueMicrotask(() => { void load() }) }, [load])

  async function recoverPayment(retry = false) {
    if (busy || !order || (retry && retryGeneration === null)) return
    setBusy(true); setError('')
    try {
      const result = await createEldokanApi(locale).orders.payment(order.id, retry
        ? { retry: true, expected_generation: retryGeneration! }
        : {}, { lang: locale })
      if (result.data.requires_redirect && result.data.redirect_url) {
        const target = new URL(result.data.redirect_url)
        if (target.protocol !== 'https:' || target.hostname !== 'accept.paymob.com') throw new Error('Hosted payment destination failed validation.')
        window.location.assign(target.toString()); return
      }
      setRetryGeneration(result.data.retryable && Number.isInteger(result.data.generation) ? result.data.generation : null)
      setPaymentIssue(result.data.issue?.code ?? null)
      await load()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Payment status is uncertain. Keep this Order and recover it again.') }
    finally { setBusy(false) }
  }

  if (loading) return <p className="rounded-xl border bg-card p-8 text-center">{text('Loading order…', 'جارٍ تحميل الطلب…')}</p>
  if (!order) return <section className="rounded-xl border bg-card p-8 text-center"><p role="alert">{error}</p><Link href="/orders" className="mt-4 inline-flex underline">{text('Back to orders', 'العودة للطلبات')}</Link></section>

  return <section dir={ar ? 'rtl' : 'ltr'} className="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
    <Link href="/orders" className="text-sm font-semibold underline">{text('Back to orders', 'العودة للطلبات')}</Link>
    <h1 className="mt-4 break-all text-2xl font-bold">{text('Order', 'الطلب')} · {order.id}</h1>
    <dl className="mt-5 grid gap-3 border-b pb-5 text-sm sm:grid-cols-2">
      <div><dt className="text-muted-foreground">{text('Created', 'تاريخ الطلب')}</dt><dd>{new Date(order.created_at).toLocaleString(ar ? 'ar-EG' : 'en')}</dd></div>
      <div><dt className="text-muted-foreground">{text('Order status', 'حالة الطلب')}</dt><dd className="font-semibold">{order.status}</dd></div>
      <div><dt className="text-muted-foreground">{text('Payment status', 'حالة الدفع')}</dt><dd className="font-semibold">{order.payment_status}</dd></div>
      <div><dt className="text-muted-foreground">{text('Payment method', 'طريقة الدفع')}</dt><dd>{order.payment_method}</dd></div>
      <div><dt className="text-muted-foreground">{text('Delivery address', 'عنوان التوصيل')}</dt><dd>{order.address.city}, {order.address.state}</dd></div>
      <div><dt className="text-muted-foreground">{text('Total', 'الإجمالي')}</dt><dd className="font-bold">{order.total.formatted}</dd></div>
    </dl>
    <ul className="divide-y">{order.lines.map((line, index) => <li key={`${line.product_id}-${index}`} className="flex justify-between gap-4 py-3 text-sm"><span>{line.name} × {line.quantity}</span><span>{line.total.formatted}</span></li>)}</ul>
    {order.payment_method === 'paymob' && order.payment_status !== 'paid' && <div className="mt-5 flex flex-wrap gap-2"><button disabled={busy} onClick={() => void recoverPayment()} className="rounded-lg bg-[#f5b400] text-primary-foreground px-5 py-3 font-semibold disabled:opacity-50">{busy ? text('Recovering…', 'جارٍ الاستعادة…') : text('Recover payment', 'استعادة الدفع')}</button>{retryGeneration !== null && <button disabled={busy} onClick={() => { if (window.confirm(text('Start a new payment session for this same order?', 'بدء جلسة دفع جديدة لنفس الطلب؟'))) void recoverPayment(true) }} className="rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{text('Confirm payment retry', 'تأكيد إعادة المحاولة')}</button>}</div>}
    {error && <p role="alert" className="mt-4 text-sm text-danger-foreground">{error}</p>}
    {order.payment_status !== 'paid' && paymentIssue && <p role="status" className="mt-4 text-sm text-shop-accent">{text('Payment needs follow-up on this same order.', 'الدفع يحتاج متابعة على نفس الطلب.')} <span dir="ltr">{paymentIssue}</span></p>}
  </section>
}
