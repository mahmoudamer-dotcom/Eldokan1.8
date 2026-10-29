import Image from 'next/image'
import Link from 'next/link'
import { categoryArt, categoryWorldOf } from '@/lib/category-experience'
import ProductCard from '@/components/productCard/ProductCard'
import ProductShelf from '@/components/ui/ProductShelf'
import type { HomeCategory, HomeProduct } from '@/types/home'
import T from '@/components/i18n/T'
import { translateCatalogName } from '@/lib/i18n'
import { getLocale } from '@/lib/server-locale'

export type HomeCategoryProductGroup = {
  category: HomeCategory
  products: HomeProduct[]
}

export default async function HomeCategoryShelves({ groups }: { groups: HomeCategoryProductGroup[] }) {
  const locale = await getLocale()
  const visibleGroups = groups.filter(({ products }) => products.length > 0)
  if (visibleGroups.length === 0) return null

  return (
    <div className="space-y-8">
      {visibleGroups.map(({ category, products }) => {
        const world = categoryWorldOf(category.slug, category.name)
        return (
        <section key={`products-${category.id}`} className="space-y-4" aria-labelledby={`category-products-${category.id}`}>
          <div className={`category-world category-world--${world} category-shelf-head`}>
            <Image src={categoryArt(world, 'small')} alt="" width={480} height={144} unoptimized />
            <div className="min-w-0">
              <p><T text="Browse this category" /></p>
              <h2 id={`category-products-${category.id}`}>{translateCatalogName(category.name, locale)}</h2>
            </div>
            <Link href={`/category/${category.slug || encodeURIComponent(category.name)}`}>
              <T text="View all" />
            </Link>
          </div>
          <ProductShelf>
            {products.map((product, index) => <ProductCard key={product.id ?? index} product={product} swipeImages={false} />)}
          </ProductShelf>
        </section>
        )
      })}
    </div>
  )
}
