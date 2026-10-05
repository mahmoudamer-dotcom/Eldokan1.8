'use client'

import Link from 'next/link'
import { ReceiptText } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { readVerifiedOrderReferences } from '@/lib/order-history'

type VerifiedOrder = {
  status: 'paid'
  invoice: {
    orderId?: number
    transactionId?: number
    paidAt?: string
    amountCents: number
    currency: string
    items: Array<{ id: string | number; name: string; quantity: number }>
  }
}
type OrderEntry = { reference: string; invoice: VerifiedOrder['invoice'] }

export default function OrderHistory() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [orders, setOrders] = useState<OrderEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    const references = readVerifiedOrderReferences()
    Promise.allSettled(references.map(async (reference) => {
      const response = await fetch(`/api/paymob/verify?reference=${encodeURIComponent(reference)}`, { cache: 'no-store' })
      if (!response.ok) throw new Error('Unable to load verified orders.')
      const result = await response.json() as VerifiedOrder
      return { reference, result }
    }))
      .then((results) => {
        if (active) setOrders(results.flatMap((entry) => entry.status === 'fulfilled' && entry.value.result.status === 'paid' && entry.value.result.invoice
          ? [{ reference: entry.value.reference, invoice: entry.value.result.invoice }]
          : []))
      })
      .catch(() => { if (active) setError(ar ? 'تعذر تحميل الطلبات الآن.' : 'Orders could not be loaded right now.') })
      .finally(() => { if (active) setLoading(false) })

    return () => { active = false }
  }, [ar])

  if (loading) return <p className="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-600">{ar ? 'جارٍ تحميل الطلبات…' : 'Loading orders…'}</p>
  if (error) return <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-5 text-red-800">{error}</p>
  if (!orders.length) return <section className="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
    <p className="text-gray-600">{ar ? 'لا توجد طلبات مدفوعة محفوظة على هذا الجهاز.' : 'No paid orders are saved on this device.'}</p>
    <Link href="/" className="mt-5 inline-flex rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-gray-950">{ar ? 'ابدأ التسوق' : 'Start shopping'}</Link>
  </section>

  return <section className="space-y-4">
    {orders.map(({ invoice, reference }, index) => <article key={`${invoice.transactionId ?? invoice.orderId ?? index}`} className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
      <div className="flex flex-wrap justify-between gap-2 border-b border-gray-100 pb-3">
        <h2 className="font-bold">{ar ? 'طلب' : 'Order'} #{invoice.orderId ?? invoice.transactionId ?? index + 1}</h2>
        <span className="font-semibold text-emerald-700">{ar ? 'مدفوع' : 'Paid'}</span>
      </div>
      <ul className="divide-y divide-gray-100">
        {invoice.items.map((item, itemIndex) => <li key={`${item.id}-${itemIndex}`} className="flex justify-between gap-4 py-3 text-sm">
          <span>{item.name} × {item.quantity}</span>
        </li>)}
      </ul>
      <div className="flex flex-wrap justify-between gap-2 border-t border-gray-100 pt-3 text-sm">
        <span className="text-gray-500">{invoice.paidAt ? new Date(invoice.paidAt).toLocaleString(ar ? 'ar-EG' : 'en') : (ar ? 'تاريخ الدفع غير متاح' : 'Payment date unavailable')}</span>
        <strong>{(invoice.amountCents / 100).toLocaleString(ar ? 'ar-EG' : 'en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} {invoice.currency}</strong>
      </div>
      <Link href={`/checkout/result?reference=${encodeURIComponent(reference)}`} className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-[#94630f] hover:underline">
        <ReceiptText className="size-4" aria-hidden="true" />{ar ? 'عرض الفاتورة والتفاصيل' : 'View invoice and details'}
      </Link>
    </article>)}
  </section>
}
