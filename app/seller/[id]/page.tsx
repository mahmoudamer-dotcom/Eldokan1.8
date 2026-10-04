import { notFound } from 'next/navigation'
import Link from 'next/link'
import { Star, Store } from 'lucide-react'
import Footer from '@/components/footer/Footer'
import ProductCard, { type StoreProduct } from '@/components/productCard/ProductCard'
import { SellerDetails, SellerProducts } from '@/services/seller'
import T from '@/components/i18n/T'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'

export default async function SellerPage({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = await params
  const locale = await getLocale()
  const [seller, sellerProducts] = await Promise.all([
    SellerDetails(id),
    SellerProducts(id),
  ])

  if (!seller) notFound()

  const products = sellerProducts as StoreProduct[]
  const rating = Number(seller.rating)
  const ratingCount = Number(seller.rating_count ?? 0)

  return (
    <>
      <main className="container mx-auto  px-4 pb-12">
        <nav aria-label={translate('Breadcrumb', locale)} className="flex items-center gap-2 py-5 text-sm text-gray-500">
          <Link href="/" className="transition hover:text-gray-900"><T text="Home" /></Link>
          <span aria-hidden="true">/</span>
          <span className="font-medium text-gray-800">{seller.name}</span>
        </nav>

        <section className="mb-8 flex flex-wrap items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
          <span className="grid size-14 shrink-0 place-items-center rounded-full bg-[#fff2c9] text-[#805400]">
            <Store className="size-7" aria-hidden="true" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]"><T text="Seller" /></p>
            <h1 className="mt-1 text-2xl font-bold text-gray-900">{seller.name}</h1>
            <div className="mt-2 flex items-center gap-1.5 text-sm text-gray-600">
              {Number.isFinite(rating) && ratingCount > 0 ? (
                <>
                  <Star className="size-4 fill-amber-400 text-amber-400" aria-hidden="true" />
                  <span className="font-semibold text-gray-800">{rating.toFixed(1)}</span>
                  <span>({ratingCount} <T text={ratingCount === 1 ? 'rating' : 'ratings'} />)</span>
                </>
              ) : (
                <T text="No seller ratings yet" />
              )}
            </div>
          </div>
          <p className="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-600">
            {products.length} <T text={products.length === 1 ? 'product' : 'products'} />
          </p>
        </section>

        <section aria-labelledby="seller-products-title">
          <div className="mb-5">
            <h2 id="seller-products-title" className="text-xl font-bold text-gray-900 sm:text-2xl"><T text="Products from" /> {seller.name}</h2>
          </div>
          {products.length > 0 ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {products.map((product, index) => (
                <ProductCard key={product.id ?? index} product={product} />
              ))}
            </div>
          ) : (
            <p className="rounded-xl border border-dashed border-gray-300 bg-white px-5 py-10 text-center text-sm text-gray-500">
              <T text="This seller has no products available right now." />
            </p>
          )}
        </section>
      </main>
      <Footer />
    </>
  )
}
