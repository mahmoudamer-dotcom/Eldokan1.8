import Footer from '@/components/footer/Footer'
import ProductCard, { type StoreProduct } from '@/components/productCard/ProductCard'
import { ProductsByPriceRange } from '@/services/product'
import T from '@/components/i18n/T'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'

function readPrice(value?: string | string[]) {
  const candidate = Array.isArray(value) ? value[0] : value
  if (candidate === undefined || candidate.trim() === '') return undefined
  const amount = Number(candidate)
  return Number.isFinite(amount) && amount >= 0 ? amount : undefined
}

function formatRange(min: number | undefined, max: number | undefined, locale: 'en' | 'ar') {
  const format = (value: number) => value.toLocaleString(locale === 'ar' ? 'ar-EG' : 'en')
  const currency = locale === 'ar' ? 'جنيه' : 'EGP'
  if (min !== undefined && max !== undefined) return `${format(min)}–${format(max)} ${currency}`
  if (max !== undefined) return `${translate('Up to', locale)} ${format(max)} ${currency}`
  if (min !== undefined) return `${format(min)} ${currency} ${translate('and above', locale)}`
  return translate('All prices', locale)
}

export default async function BudgetPage({
  searchParams,
}: {
  searchParams: Promise<{ min?: string | string[]; max?: string | string[] }>
}) {
  const params = await searchParams
  const locale = await getLocale()
  const minPrice = readPrice(params.min)
  const maxPrice = readPrice(params.max)
  const rangeIsValid = minPrice === undefined || maxPrice === undefined || minPrice < maxPrice
  const response = rangeIsValid ? await ProductsByPriceRange(minPrice, maxPrice) : []
  const allProducts: StoreProduct[] = Array.isArray(response) ? response : []
  const products = allProducts.filter((product) => {
    if (product.stock?.status !== 'in_stock') return false
    const price = Number(product.pricing?.on_sale ? product.pricing.sale_price?.amount : product.pricing?.regular_price?.amount)
    if (!Number.isFinite(price)) return false
    return (minPrice === undefined || price >= minPrice) && (maxPrice === undefined || price <= maxPrice)
  })

  return (
    <>
      <main className="container mx-auto min-h-[60vh] px-4 pb-12">
        <section className="py-8">
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]"><T text="Find something in your range" /></p>
          <div className="mt-1 flex flex-wrap items-end justify-between gap-3">
            <h1 className="text-2xl font-bold text-gray-900 sm:text-3xl"><T text="Shop by budget" /></h1>
            <p className="text-sm text-gray-500">{formatRange(minPrice, maxPrice, locale)} · {products.length} <T text="products" /></p>
          </div>

          {!rangeIsValid ? (
            <div className="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
              <h2 className="text-lg font-semibold text-gray-900"><T text="That price range is not valid" /></h2>
              <p className="mt-2 text-sm text-gray-500"><T text="Choose a minimum price lower than the maximum price." /></p>
            </div>
          ) : products.length > 0 ? (
            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {products.map((product, index) => <ProductCard key={product.id ?? index} product={product} />)}
            </div>
          ) : (
            <div className="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
              <h2 className="text-lg font-semibold text-gray-900"><T text="No products in this range yet" /></h2>
              <p className="mt-2 text-sm text-gray-500"><T text="Try another budget to see more products." /></p>
            </div>
          )}
        </section>
      </main>
      <Footer />
    </>
  )
}
