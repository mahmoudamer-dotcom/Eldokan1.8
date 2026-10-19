import Image from 'next/image'
import Link from 'next/link'
import T from '@/components/i18n/T'
import { translateCatalogName } from '@/lib/i18n'
import { getLocale } from '@/lib/server-locale'
import { categoryIcon, categoryWorldOf } from '@/lib/category-experience'

type ShortcutCategory = { id: string | number; name: string; slug?: string; image?: string | { url?: string } | null }

export default async function HomeCategoryShortcuts({ categories }: { categories: ShortcutCategory[] }) {
  const locale = await getLocale()
  if (categories.length === 0) return null

  return (
    <section aria-labelledby="shop-categories-title" className="space-y-6">
      <div>
        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="Find your next favorite" /></p>
        <h2 id="shop-categories-title" className="mt-1 text-xl font-bold text-foreground sm:text-2xl"><T text="Shop by category" /></h2>
      </div>
      {/* A row of circles: scrolls sideways on phones, shares the full width equally on wide screens. */}
      <div className="category-circles -mx-4 flex snap-x gap-5 overflow-x-auto px-4 pb-2 pt-1 sm:gap-10 lg:mx-0 lg:grid lg:grid-flow-col lg:auto-cols-fr lg:justify-items-center lg:gap-x-6 lg:overflow-visible lg:px-0">
        {categories.slice(0, 12).map((category) => {
          const world = categoryWorldOf(category.slug, category.name)
          const storeImage = typeof category.image === 'string' ? category.image : category.image?.url

          return (
            <Link
              key={category.id}
              href={`/category/${category.slug || encodeURIComponent(category.name)}`}
              className={`category-world category-world--${world} category-circle group`}
            >
              <span className="category-circle-art">
                {storeImage
                  ? <Image src={storeImage} alt="" fill sizes="112px" />
                  : <Image src={categoryIcon(world)} alt="" width={200} height={200} unoptimized />}
              </span>
              <span className="category-circle-label">{translateCatalogName(category.name, locale)}</span>
            </Link>
          )
        })}
      </div>
    </section>
  )
}
