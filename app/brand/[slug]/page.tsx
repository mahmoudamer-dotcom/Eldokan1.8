import Footer from '@/components/footer/Footer'
import ProductCard, { type StoreProduct } from '@/components/productCard/ProductCard'
import { ProductsByBrand } from '@/services/product'
import { Brands } from '@/services/brand'
import T from '@/components/i18n/T'

export default async function BrandPage({
  params,
}: {
  params: Promise<{ slug: string }>
}) {
  const { slug } = await params
  const brandSlug = decodeURIComponent(slug)
  const [response, allBrands] = await Promise.all([ProductsByBrand(brandSlug), Brands()])
  const products: StoreProduct[] = Array.isArray(response) ? response : []
  const brandName = products.find((product) => product.brand?.name)?.brand?.name
    ?? allBrands.find((brand) => brand.slug === brandSlug)?.name
    ?? brandSlug.replace(/[-_]+/g, ' ')

  return (
    <>
      <main className="container mx-auto  px-4 pb-12">
        <section className="py-8">
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]"><T text="Shop by brand" /></p>
          <div className="mt-1 flex flex-wrap items-end justify-between gap-3">
            <h1 className="text-2xl font-bold capitalize text-gray-900 sm:text-3xl">{brandName}</h1>
            <p className="text-sm text-gray-500">{products.length} <T text={products.length === 1 ? 'product' : 'products'} /></p>
          </div>

          {products.length > 0 ? (
            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {products.map((product, index) => (
                <ProductCard key={product.id ?? index} product={product} />
              ))}
            </div>
          ) : (
            <div className="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
              <h2 className="text-lg font-semibold text-gray-900"><T text="No products available yet" /></h2>
              <p className="mt-2 text-sm text-gray-500"><T text="Check back soon for products from this brand." /></p>
            </div>
          )}
        </section>
      </main>
      <Footer />
    </>
  )
}
