import { notFound } from 'next/navigation'
import Link from 'next/link'
import { Star, Store } from 'lucide-react'
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
        <nav aria-label={translate('Breadcrumb', locale)} className="flex items-center gap-2 py-5 text-sm text-muted-foreground">
          <Link href="/" className="transition hover:text-foreground"><T text="Home" /></Link>
          <span aria-hidden="true">/</span>
          <span className="font-medium text-foreground">{seller.name}</span>
        </nav>

        <section className="mb-8 flex flex-wrap items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-7">
          <span className="grid size-14 shrink-0 place-items-center rounded-full bg-shop-soft text-shop-accent">
            <Store className="size-7" aria-hidden="true" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="Seller" /></p>
            <h1 className="mt-1 text-2xl font-bold text-foreground">{seller.name}</h1>
            <div className="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground">
              {Number.isFinite(rating) && ratingCount > 0 ? (
                <>
                  <Star className="size-4 fill-amber-400 text-amber-400" aria-hidden="true" />
                  <span className="font-semibold text-foreground">{rating.toFixed(1)}</span>
                  <span>({ratingCount} <T text={ratingCount === 1 ? 'rating' : 'ratings'} />)</span>
                </>
              ) : (
                <T text="No seller ratings yet" />
              )}
            </div>
          </div>
          <p className="rounded-full bg-muted px-3 py-1.5 text-sm font-medium text-muted-foreground">
            {products.length} <T text={products.length === 1 ? 'product' : 'products'} />
          </p>
        </section>

        <section aria-labelledby="seller-products-title">
          <div className="mb-5">
            <h2 id="seller-products-title" className="text-xl font-bold text-foreground sm:text-2xl"><T text="Products from" /> {seller.name}</h2>
          </div>
          {products.length > 0 ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {products.map((product, index) => (
                <ProductCard key={product.id ?? index} product={product} />
              ))}
            </div>
          ) : (
            <p className="rounded-xl border border-dashed border-input bg-card px-5 py-10 text-center text-sm text-muted-foreground">
              <T text="This seller has no products available right now." />
            </p>
          )}
        </section>
      </main>
    </>
  )
}
