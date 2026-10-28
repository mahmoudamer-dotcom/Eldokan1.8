import Link from 'next/link'
import { ArrowLeft, ArrowRight, BadgeCheck } from 'lucide-react'
import ProductCard from '@/components/productCard/ProductCard'
import type { HomeProduct } from '@/types/home'
import type { Locale } from '@/lib/i18n'

/** Home shelf for pre-owned items in like-new condition; hidden while none are in stock. */
export default function HomeUsedLikeNew({ products, locale }: { products: HomeProduct[]; locale: Locale }) {
  if (products.length === 0) return null
  const ar = locale === 'ar'
  const Arrow = ar ? ArrowLeft : ArrowRight
  return (
    <section aria-labelledby="used-like-new-title" className="rounded-3xl bg-gradient-to-br from-emerald-50 to-shop-soft p-4 ring-1 ring-emerald-100 sm:p-6 dark:from-emerald-950/40 dark:to-shop-soft dark:ring-emerald-900/50">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-300"><BadgeCheck className="size-4" aria-hidden="true" />{ar ? 'زي الجديد، بسعر أقل' : 'Like new, for less'}</p>
          <h2 id="used-like-new-title" className="mt-1 text-xl font-bold text-foreground sm:text-2xl">{ar ? 'مستعمل كالجديد' : 'Used like new'}</h2>
        </div>
        <Link href="/used-like-new" className="inline-flex items-center gap-1 rounded-full bg-card px-4 py-2 text-sm font-bold text-foreground ring-1 ring-border transition hover:bg-muted">
          {ar ? 'عرض الكل' : 'View all'}<Arrow className="size-4" aria-hidden="true" />
        </Link>
      </div>
      <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
        {products.slice(0, 5).map((product, index) => <ProductCard key={product.id ?? index} product={product} likeNew />)}
      </div>
    </section>
  )
}
