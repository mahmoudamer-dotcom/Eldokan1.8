'use client'

import Link from 'next/link'
import { useCallback, useEffect, useState } from 'react'
import { EldokanClientError, type OrderSummary } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function OrderHistory() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [orders, setOrders] = useState<OrderSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [loadingMore, setLoadingMore] = useState(false)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [error, setError] = useState('')

  const loadPage = useCallback(async (nextPage: number, append: boolean) => {
    if (append) setLoadingMore(true)
    else setLoading(true)
    setError('')
    try {
      const result = await createEldokanApi(locale).orders.list({ page: nextPage, perPage: 20, lang: locale })
      setOrders((current) => append ? [...current, ...result.data.items] : result.data.items)
      setPage(result.data.pagination.page)
      setTotalPages(result.data.pagination.total_pages)
    } catch (cause) {
      if (cause instanceof EldokanClientError && cause.status === 401) setError(ar ? 'سجّل الدخول لعرض طلباتك.' : 'Sign in to view your orders.')
      else setError(cause instanceof EldokanClientError
        ? [ar ? 'تعذر تحميل الطلبات حاليًا.' : 'Orders could not be loaded right now.', cause.code, cause.requestId].filter(Boolean).join(' · ')
        : (ar ? 'تعذر تحميل الطلبات حاليًا.' : 'Orders could not be loaded right now.'))
    } finally {
      setLoading(false)
      setLoadingMore(false)
    }
  }, [locale, ar])

  useEffect(() => {
    let active = true
    queueMicrotask(() => { if (active) void loadPage(1, false) })
    return () => { active = false }
  }, [loadPage])

  if (loading) return <p className="rounded-xl border bg-card p-8 text-center">{ar ? 'جارٍ تحميل الطلبات…' : 'Loading orders…'}</p>
  if (error) return <section className="rounded-xl border bg-card p-8 text-center"><p role="alert" className="text-foreground">{error}</p>{error.includes('Sign in') || error.includes('سجّل') ? <Link href="/login" className="mt-4 inline-flex rounded-lg bg-[#f5b400] text-primary-foreground px-5 py-3 font-semibold">{ar ? 'تسجيل الدخول' : 'Sign in'}</Link> : null}</section>
  if (!orders.length) return <section className="rounded-2xl border border-dashed bg-card p-10 text-center"><p className="text-muted-foreground">{ar ? 'لا توجد طلبات بعد.' : 'You have no orders yet.'}<Link href="/" className="ms-2 font-semibold underline">{ar ? 'ابدأ التسوق' : 'Start shopping'}</Link></p></section>

  return <section className="space-y-4">
    {orders.map((order) => <article key={order.id} className="rounded-2xl border border-border bg-card p-5 shadow-sm">
      <div className="flex flex-wrap justify-between gap-2 border-b border-border pb-3">
        <h2 className="font-bold">{ar ? 'طلب' : 'Order'} · <Link href={`/orders/${encodeURIComponent(order.id)}`} className="break-all underline">{order.id}</Link></h2>
        <span className="font-semibold">{order.status} · {order.payment_status}</span>
      </div>
      <div className="flex flex-wrap justify-between gap-3 pt-3 text-sm"><span className="text-muted-foreground">{new Date(order.created_at).toLocaleDateString(ar ? 'ar-EG' : 'en')}</span><strong>{order.total.formatted}</strong></div>
      <p className="mt-2 text-sm text-muted-foreground">{order.item_count} {ar ? 'قطعة' : 'items'} · {order.payment_method}</p>
    </article>)}
    {page < totalPages && <button type="button" disabled={loadingMore} onClick={() => void loadPage(page + 1, true)} className="w-full rounded-lg border px-5 py-3 font-semibold disabled:opacity-50">{loadingMore ? (ar ? 'جارٍ تحميل المزيد…' : 'Loading more…') : (ar ? 'تحميل المزيد' : 'Load more')}</button>}
  </section>
}
