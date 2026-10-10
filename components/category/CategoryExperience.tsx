import Image from 'next/image'
import Link from 'next/link'
import { ArrowDown, ArrowUpRight, Cpu, Sparkles, Shirt, Hand, House, Wrench, Compass } from 'lucide-react'
import type { CategoryDetail } from '@eldokan/customer-api-client'
import type { StoreProduct } from '@/components/productCard/ProductCard'
import DiscoveryPrompt from '@/components/discovery/DiscoveryPrompt'
import Money from '@/components/i18n/Money'
import { translateCatalogName, type Locale } from '@/lib/i18n'
import { categoryWorldCopy, type CategoryWorld } from '@/lib/category-experience'

const icons = { technology: Cpu, beauty: Sparkles, fashion: Shirt, handmade: Hand, home: House, tools: Wrench, explore: Compass }

export default function CategoryExperience({ world, category, name, slug, products, locale }: {
  world: CategoryWorld; category?: CategoryDetail | null; name: string; slug: string; products: StoreProduct[]; locale: Locale
}) {
  const ar = locale === 'ar'
  const copy = categoryWorldCopy[world]
  const pick = (value: [string, string]) => value[ar ? 1 : 0]
  const Icon = icons[world]
  const showcase = products.filter((product) => product.id && (product.image?.url || product.images?.length)).slice(0, 3)
  return <div className={`category-world category-world--${world}`}>
    <nav aria-label={ar ? 'مسار التصفح' : 'Breadcrumb'} className="mb-5 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
      <Link href="/" className="hover:text-foreground hover:underline">{ar ? 'الرئيسية' : 'Home'}</Link><span aria-hidden="true">/</span><span aria-current="page" className="font-semibold text-foreground">{translateCatalogName(name, locale)}</span>
    </nav>
    <section aria-labelledby="category-world-title" className="category-world-hero">
      <div className="category-world-copy">
        <p className="category-world-eyebrow"><Icon aria-hidden="true" className="size-4 shrink-0" />{pick(copy.eyebrow)}</p>
        <h1 id="category-world-title" className="category-world-name">{translateCatalogName(name, locale)}</h1>
        <p className="category-world-heading">{pick(copy.heading)}</p>
        <p className="category-world-description">{pick(copy.description)}</p>
        <div className="mt-6 flex flex-wrap items-center gap-3">
          <a href="#category-products" className="category-world-browse">{ar ? 'تصفح المنتجات' : 'Browse products'}<ArrowDown aria-hidden="true" className="size-4" /></a>
          <DiscoveryPrompt locale={locale} category={slug} />
        </div>
      </div>
      <div className="category-world-showcase" aria-label={ar ? 'اختيارات من القسم' : 'Picks from this category'}>
        {showcase.length ? showcase.map((product, index) => {
          const image = product.image?.url ?? (typeof product.images?.[0] === 'string' ? product.images[0] : product.images?.[0]?.url)
          return <Link key={product.id} href={`/product/${encodeURIComponent(String(product.id))}`} className="group category-world-product">
            <div className="category-world-photo">{image && <Image src={image} alt={product.name ?? product.title ?? ''} fill sizes="(max-width: 640px) 45vw, (max-width: 1024px) 30vw, 22vw" priority={index === 0} className="object-contain p-4 transition duration-300 group-hover:scale-105" />}</div>
            <div className="category-world-caption"><span className="line-clamp-2 text-sm font-semibold">{product.name ?? product.title}</span><span className="mt-1 block text-xs font-bold"><Money value={product.pricing?.on_sale ? product.pricing.sale_price ?? product.pricing.regular_price : product.pricing?.regular_price} /></span></div>
          </Link>
        }) : <div className="category-world-symbol" aria-hidden="true"><Icon className="size-24 stroke-1" /></div>}
      </div>
    </section>
    {category?.children.length ? <nav aria-label={ar ? 'الأقسام الفرعية' : 'Subcategories'} className="mt-5 flex gap-3 overflow-x-auto pb-2">
      {category.children.map((child) => <Link key={child.id} href={`/category/${encodeURIComponent(child.slug)}`} className="category-world-child"><span>{translateCatalogName(child.name, locale)}</span><ArrowUpRight aria-hidden="true" className="size-4 shrink-0 rtl:-rotate-90" /></Link>)}
    </nav> : null}
    <div className="category-world-guide"><Icon aria-hidden="true" className="size-4 shrink-0" /><p>{pick(copy.guide)}</p></div>
  </div>
}
