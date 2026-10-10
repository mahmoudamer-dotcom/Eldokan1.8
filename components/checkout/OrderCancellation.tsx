'use client'

import { useState } from 'react'
import type { OrderDetail } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'

type Cancellation = { available: boolean; mode: 'direct' | 'request'; state: string | null; reason_code: string | null; details: string; requested_at: string | null }
export default function OrderCancellation({ order, busy, onBusy, onRefresh, initiallyOpen = false }: {
  order: OrderDetail; busy: boolean; onBusy: (value: boolean) => void; onRefresh: () => Promise<void>; initiallyOpen?: boolean
}) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [open, setOpen] = useState(initiallyOpen)
  const [review, setReview] = useState(false)
  const [reason, setReason] = useState('')
  const [details, setDetails] = useState('')
  const [error, setError] = useState('')
  const policy = (order as OrderDetail & { cancellation?: Cancellation }).cancellation
  const reasons: Record<string, string> = {
    changed_mind: ar ? 'غيرت رأيي' : 'Changed my mind', ordered_by_mistake: ar ? 'طلبت بالخطأ' : 'Ordered by mistake',
    delivery_time: ar ? 'مدة التوصيل غير مناسبة' : 'Delivery time', found_better_price: ar ? 'وجدت سعرًا أفضل' : 'Found a better price', other: ar ? 'سبب آخر' : 'Other reason',
  }
  const valid = !!reasons[reason] && (reason !== 'other' || details.trim().length > 0) && [...details].length <= 500

  async function cancel() {
    if (!policy?.available || !valid || !review || busy) return
    onBusy(true); setError('')
    try {
      const session = await createEldokanApi(locale).auth.session()
      const response = await fetch(`/api/customer/orders/${encodeURIComponent(order.id)}/cancel?lang=${locale}`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-ElDokan-CSRF': session.data.csrf_token },
        body: JSON.stringify({ reason_code: reason, details: details.trim() }),
      })
      const result = await response.json() as { success?: boolean; error?: { code?: string; message?: string }; meta?: { request_id?: string } }
      if (!response.ok || !result.success) throw new Error([ar ? 'تعذر تأكيد الإلغاء. حدّث حالة الطلب قبل إعادة المحاولة.' : 'Cancellation could not be confirmed. Refresh order status before retrying.', result.error?.code, result.meta?.request_id].filter(Boolean).join(' · '))
      setOpen(false); setReview(false)
      await onRefresh()
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : (ar ? 'تعذر تأكيد الإلغاء. حدّث حالة الطلب.' : 'Could not confirm cancellation. Refresh order status.'))
      await onRefresh()
    } finally { onBusy(false) }
  }

  if (!policy) return null
  return <section className="mt-5 space-y-3 rounded-xl border border-border bg-card p-4 print:hidden">
    <h2 className="font-semibold">{ar ? 'إلغاء الطلب' : 'Order cancellation'}</h2>
    {policy.state === 'requested' ? <p role="status" className="text-sm leading-6 text-shop-accent">{ar ? 'طلب الإلغاء مسجل وبانتظار مراجعة المتجر. الطلب لم يُلغَ بعد، وأي استرداد للمبلغ يحتاج تأكيدًا منفصلًا.' : 'Your cancellation request is awaiting store review. The order is not cancelled yet; any refund requires separate confirmation.'}</p>
      : policy.state === 'cancelled' ? <p role="status" className="text-sm">{ar ? 'تم إلغاء الطلب.' : 'The order has been cancelled.'}</p>
      : policy.state === 'refunded' ? <p role="status" className="text-sm">{ar ? 'الطلب مسجل كمسترد المبلغ لدى المتجر.' : 'The store has marked this order as refunded.'}</p>
      : policy.state === 'rejected' ? <p role="status" className="text-sm text-muted-foreground">{ar ? 'المتجر لم يوافق على طلب الإلغاء. تواصل مع المتجر لمزيد من التفاصيل.' : 'The store declined your cancellation request. Contact the store for details.'}</p>
      : !policy.available ? <p className="text-sm text-muted-foreground">{ar ? 'الإلغاء عبر الموقع غير متاح في المرحلة الحالية. تواصل مع المتجر لمتابعة الطلب.' : 'Online cancellation is unavailable at this stage. Contact the store for help.'}</p>
      : !open ? <button type="button" disabled={busy} onClick={() => setOpen(true)} className="rounded-lg border border-danger-foreground px-4 py-2 text-sm font-semibold text-danger-foreground disabled:opacity-50">{policy.mode === 'direct' ? (ar ? 'إلغاء الطلب' : 'Cancel order') : (ar ? 'طلب إلغاء الطلب' : 'Request cancellation')}</button>
      : <form onSubmit={(event) => { event.preventDefault(); if (review) void cancel(); else if (valid) setReview(true) }} className="space-y-3">
        {!review ? <>
          <label className="block text-sm font-medium">{ar ? 'سبب الإلغاء' : 'Cancellation reason'}<select required disabled={busy} value={reason} onChange={(event) => setReason(event.target.value)} className="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2"><option value="">{ar ? 'اختار السبب' : 'Choose a reason'}</option>{Object.entries(reasons).map(([id, label]) => <option key={id} value={id}>{label}</option>)}</select></label>
          <label className="block text-sm font-medium">{ar ? (reason === 'other' ? 'وضّح السبب' : 'تفاصيل إضافية (اختياري)') : (reason === 'other' ? 'Explain your reason' : 'Additional details (optional)')}<textarea required={reason === 'other'} disabled={busy} maxLength={500} value={details} onChange={(event) => setDetails(event.target.value)} className="mt-1 min-h-24 w-full rounded-lg border border-input bg-background px-3 py-2" /></label>
        </> : <div className="space-y-2 rounded-lg bg-shop-soft p-3 text-sm"><p className="font-semibold">{ar ? 'راجع قبل التأكيد' : 'Review before confirming'}</p><p>{reasons[reason]}</p>{details.trim() && <p className="whitespace-pre-wrap break-words">{details.trim()}</p>}<p className="leading-6 text-muted-foreground">{policy.mode === 'direct' ? (ar ? 'بعد التأكيد سيُلغى هذا الطلب.' : 'Confirming will cancel this order.') : (ar ? 'سيُرسل طلب الإلغاء للمتجر للمراجعة؛ الإلغاء واسترداد المبلغ لا يحدثان فورًا.' : 'The store will review your request. Cancellation and refund are not immediate.')}</p></div>}
        <div className="flex flex-wrap gap-3"><button type="submit" disabled={busy || !valid} className="rounded-lg bg-foreground px-4 py-2 font-semibold text-background disabled:opacity-50">{busy ? (ar ? 'جارٍ الإرسال…' : 'Submitting…') : review ? (policy.mode === 'direct' ? (ar ? 'تأكيد الإلغاء' : 'Confirm cancellation') : (ar ? 'تأكيد طلب الإلغاء' : 'Confirm cancellation request')) : (ar ? 'مراجعة الإلغاء' : 'Review cancellation')}</button>{review && <button type="button" disabled={busy} onClick={() => setReview(false)} className="rounded-lg border px-4 py-2">{ar ? 'تعديل السبب' : 'Edit reason'}</button>}<button type="button" disabled={busy} onClick={() => { setOpen(false); setReview(false) }} className="rounded-lg border px-4 py-2">{ar ? 'احتفظ بالطلب' : 'Keep order'}</button></div>
      </form>}
    {policy.reason_code && <p className="text-sm text-muted-foreground">{ar ? 'السبب المسجل:' : 'Recorded reason:'} {reasons[policy.reason_code] ?? policy.reason_code}{policy.details ? ` · ${policy.details}` : ''}</p>}
    {error && <p role="alert" className="text-sm text-danger-foreground">{error}</p>}
  </section>
}
