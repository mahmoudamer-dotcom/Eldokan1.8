import { notFound } from 'next/navigation'
import Link from 'next/link'
import ParentImage from '@/components/ParentImage/ParentImage'
import ProductDetails from '@/components/productDetails/ProductDetails'
import { ProductViewTracker } from '@/components/home/RecentlyViewed'
import DescriptionProduct from '@/components/descriptionProduct/DescriptionProduct'
import ProductCard, { type StoreProduct } from '@/components/productCard/ProductCard'
import { ProductsDetails } from '@/services/productdetails'
import { Products } from '@/services/product'
import T from '@/components/i18n/T'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'
import { translateCatalogName } from '@/lib/i18n'
import type { Metadata } from 'next'
import ProductReviews from '@/components/productDetails/ProductReviews'
import ReturnToJourney from '@/components/discovery/ReturnToJourney'

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }): Promise<Metadata> {
  const { id } = await params
  const locale = await getLocale()
  const response = await ProductsDetails(id, locale)
  const product = response.data
  if (!product) return { title: locale === 'ar' ? 'المنتج غير متاح | الدكان' : 'Product unavailable | Eldokan', robots: { index: false, follow: false } }
  const description = `${product.name} · ${product.pricing.on_sale ? product.pricing.sale_price.formatted : product.pricing.regular_price.formatted}`
  return { title: `${product.name} | ${locale === 'ar' ? 'الدكان' : 'Eldokan'}`, description,
    openGraph: { title: product.name, description, type: 'website', images: product.images.slice(0, 1).map((image) => ({ url: image.url, alt: product.name })) },
    twitter: { card: 'summary_large_image', title: product.name, description, images: product.images.slice(0, 1).map((image) => image.url) } }
}

export default async function ProductPage({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = await params
  const locale = await getLocale()
  const response = await ProductsDetails(id, locale)
  const product = response?.data

  if ('unavailable' in response && response.unavailable) throw new Error('Product service temporarily unavailable')

  if (!product?.id) notFound()

  const category = product.categories?.[0]
  const [allProductsResponse, categoryResponse] = await Promise.all([
    Products(),
    category ? Products(category.slug || category.name, { perPage: 48, stockStatus: 'in_stock' }) : Promise.resolve({ data: [] }),
  ])
  const allProducts: StoreProduct[] = Array.isArray(allProductsResponse?.data) ? allProductsResponse.data : []
  const categoryProducts: StoreProduct[] = Array.isArray(categoryResponse?.data) ? categoryResponse.data : []
  const listings = [...new Map<string, StoreProduct>(
    [...allProducts, ...categoryProducts]
      .filter((listing) => listing.id != null)
      .map((listing) => [String(listing.id), listing] as const),
  ).values()]
  const normalizedName = (value?: string) => (value ?? '').trim().toLocaleLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ')
  const normalizedSku = (value?: string) => (value ?? '').trim().toLocaleLowerCase()
  const isSameProduct = (listing: StoreProduct) => {
    if (String(listing.id) === String(product.id)) return true
    if (product.sku && listing.sku) return normalizedSku(product.sku) === normalizedSku(listing.sku)
    return Boolean(normalizedName(product.name) && normalizedName(listing.name ?? listing.title) === normalizedName(product.name))
  }
  const otherSellerOffers = listings.filter((listing) =>
    isSameProduct(listing) && String(listing.id) !== String(product.id),
  )
  const relatedProducts = categoryProducts
    .filter((listing) => listing.stock?.status === 'in_stock' && !isSameProduct(listing))
    .slice(0, 10)

  return (
    <>
      <main className="container mx-auto  px-4 pb-12">
        <ProductViewTracker product={product} />
        <nav aria-label={translate('Breadcrumb', locale)} className="flex flex-wrap items-center gap-2 py-5 text-sm text-muted-foreground">
          <Link href="/" className="transition hover:text-foreground"><T text="Home" /></Link>
          <span aria-hidden="true">/</span>
          {category && (
            <>
              <Link href={`/category/${encodeURIComponent(category.slug || category.name)}`} className="transition hover:text-foreground"><T text={translateCatalogName(category.name, locale)} /></Link>
              <span aria-hidden="true">/</span>
            </>
          )}
          <span className="line-clamp-1 font-medium text-foreground">{product.name}</span>
        </nav>

        <ReturnToJourney />
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(20rem,0.95fr)] lg:gap-8">
          <div className="min-w-0 rounded-2xl border border-border bg-card p-3 sm:p-5">
            <ParentImage data={product} />
          </div>
          <ProductDetails data={product} />
        </div>
<DescriptionProduct data={product} />
        <ProductReviews key={String(product.id)} productId={String(product.id)} />
        {category && (
          <section className="mt-10" aria-labelledby="related-products-title">
            <div className="mb-5">
              <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="More to explore" /></p>
              <h2 id="related-products-title" className="mt-1 text-xl font-bold text-foreground sm:text-2xl"><T text="Related products" /></h2>
              <p className="mt-1 text-sm text-muted-foreground"><T text="More picks from" /> {category?.name ?? <T text="this collection" />}</p>
            </div>
            {relatedProducts.length > 0 ? <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {relatedProducts.map((relatedProduct, index) => <ProductCard key={relatedProduct.id ?? index} product={relatedProduct} />)}
            </div> : <p className="rounded-xl border border-dashed border-input bg-card px-5 py-10 text-center text-sm text-muted-foreground"><T text="No other products are available in this category right now." /></p>}
          </section>
        )}

        

        {otherSellerOffers.length > 0 && (
          <section className="mt-12" aria-labelledby="other-sellers-title">
            <div className="mb-5">
              <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="Compare sellers" /></p>
              <h2 id="other-sellers-title" className="mt-1 text-xl font-bold text-foreground sm:text-2xl"><T text="The same product from other sellers" /></h2>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {otherSellerOffers.map((offer, index) => (
                <ProductCard key={offer.id ?? `${offer.seller?.name ?? 'seller'}-${index}`} product={offer} />
              ))}
            </div>
          </section>
        )}

      </main>
    </>
  )
}
