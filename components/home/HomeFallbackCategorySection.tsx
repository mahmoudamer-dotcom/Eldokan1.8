import ProductCard from '@/components/productCard/ProductCard'
import type { HomeProduct } from '@/types/home'
import T from '@/components/i18n/T'

export default function HomeFallbackCategorySection({
  categoryName,
  products,
  isFirst = false,
}: {
  categoryName: string
  products: HomeProduct[]
  isFirst?: boolean
}) {
  if (products.length === 0) return null

  return (
    <section id={isFirst ? 'products' : undefined} className="space-y-4 scroll-mt-44" aria-label={categoryName}>
      <h2 className="text-xl font-bold text-foreground sm:text-2xl"><T text={categoryName} /></h2>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
        {products.slice(0, 10).map((product, index) => <ProductCard key={product.id ?? index} product={product} />)}
      </div>
    </section>
  )
}
