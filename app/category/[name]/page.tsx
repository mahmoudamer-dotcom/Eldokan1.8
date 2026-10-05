import Footer from '@/components/footer/Footer'
import { Products } from '@/services/product'
import { fetchCategoryBySlug, fetchCategoryFilters } from '@/services/category'
import T from '@/components/i18n/T'
import type { StoreProduct } from '@/components/productCard/ProductCard'
import CategoryProducts from '@/components/category/CategoryProducts'

export default async function CategoryPage({
  params,
}: {
  params: Promise<{ name: string }>
}) {
  const { name } = await params
  const categorySlug = decodeURIComponent(name)
  const [response, categoryResponse, filterResponse] = await Promise.all([
    Products(categorySlug, { stockStatus: 'in_stock' }),
    fetchCategoryBySlug(categorySlug),
    fetchCategoryFilters(categorySlug),
  ])
  const categoryName = categoryResponse?.data.name ?? categorySlug
  const products = Array.isArray(response?.data) ? response.data : []
  const availableProducts = products.filter((product: StoreProduct) => product.stock?.status === 'in_stock')

  return (
    <>
      <main className="container mx-auto  px-4 pb-12">
        <section className="py-8">
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]"><T text="Eldokan marketplace" /></p>
          <div className="mt-1 flex flex-wrap items-end justify-between gap-3">
            <h1 className="text-2xl font-bold capitalize text-gray-900 sm:text-3xl">{categoryName}</h1>
          </div>

          {availableProducts.length > 0 ? (
            <CategoryProducts
              category={categorySlug}
              products={availableProducts}
              initialTotalPages={response?.meta?.total_pages ?? 1}
              filters={filterResponse?.data.attributes ?? []}
            />
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
