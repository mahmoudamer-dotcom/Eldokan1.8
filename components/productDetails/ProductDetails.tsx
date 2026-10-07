import Link from 'next/link'
import { CircleHelp, Star, Store } from 'lucide-react'
import type { ProductDetailData } from '@/types/product'
import T from '@/components/i18n/T'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'
import { translateCatalogName } from '@/lib/i18n'
import WishlistButton from '@/components/wishlist/WishlistButton'
import ProductPurchaseActions from '@/components/productDetails/ProductPurchaseActions'
import ProductShare from './ProductShare'
import ProductCompareButton from './ProductCompareButton'

export default async function ProductDetails({ data }: { data: ProductDetailData }) {
  const locale = await getLocale()
  const t = (text: string) => translate(text, locale)
  const categories = (data.categories ?? []).map((category) => category.name)
  const salePrice = data.pricing.sale_price
  const regularPrice = data.pricing.regular_price
  const sellerRating = Number(data.seller?.rating)
  const sellerRatingCount = Number(data.seller?.rating_count ?? 0)
  const discount = data.pricing.on_sale && regularPrice.amount > 0
    ? Math.round(((regularPrice.amount - salePrice.amount) / regularPrice.amount) * 100)
    : null

  return (
    <section className="min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-7">
      <div className="flex items-start justify-between gap-3 [&>div]:min-w-0 [&>button]:shrink-0">
        <div>
          {data.brand?.name && <p className="text-sm font-semibold text-shop-accent">{data.brand.name}</p>}
          <h1 className="mt-2 break-words text-2xl font-semibold leading-tight text-foreground sm:text-3xl">{data.name}</h1>
          {data.sku && <p className="mt-3 text-xs text-muted-foreground">SKU: {data.sku}</p>}
        </div>
        <WishlistButton productId={data.id} className="p-2.5" />
      </div>

      <a href="#product-reviews" className="mt-4 inline-flex items-center gap-2 text-sm text-muted-foreground hover:underline">
        <Star className="size-4 text-amber-500" aria-hidden="true" />
        {Number(data.rating_count) > 0 && Number.isFinite(data.average_rating)
          ? `${Number(data.average_rating).toFixed(1)} / 5 · ${data.rating_count} ${locale === 'ar' ? 'تقييم من المتجر' : 'store ratings'}`
          : locale === 'ar' ? 'التقييمات والتعليقات' : 'Ratings & reviews'}
      </a>
      {data.id && <ProductShare productId={String(data.id)} name={data.name} />}
      {data.id && <ProductCompareButton productId={String(data.id)} />}

      <div className="mt-6 border-y border-border py-5">
        <div className="flex flex-wrap items-center gap-3">
          <span className="text-2xl font-bold tracking-tight sm:text-3xl text-foreground">
            {data.pricing.on_sale ? salePrice.formatted : regularPrice.formatted}
          </span>
          {data.pricing.on_sale && <span className="text-base text-muted-foreground line-through">{regularPrice.formatted}</span>}
          {discount !== null && <span className="rounded-md bg-shop-soft px-2 py-1 text-sm font-semibold text-shop-accent"><T text="Save" /> {discount}%</span>}
        </div>
        <p className="mt-2 text-xs text-muted-foreground"><T text="Current price from Eldokan marketplace." /></p>
      </div>

      {data.stock?.status && (
        <p className={`mt-5 text-sm font-semibold ${data.stock.status === 'in_stock' ? 'text-success-foreground' : 'text-danger-foreground'}`}>
          <T text={data.stock.status === 'in_stock' ? 'In stock' : 'Currently unavailable'} />
        </p>
      )}

      {data.seller && (
        <section className="mt-5 rounded-xl border border-border bg-background p-4" aria-label={t('Seller information')}>
          <div className="flex items-center gap-3">
            <span className="grid size-10 shrink-0 place-items-center rounded-full bg-card text-muted-foreground shadow-sm">
              <Store className="size-5" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <p className="text-xs font-medium text-muted-foreground"><T text="Sold by" /></p>
              {data.seller.id ? (
                <Link href={`/seller/${encodeURIComponent(data.seller.id)}`} className="block truncate font-semibold text-foreground underline decoration-gray-300 underline-offset-4 hover:decoration-[#d99500]">
                  {data.seller.name || <T text="Marketplace seller" />}
                </Link>
              ) : (
                <p className="truncate font-semibold text-foreground">{data.seller.name || <T text="Marketplace seller" />}</p>
              )}
              <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                {Number.isFinite(sellerRating) && sellerRatingCount > 0 ? (
                  <>
                    <Star className="size-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />
                    <span className="font-semibold text-foreground">{sellerRating.toFixed(1)}</span>
                    <span>({sellerRatingCount} <T text={sellerRatingCount === 1 ? 'rating' : 'ratings'} />)</span>
                  </>
                ) : (
                  <T text="No seller ratings yet" />
                )}
              </div>
            </div>
          </div>
        </section>
      )}

      {categories.length > 0 && (
        <p className="mt-4 text-sm text-muted-foreground">
          <T text="Category" />:{' '}
          <Link href={`/category/${encodeURIComponent(data.categories?.[0]?.slug || categories[0])}`} className="font-medium text-foreground underline decoration-gray-300 underline-offset-4 hover:decoration-[#d99500]">
            <T text={translateCatalogName(categories[0], locale)} />
          </Link>
        </p>
      )}

      {(data.attributes ?? []).filter((attribute) => !attribute.variation).length > 0 && (
        <dl className="mt-5 space-y-3 rounded-xl bg-background p-4">
          {data.attributes.filter((attribute) => !attribute.variation).map((attribute, index) => (
            <div key={`${attribute.name}-${index}`} className="flex flex-wrap gap-x-2 text-sm">
              <dt className="font-semibold text-foreground">{attribute.name}:</dt>
              <dd className="text-muted-foreground">{attribute.options.map((option) => option.name).join(', ')}</dd>
            </div>
          ))}
        </dl>
      )}

      {data.id && <ProductPurchaseActions
        productId={String(data.id)} productType={data.type ?? 'simple'} attributes={data.attributes} variations={data.variations} name={data.name} imageUrl={data.images[0]?.url}
        unitPrice={data.pricing.on_sale ? salePrice.amount : regularPrice.amount}
        available={data.stock?.status !== 'out_of_stock'} stockQuantity={data.stock?.quantity}
      />}
      <p className="mt-5 flex items-center justify-center gap-2 text-xs text-muted-foreground">
        <CircleHelp className="size-4 text-muted-foreground" /> <T text="Product information provided by the seller" />
      </p>
    </section>
  )
}
