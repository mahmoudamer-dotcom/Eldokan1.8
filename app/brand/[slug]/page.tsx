import ProductCard, { type StoreProduct } from '@/components/productCard/ProductCard'
import { ProductsByBrand } from '@/services/product'
import { Brands } from '@/services/brand'
import T from '@/components/i18n/T'
import { notFound } from 'next/navigation'
import type { Metadata } from 'next'

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const brandSlug = decodeURIComponent((await params).slug)
  const brand = (await Brands()).find((item) => item.slug === brandSlug)
  return { title: brand?.name ?? brandSlug.replace(/[-_]+/g, ' '), ...(brand ? {} : { robots: { index: false, follow: true } }) }
}

export default async function BrandPage({
  params,
}: {
  params: Promise<{ slug: string }>
}) {
  const { slug } = await params
  const brandSlug = decodeURIComponent(slug)
  const [response, allBrands] = await Promise.all([ProductsByBrand(brandSlug), Brands()])
  const products: StoreProduct[] = Array.isArray(response) ? response : []
  // An unknown brand with nothing to show is a wrong link: the 404 page.
  if (products.length === 0 && !allBrands.some((brand) => brand.slug === brandSlug)) notFound()
  const brandName = products.find((product) => product.brand?.name)?.brand?.name
    ?? allBrands.find((brand) => brand.slug === brandSlug)?.name
    ?? brandSlug.replace(/[-_]+/g, ' ')

  return (
    <>
      <main className="container mx-auto  px-4 pb-12">
        <section className="py-8">
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="Shop by brand" /></p>
          <div className="mt-1 flex flex-wrap items-end justify-between gap-3">
            <h1 className="text-2xl font-bold capitalize text-foreground sm:text-3xl">{brandName}</h1>
            <p className="text-sm text-muted-foreground">{products.length} <T text={products.length === 1 ? 'product' : 'products'} /></p>
          </div>

          {products.length > 0 ? (
            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {products.map((product, index) => (
                <ProductCard key={product.id ?? index} product={product} />
              ))}
            </div>
          ) : (
            <div className="mt-6 rounded-2xl border border-dashed border-input bg-card px-6 py-16 text-center">
              <h2 className="text-lg font-semibold text-foreground"><T text="No products available yet" /></h2>
              <p className="mt-2 text-sm text-muted-foreground"><T text="Check back soon for products from this brand." /></p>
            </div>
          )}
        </section>
      </main>
    </>
  )
}
