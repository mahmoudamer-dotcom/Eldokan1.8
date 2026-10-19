import type { Metadata } from 'next'
import type { OrderDetail } from '@eldokan/customer-api-client'
import Link from 'next/link'
import SharedInvoice from '@/components/checkout/SharedInvoice'
import { getLocale } from '@/lib/server-locale'

const DEFAULT_API_BASE_URL = 'https://www.eldokan.com/wp-json/eldokan-customer/v1'

// Personal data behind a secret link: never indexed, never cached.
export const metadata: Metadata = { title: 'Invoice', robots: { index: false, follow: false } }
export const dynamic = 'force-dynamic'

/** Opened from the QR code on a printed invoice: no sign-in, the link key is the access. */
export default async function SharedInvoicePage({ params, searchParams }: { params: Promise<{ orderId: string }>; searchParams: Promise<{ k?: string | string[] }> }) {
  const [{ orderId }, query, locale] = await Promise.all([params, searchParams, getLocale()])
  const key = Array.isArray(query.k) ? query.k[0] : query.k
  let order: OrderDetail | null = null
  if (/^ord_[a-f0-9]{64}$/.test(orderId) && typeof key === 'string' && /^[a-f0-9]{32}$/.test(key)) {
    const base = (process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL).replace(/\/$/, '')
    try {
      const response = await fetch(`${base}/orders/${orderId}/invoice?k=${key}&lang=${locale}`, { cache: 'no-store', signal: AbortSignal.timeout(15000) })
      const body = await response.json() as { success?: boolean; data?: OrderDetail }
      if (response.ok && body.success && body.data) order = body.data
    } catch { /* Shown as unavailable below. */ }
  }
  const ar = locale === 'ar'
  return <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-8 sm:py-12" dir={ar ? 'rtl' : 'ltr'}>
    {order ? <SharedInvoice order={order} /> : <section className="rounded-2xl border border-border bg-card p-8 text-center">
      <h1 className="text-xl font-bold">{ar ? 'الفاتورة مش متاحة' : 'Invoice unavailable'}</h1>
      <p className="mt-2 text-sm text-muted-foreground">{ar ? 'الرابط غير صحيح أو الفاتورة مش متاحة دلوقتي. امسح الكود تاني أو افتح الطلب من حسابك.' : 'The link is invalid or the invoice is unavailable right now. Scan the code again or open the order from your account.'}</p>
      <Link href="/orders" className="mt-5 inline-flex rounded-lg bg-primary px-5 py-3 font-semibold text-primary-foreground">{ar ? 'طلباتي' : 'My orders'}</Link>
    </section>}
  </main>
}
