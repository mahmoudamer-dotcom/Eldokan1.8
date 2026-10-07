import Link from 'next/link'
import { Star, Store } from 'lucide-react'
import ProductCard from '@/components/productCard/ProductCard'
import type { HomeProduct } from '@/types/home'
import T from '@/components/i18n/T'

type SellerGroup = {
  id: string
  name: string
  rating?: number | null
  ratingCount?: number | null
  products: HomeProduct[]
}

export default function HomeSellerSpotlight({ products }: { products: HomeProduct[] }) {
  const sellers = new Map<string, SellerGroup>()

  for (const product of products) {
    const seller = product.seller
    if (!seller?.id || !seller.name || product.stock?.status !== 'in_stock') continue

    const group = sellers.get(seller.id) ?? {
      id: seller.id,
      name: seller.name,
      rating: seller.rating,
      ratingCount: seller.rating_count,
      products: [],
    }
    if (!group.products.some((item) => String(item.id) === String(product.id))) group.products.push(product)
    sellers.set(seller.id, group)
  }

  const spotlight = [...sellers.values()].sort((a, b) => b.products.length - a.products.length)[0]
  if (!spotlight) return null

  const rating = Number(spotlight.rating)
  const ratingCount = Number(spotlight.ratingCount ?? 0)
  const featuredProducts = spotlight.products.slice(0, 4)

  return (
    <section className="overflow-hidden rounded-3xl bg-gray-950 p-5 text-white sm:p-7" aria-labelledby="seller-spotlight-title">
      <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.15em] text-[#ffd45f]"><T text="Meet our marketplace sellers" /></p>
          <h2 id="seller-spotlight-title" className="mt-1 text-xl font-bold sm:text-2xl"><T text="Seller spotlight" /></h2>
        </div>
        <Link href={`/seller/${encodeURIComponent(spotlight.id)}`} className="inline-flex items-center gap-2 rounded-full border border-white/25 px-4 py-2 text-sm font-semibold text-white transition hover:bg-card hover:text-foreground">
          <T text="Visit" /> {spotlight.name}
        </Link>
      </div>

      <div className="mb-4 flex flex-wrap items-center gap-3 text-sm text-white/75">
        <span className="inline-flex items-center gap-2 font-semibold text-white"><Store className="size-4 text-[#ffd45f]" aria-hidden="true" />{spotlight.name}</span>
        {Number.isFinite(rating) && ratingCount > 0 && (
          <span className="inline-flex items-center gap-1.5">
            <Star className="size-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />
            {rating.toFixed(1)} ({ratingCount})
          </span>
        )}
        <span>{spotlight.products.length} <T text="products in this selection" /></span>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4">
        {featuredProducts.map((product, index) => <ProductCard key={product.id ?? index} product={product} />)}
      </div>
    </section>
  )
}
