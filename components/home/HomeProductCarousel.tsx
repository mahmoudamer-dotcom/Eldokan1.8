import ProductCard from '@/components/productCard/ProductCard'
import ProductShelf from '@/components/ui/ProductShelf'
import type { HomeProduct, HomeSection } from '@/types/home'
import T from '@/components/i18n/T'

function matchesSection(section: HomeSection, terms: string[]) {
  const label = `${section.id} ${section.title ?? ''}`.toLocaleLowerCase()
  return terms.some((term) => label.includes(term))
}

export default function HomeProductCarousel({
  section,
  firstProductSectionId,
  eyebrow,
}: {
  section: HomeSection
  firstProductSectionId?: string
  eyebrow?: string
}) {
  const products = (section.items as HomeProduct[]).filter((product) => product.stock?.status === 'in_stock')
  if (products.length === 0) return null

  return (
    <section id={section.id === firstProductSectionId ? 'products' : undefined} className="space-y-4 scroll-mt-44" aria-labelledby={`${section.id}-title`}>
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text={eyebrow || (matchesSection(section, ['deal', 'offer', 'discount']) ? 'Limited time offers' : matchesSection(section, ['best']) ? 'Customer favorites' : matchesSection(section, ['new', 'arrival']) ? 'Just added' : 'Picked for you')} /></p>
          <h2 id={`${section.id}-title`} className="mt-1 text-xl font-bold text-foreground sm:text-2xl"><T text={section.title ?? section.id} /></h2>
        </div>
        {products.length > 5 && <span className="hidden text-sm text-muted-foreground sm:inline"><T text="Scroll to explore" /></span>}
      </div>
      <ProductShelf>
            {products.map((product, index) => <ProductCard key={product.id ?? index} product={product} swipeImages={false} />)}
          </ProductShelf>
    </section>
  )
}
