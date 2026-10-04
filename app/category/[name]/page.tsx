import Footer from '@/components/footer/Footer'
import ProductCard from '@/components/productCard/ProductCard'
import { Products } from '@/services/product'
import { fetchCategoryBySlug } from '@/services/category'
import T from '@/components/i18n/T'

export default async function CategoryPage({
  params,
}: {
  params: Promise<{ name: string }>
}) {
  const { name } = await params
  const categorySlug = decodeURIComponent(name)
  const [response, categoryResponse] = await Promise.all([Products(categorySlug), fetchCategoryBySlug(categorySlug)])
  const categoryName = categoryResponse?.data.name ?? categorySlug
  const products = Array.isArray(response?.data) ? response.data : []
  const availableProducts = products.filter((product: any) => product.stock?.status === 'in_stock')

  return (
    <>
      <main className="container mx-auto  px-4 pb-12">
        <section className="py-8">
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]"><T text="Eldokan marketplace" /></p>
          <div className="mt-1 flex flex-wrap items-end justify-between gap-3">
            <h1 className="text-2xl font-bold capitalize text-gray-900 sm:text-3xl">{categoryName}</h1>
            <p className="text-sm text-gray-500">{availableProducts.length} <T text="products" /></p>
          </div>

          {availableProducts.length > 0 ? (
            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {availableProducts.map((product: any, index: number) => (
                <ProductCard key={product.id ?? index} product={product} />
              ))}
            </div>
          ) : (
            <div className="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
              <h2 className="text-lg font-semibold text-gray-900"><T text="No products available yet" /></h2>
              <p className="mt-2 text-sm text-gray-500"><T text="Check back soon for new finds in this category." /></p>
            </div>
          )}
        </section>
      </main>
      <Footer />
    </>
  )
}
