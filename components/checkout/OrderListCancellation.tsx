'use client'

import { useState } from 'react'
import { EldokanClientError, type OrderDetail, type OrderSummary } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'
import OrderCancellation from './OrderCancellation'

export default function OrderListCancellation({ order, onUpdated }: {
  order: OrderSummary; onUpdated: (updated: OrderDetail) => void
}) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [detail, setDetail] = useState<OrderDetail | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function refresh() {
    setError('')
    try {
      const result = await createEldokanApi(locale).orders.get(order.id, { lang: locale })
      setDetail(result.data)
      onUpdated(result.data)
    } catch (cause) {
      setError([ar ? 'تعذر تحديث حالة الطلب. أعد المحاولة قبل تأكيد الإلغاء.' : 'Could not refresh this order. Retry before confirming cancellation.', cause instanceof EldokanClientError ? cause.code : null, cause instanceof EldokanClientError ? cause.requestId : null].filter(Boolean).join(' · '))
    }
  }

  async function openCancellation() {
    if (busy) return
    setBusy(true)
    try { await refresh() } finally { setBusy(false) }
  }

  if (!detail && !['pending_payment', 'processing'].includes(order.status)) return null
  return <div className="mt-4">
    {!detail ? <button type="button" disabled={busy} onClick={() => void openCancellation()} className="rounded-lg border border-danger-foreground px-4 py-2 text-sm font-semibold text-danger-foreground disabled:opacity-50">{busy ? (ar ? 'جارٍ مراجعة الطلب…' : 'Checking order…') : (ar ? 'إلغاء الطلب' : 'Cancel order')}</button> : <>
      <OrderCancellation key={detail.id} order={detail} busy={busy} onBusy={setBusy} onRefresh={refresh} initiallyOpen />
      {!(detail as OrderDetail & { cancellation?: unknown }).cancellation && <p className="mt-3 text-sm text-muted-foreground">{ar ? 'الإلغاء عبر الموقع غير متاح حاليًا. تواصل مع المتجر.' : 'Online cancellation is currently unavailable. Contact the store.'}</p>}
      <button type="button" disabled={busy} onClick={() => { setDetail(null); setError('') }} className="mt-3 text-sm font-semibold underline disabled:opacity-50">{ar ? 'إغلاق' : 'Close'}</button>
    </>}
    {error && <p role="alert" className="mt-3 text-sm text-danger-foreground">{error}</p>}
  </div>
}
