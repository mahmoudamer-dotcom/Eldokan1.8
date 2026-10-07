import Image from 'next/image'
import Link from 'next/link'
import type { HomeCategory } from '@/types/home'
import T from '@/components/i18n/T'
import { translateCatalogName } from '@/lib/i18n'
import { getLocale } from '@/lib/server-locale'

export default async function HomeCategoryShortcuts({ categories }: { categories: HomeCategory[] }) {
  const locale = await getLocale()
  if (categories.length === 0) return null

  return (
    <section aria-labelledby="shop-categories-title" className="space-y-4">
      <div>
        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="Find your next favorite" /></p>
        <h2 id="shop-categories-title" className="mt-1 text-xl font-bold text-foreground sm:text-2xl"><T text="Shop by category" /></h2>
      </div>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
        {categories.slice(0, 12).map((category) => {
          const image = typeof category.image === 'string' ? category.image : category.image?.url

          return (
            <Link
              key={category.id}
              href={`/category/${category.slug || encodeURIComponent(category.name)}`}
              className="group relative isolate flex min-h-32 items-end overflow-hidden rounded-2xl bg-gradient-to-br from-[#fff2c9] to-[#f5d477] dark:from-shop-soft dark:to-card p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:min-h-36"
            >
              {image && (
                <Image
                  src={image}
                  alt=""
                  fill
                  sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 16vw"
                  className="-z-20 object-cover transition duration-300 group-hover:scale-105"
                />
              )}
              {image && <span aria-hidden="true" className="absolute inset-0 -z-10 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />}
              <span className={`line-clamp-2 text-sm font-bold sm:text-base ${image ? 'text-white drop-shadow-sm' : 'text-foreground'}`}>
                {translateCatalogName(category.name, locale)}
              </span>
            </Link>
          )
        })}
      </div>
    </section>
  )
}
