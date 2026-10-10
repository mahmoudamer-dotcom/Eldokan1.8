'use client'

import { useCallback, useEffect, useState } from 'react'
import type { OrderId, ReturnSummary } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'

const reasons = [ ['damaged', 'Damaged item', 'منتج تالف'], ['wrong_item', 'Wrong item', 'منتج مختلف'], ['missing_parts', 'Missing parts', 'أجزاء ناقصة'], ['not_as_described', 'Not as described', 'مش مطابق للوصف'], ['changed_mind', 'Changed my mind', 'غيرت رأيي'], ['other', 'Other', 'سبب آخر'] ]
const states: Record<string, [string, string]> = { requested: ['Awaiting review', 'بانتظار المراجعة'], approved: ['Approved; awaiting collection', 'تمت الموافقة؛ بانتظار الاستلام'], received: ['Received; under inspection', 'تم الاستلام؛ جارٍ الفحص'], closed: ['Return closed', 'تم إغلاق طلب الإرجاع'], rejected: ['Return declined', 'تم رفض الإرجاع'] }

export default function OrderReturns({ orderId, orderStatus }: { orderId: OrderId; orderStatus: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [data, setData] = useState<ReturnSummary | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [selected, setSelected] = useState<Record<number, number>>({})
  const load = useCallback(async () => { const result = await createEldokanApi(locale).commerce.returns(orderId); setData(result.data) }, [locale, orderId])
  useEffect(() => { let active = true; createEldokanApi(locale).commerce.returns(orderId).then(result => { if (active) setData(result.data) }).catch(() => { if (active) setError(ar ? 'تعذر تحميل خدمة الإرجاع. حاول التحديث.' : 'Could not load returns. Please refresh.') }); return () => { active = false } }, [locale, orderId, ar, orderStatus])
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (busy) return
    const fields = new FormData(event.currentTarget)
    const lines = Object.entries(selected).filter(([, quantity]) => quantity > 0).map(([id, quantity]) => ({ item_id: Number(id), quantity }))
    if (!lines.length) { setError(ar ? 'اختار منتجًا للإرجاع.' : 'Select an item to return.'); return }
    setBusy(true); setError('')
    try { const api = createEldokanApi(locale); await api.auth.session(); setData((await api.commerce.requestReturn(orderId, { reason: String(fields.get('reason')), details: String(fields.get('details')), lines })).data) }
    catch { setError(ar ? 'تعذر تأكيد طلب الإرجاع. حدّث الحالة قبل المحاولة مجددًا.' : 'Could not confirm the return. Refresh its status before retrying.') }
    finally { setBusy(false) }
  }
  return <section className="mt-6 rounded-xl border border-border p-4 print:hidden"><div className="flex flex-wrap justify-between gap-3"><h2 className="font-bold">{ar ? 'الإرجاع والاسترداد' : 'Returns & refunds'}</h2><button disabled={busy} className="text-sm underline" onClick={() => { setError(''); void load().catch(() => setError(ar ? 'تعذر التحديث.' : 'Could not refresh.')) }}>{ar ? 'تحديث' : 'Refresh'}</button></div>
    {data?.request ? <div className="mt-4 space-y-2 text-sm"><p role="status" className="font-semibold">{states[data.request.state]?.[ar ? 1 : 0]}</p><p>{reasons.find(reason => reason[0] === data.request?.reason)?.[ar ? 2 : 1]}</p><p className="whitespace-pre-wrap">{data.request.details}</p>{data.request.staff_note && <p className="whitespace-pre-wrap rounded-lg bg-muted p-3">{data.request.staff_note}</p>}<p>{ar ? 'مرجع الإرجاع' : 'Return reference'}: <span dir="ltr">{data.request.id}</span></p></div> : data?.available ? <form className="mt-4 space-y-4" onSubmit={submit}>
      <p className="text-sm text-muted-foreground">{ar ? 'اختار المنتجات والسبب. قبول الطلب والاسترداد يخضعان لمراجعة المتجر وسياسة الإرجاع.' : 'Choose items and a reason. Approval and refunds are subject to store review and the return policy.'}</p>
      {data.items.map(item => <label key={item.item_id} className="flex items-center justify-between gap-3 text-sm"><span>{item.name}</span><select aria-label={`${ar ? 'كمية الإرجاع' : 'Return quantity'}: ${item.name}`} value={selected[item.item_id] ?? 0} onChange={event => setSelected(previous => ({ ...previous, [item.item_id]: Number(event.target.value) }))} className="rounded-lg border bg-background p-2">{Array.from({ length: Math.min(item.quantity, 100) + 1 }, (_, quantity) => <option key={quantity} value={quantity}>{quantity}</option>)}</select></label>)}
      <label className="block text-sm">{ar ? 'سبب الإرجاع' : 'Return reason'}<select name="reason" required className="mt-2 w-full rounded-lg border bg-background p-3"><option value="">{ar ? 'اختار السبب' : 'Choose a reason'}</option>{reasons.map(reason => <option key={reason[0]} value={reason[0]}>{reason[ar ? 2 : 1]}</option>)}</select></label>
      <label className="block text-sm">{ar ? 'تفاصيل إضافية' : 'Additional details'}<textarea name="details" maxLength={500} className="mt-2 w-full rounded-lg border bg-background p-3" /></label><button disabled={busy} className="rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground disabled:opacity-50">{busy ? (ar ? 'جارٍ الإرسال…' : 'Submitting…') : (ar ? 'إرسال طلب الإرجاع' : 'Request return')}</button>
    </form> : data && <p className="mt-3 text-sm text-muted-foreground">{ar ? 'طلب الإرجاع متاح بعد تسجيل توصيل الطلب. للدعم تواصل مع المتجر.' : 'Returns become available after delivery is recorded. Contact the store for help.'}</p>}
    {data && <p className="mt-4 text-sm">{ar ? 'المبلغ المسترد المسجل للطلب' : 'Recorded order refund'}: {Number(data.refunded_amount).toLocaleString(ar ? 'ar-EG' : 'en-EG', { style: 'currency', currency: data.currency })}</p>}
    {error && <p role="alert" className="mt-3 text-sm text-danger-foreground">{error}</p>}
  </section>
}
