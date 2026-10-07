import Link from 'next/link'
import ProductCard from '@/components/productCard/ProductCard'
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from '@/components/ui/carousel'
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
      {visibleGroups.map(({ category, products }) => (
        <section key={`products-${category.id}`} className="space-y-4" aria-labelledby={`category-products-${category.id}`}>
          <div className="flex items-end justify-between gap-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="Browse this category" /></p>
              <h2 id={`category-products-${category.id}`} className="mt-1 text-lg font-bold text-foreground sm:text-xl">{translateCatalogName(category.name, locale)}</h2>
            </div>
            <Link href={`/category/${category.slug || encodeURIComponent(category.name)}`} className="shrink-0 text-sm font-semibold text-foreground underline decoration-gray-300 underline-offset-4 transition hover:text-shop-accent">
              <T text="View all" />
            </Link>
          </div>
          <Carousel opts={{ align: 'start' }} className="w-full">
            <CarouselContent className="-ms-3">
              {products.map((product, index) => (
                <CarouselItem key={product.id ?? index} className="basis-[68%] ps-3 sm:basis-[42%] md:basis-[30%] lg:basis-[22%] xl:basis-[18%]">
                  <ProductCard product={product} swipeImages={false} />
                </CarouselItem>
              ))}
            </CarouselContent>
            {products.length > 5 && <><CarouselPrevious /><CarouselNext /></>}
          </Carousel>
        </section>
      ))}
    </div>
  )
}
