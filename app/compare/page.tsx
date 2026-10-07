import Link from 'next/link'
import type { Metadata } from 'next'
import { getLocale } from '@/lib/server-locale'
import { normalizeComparisonIds } from '@/lib/product-comparison'
import { createEldokanApi } from '@/lib/eldokan-api'
import { retryApiRead } from '@/lib/retry-api-read'
import ComparisonTable from '@/components/productDetails/ComparisonTable'

export const metadata: Metadata = { title: 'Compare products | Eldokan', robots: { index: false, follow: false } }

export default async function ComparePage({ searchParams }: { searchParams: Promise<{ ids?: string | string[] }> }) {
  const query = await searchParams
  const locale = await getLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const ids = normalizeComparisonIds((typeof query.ids === 'string' ? query.ids : '').slice(0, 200).split(','))
  const api = createEldokanApi(locale)
  const results = await Promise.allSettled(ids.map((id) => retryApiRead(() => api.products.get(id as `prd_${number}`, { lang: locale }))))
  const products = results.flatMap((result) => result.status === 'fulfilled' ? [result.value.data] : [])
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto w-full max-w-7xl px-4 py-10">
    <h1 className="text-3xl font-bold">{text('Compare complete product details', 'مقارنة تفاصيل المنتجات كاملة')}</h1>
    <p className="mt-3 text-sm leading-6 text-muted-foreground">{text('Compare specifications, options, warranty, delivery, seller and price side by side.', 'قارن المواصفات والاختيارات والضمان والتوصيل والبائع والسعر جنب بعض.')}</p>
    {products.length !== ids.length && <p role="alert" className="mt-4 rounded-xl bg-shop-soft p-3 text-sm text-shop-accent">{text('Some selected products could not be loaded. Refresh the page or open each product.', 'تعذر تحميل بعض المنتجات المختارة. حدّث الصفحة أو افتح كل منتج.')}</p>}
    {products.length < 2 ? <section className="mt-6 rounded-2xl border border-dashed p-8 text-center"><p>{text('Select at least two products using Compare on a product card or product page.', 'اختار منتجين على الأقل من زر المقارنة في كارت المنتج أو صفحته.')}</p><Link href="/" className="mt-5 inline-flex rounded-xl bg-[#f5b400] text-primary-foreground px-5 py-3 font-semibold">{text('Browse products', 'تصفح المنتجات')}</Link></section> : <div className="mt-8"><ComparisonTable products={products} /></div>}
  </main>
}