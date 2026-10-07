'use client'

import { useCallback, useEffect, useState } from 'react'
import type { OrderDetail } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'

type Envelope = { success: true; data: OrderDetail }
type PaymentEnvelope = { success: true; data: { requires_redirect: boolean; redirect_url: string | null; retryable: boolean; generation?: number; issue?: { code: string; message: string } | null } }
type SafeError = string | { code?: string; message?: string; request_id?: string | null }

function errorLabel(error: SafeError | undefined, fallback: string) {
  if (typeof error === 'string') return error
  return [error?.message ?? fallback, error?.code, error?.request_id].filter(Boolean).join(' · ')
}

export default function OrderResult() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [order, setOrder] = useState<OrderDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [retryGeneration, setRetryGeneration] = useState<number | null>(null)
  const [paymentIssue, setPaymentIssue] = useState<string | null>(null)

  const refresh = useCallback(async () => {
    setLoading(true); setError('')
    try {
      const response = await fetch('/api/orders/recovery', { cache: 'no-store', headers: { 'x-eldokan-locale': locale } })
      const result = await response.json() as Envelope | { error?: SafeError }
      if (!response.ok || !('data' in result)) throw new Error(errorLabel('error' in result ? result.error : undefined, 'Order status is not available yet.'))
      setOrder(result.data)
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Order status is not available yet.') }
    finally { setLoading(false) }
  }, [locale])

  useEffect(() => { queueMicrotask(() => { void refresh() }) }, [refresh])

  async function recoverPayment(retry = false, generation?: number) {
    setBusy(true); setError('')
    try {
      const response = await fetch('/api/orders/recovery', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale },
        body: JSON.stringify({ action: 'payment', retry, ...(retry ? { expected_generation: generation } : {}) }),
      })
      const result = await response.json() as PaymentEnvelope | { error?: SafeError; code?: string; request_id?: string | null }
      if (!response.ok || !('data' in result)) throw new Error([errorLabel('error' in result ? result.error : undefined, 'Payment status is not available yet.'), 'code' in result ? result.code : '', 'request_id' in result ? result.request_id : ''].filter(Boolean).join(' · '))
      if (result.data.requires_redirect && result.data.redirect_url) {
        const target = new URL(result.data.redirect_url)
        if (target.protocol !== 'https:' || target.hostname !== 'accept.paymob.com') throw new Error('The hosted payment destination failed validation.')
        window.location.assign(target.toString()); return
      }
      setRetryGeneration(result.data.retryable && Number.isInteger(result.data.generation) ? result.data.generation! : null)
      setPaymentIssue(result.data.issue?.code ?? null)
      await refresh()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Payment status is uncertain. Keep this order and try recovery again.') }
    finally { setBusy(false) }
  }

  const text = (en: string, arabic: string) => ar ? arabic : en
  if (loading) return <section className="rounded-2xl border bg-card p-8 text-center">{text('Loading order status…', 'جارٍ تحميل حالة الطلب…')}</section>
  if (!order) return <section className="rounded-2xl border bg-card p-8 text-center"><h1 className="text-xl font-bold">{text('Order status unavailable', 'حالة الطلب غير متاحة')}</h1><p className="mt-3 text-muted-foreground">{error || text('The order may still be processing. Do not place another order.', 'قد يكون الطلب قيد المعالجة. لا تنشئ طلبًا آخر.')}</p><button onClick={() => void refresh()} className="mt-5 rounded-lg border px-5 py-3">{text('Recover order', 'استعادة الطلب')}</button></section>

  const paid = order.payment_status === 'paid'
  const orderDate = new Intl.DateTimeFormat(ar ? 'ar-EG' : 'en-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(order.created_at))
  const label = paid ? text('Payment confirmed by the order service', 'تم تأكيد الدفع من خدمة الطلبات') : text('Order saved · payment status: ', 'تم حفظ الطلب · حالة الدفع: ') + order.payment_status
  return <section dir={ar ? 'rtl' : 'ltr'} className="mx-auto max-w-3xl rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
    <p className={paid ? 'font-semibold text-success-foreground' : 'font-semibold text-shop-accent'}>{label}</p>
    <h1 className="mt-2 text-2xl font-bold">{text('Order details', 'تفاصيل الطلب')}</h1>
    <dl className="mt-5 grid gap-3 border-b pb-5 text-sm sm:grid-cols-2">
      <div><dt className="text-muted-foreground">{text('Order reference', 'رقم الطلب')}</dt><dd className="break-all font-semibold">{order.id}</dd></div>
      <div><dt className="text-muted-foreground">{text('Order status', 'حالة الطلب')}</dt><dd className="font-semibold">{order.status}</dd></div>
      <div><dt className="text-muted-foreground">{text('Payment status', 'حالة الدفع')}</dt><dd className="font-semibold">{order.payment_status}</dd></div>
      <div><dt className="text-muted-foreground">{text('Total', 'الإجمالي')}</dt><dd className="font-semibold">{order.total.formatted}</dd></div>
    </dl>
    <section className="invoice-print-root mt-6 space-y-5 rounded-xl border p-5">
      <header className="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
        <div><p className="text-sm text-muted-foreground">{text('ElDokan', 'الدكان')}</p><h2 className="text-xl font-bold">{paid ? text('Paid order invoice', 'فاتورة الطلب المدفوع') : text('Order summary', 'ملخص الطلب')}</h2></div>
        <div className="text-sm"><p><strong>{text('Invoice / Order', 'رقم الفاتورة / الطلب')}:</strong> {order.id}</p><p><strong>{text('Date', 'التاريخ')}:</strong> {orderDate}</p></div>
      </header>
      <div className="grid gap-3 text-sm sm:grid-cols-2">
        <div><h3 className="mb-1 font-bold">{text('Customer', 'بيانات العميل')}</h3><p>{order.address.first_name} {order.address.last_name}</p><p>{order.address.phone}</p><p>{order.address.email}</p></div>
        <div><h3 className="mb-1 font-bold">{text('Delivery address', 'عنوان التوصيل')}</h3><p>{order.address.street_address}{order.address.address_extra ? ` · ${order.address.address_extra}` : ''}</p><p>{order.address.city} · {order.address.state} · {order.address.country}</p><p>{text('Payment', 'الدفع')}: {order.payment_method}</p><p>{text('Status', 'الحالة')}: {order.payment_status}</p></div>
      </div>
      <div className="overflow-x-auto"><table className="w-full border-collapse text-sm"><thead><tr className="border-y text-start"><th className="py-2 text-start">{text('Item', 'المنتج')}</th><th className="py-2 text-end">{text('Qty', 'الكمية')}</th><th className="py-2 text-end">{text('Subtotal', 'الإجمالي الفرعي')}</th><th className="py-2 text-end">{text('Total', 'الإجمالي')}</th></tr></thead><tbody>{order.lines.map((line, index) => <tr key={`${line.product_id}-${index}`} className="border-b"><td className="py-2">{line.name}</td><td className="py-2 text-end">{line.quantity}</td><td className="py-2 text-end">{line.subtotal.formatted}</td><td className="py-2 text-end">{line.total.formatted}</td></tr>)}</tbody></table></div>
      <dl className="ms-auto max-w-sm space-y-1 text-sm">
        <div className="flex justify-between gap-4"><dt>{text('Subtotal', 'المجموع الفرعي')}</dt><dd>{order.subtotal.formatted}</dd></div>
        <div className="flex justify-between gap-4"><dt>{text('Discount', 'الخصم')}</dt><dd>{order.discount.formatted}</dd></div>
        <div className="flex justify-between gap-4"><dt>{text('Shipping', 'الشحن')}</dt><dd>{order.shipping_total.formatted}</dd></div>
        <div className="flex justify-between gap-4"><dt>{text('Tax', 'الضريبة')}</dt><dd>{order.tax.formatted}</dd></div>
        {order.fees.map((fee, index) => <div key={`${fee.name}-${index}`} className="flex justify-between gap-4"><dt>{fee.name}</dt><dd>{fee.total.formatted}</dd></div>)}
        <div className="flex justify-between gap-4 border-t pt-2 text-base font-bold"><dt>{paid ? text('Paid total', 'إجمالي المدفوع') : text('Order total', 'إجمالي الطلب')}</dt><dd>{order.total.formatted}</dd></div>
      </dl>
      {order.order_notes && <p className="text-sm"><strong>{text('Order notes', 'ملاحظات الطلب')}:</strong> {order.order_notes}</p>}
    </section>
    {paid && <button onClick={() => window.print()} className="print:hidden mt-4 rounded-lg bg-[#f5b400] text-primary-foreground px-5 py-3 font-semibold">{text('Print / save invoice', 'طباعة / حفظ الفاتورة')}</button>}
    {!paid && order.payment_method === 'paymob' && <button disabled={busy} onClick={() => void recoverPayment()} className="mt-5 rounded-lg bg-[#f5b400] text-primary-foreground px-5 py-3 font-semibold disabled:opacity-50">{busy ? text('Recovering…', 'جارٍ الاستعادة…') : text('Recover hosted payment', 'استعادة الدفع')}</button>}
    {!paid && order.payment_method === 'paymob' && retryGeneration !== null && <button disabled={busy} onClick={() => { if (window.confirm(text('Start a new payment session for this same order?', 'بدء جلسة دفع جديدة لنفس الطلب؟'))) void recoverPayment(true, retryGeneration) }} className="ms-2 mt-5 rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{text('Confirm payment retry', 'تأكيد إعادة محاولة الدفع')}</button>}
    <button disabled={busy} onClick={() => void refresh()} className="ms-3 mt-5 rounded-lg border px-5 py-3 font-semibold">{text('Refresh status', 'تحديث الحالة')}</button>
    {error && <p role="alert" className="mt-4 text-sm text-danger-foreground">{error}</p>}
    {!paid && paymentIssue && <p role="status" className="mt-4 text-sm text-shop-accent">{text('Payment needs follow-up on this same order.', 'الدفع يحتاج متابعة على نفس الطلب.')} <span dir="ltr">{paymentIssue}</span></p>}
  </section>
}
