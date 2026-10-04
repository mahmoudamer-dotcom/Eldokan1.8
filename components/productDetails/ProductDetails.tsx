import Link from 'next/link'
import { CircleHelp, Star, Store } from 'lucide-react'
import type { ProductDetailData } from '@/types/product'
import T from '@/components/i18n/T'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'
import { translateCatalogName } from '@/lib/i18n'
import WishlistButton from '@/components/wishlist/WishlistButton'
import AddToCartButton from '@/components/cart/AddToCartButton'
import BuyNowButton from '@/components/productDetails/BuyNowButton'

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
    <section className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
      <div className="flex items-start justify-between gap-4">
        <div>
          {data.brand?.name && <p className="text-sm font-semibold text-[#a66d00]">{data.brand.name}</p>}
          <h1 className="mt-2 text-2xl font-semibold leading-tight text-gray-900 sm:text-3xl">{data.name}</h1>
          {data.sku && <p className="mt-3 text-xs text-gray-500">SKU: {data.sku}</p>}
        </div>
        <WishlistButton productId={data.id} className="p-2.5" />
      </div>

      <div className="mt-6 border-y border-gray-100 py-5">
        <div className="flex flex-wrap items-center gap-3">
          <span className="text-3xl font-bold tracking-tight text-gray-950">
            {data.pricing.on_sale ? salePrice.formatted : regularPrice.formatted}
          </span>
          {data.pricing.on_sale && <span className="text-base text-gray-400 line-through">{regularPrice.formatted}</span>}
          {discount !== null && <span className="rounded-md bg-[#fff2c9] px-2 py-1 text-sm font-semibold text-[#805400]"><T text="Save" /> {discount}%</span>}
        </div>
        <p className="mt-2 text-xs text-gray-500"><T text="Current price from Eldokan marketplace." /></p>
      </div>

      {data.stock?.status && (
        <p className={`mt-5 text-sm font-semibold ${data.stock.status === 'in_stock' ? 'text-emerald-700' : 'text-rose-600'}`}>
          <T text={data.stock.status === 'in_stock' ? 'In stock' : 'Currently unavailable'} />
        </p>
      )}

      {data.seller && (
        <section className="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4" aria-label={t('Seller information')}>
          <div className="flex items-center gap-3">
            <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white text-gray-600 shadow-sm">
              <Store className="size-5" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <p className="text-xs font-medium text-gray-500"><T text="Sold by" /></p>
              {data.seller.id ? (
                <Link href={`/seller/${encodeURIComponent(data.seller.id)}`} className="block truncate font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-[#d99500]">
                  {data.seller.name || <T text="Marketplace seller" />}
                </Link>
              ) : (
                <p className="truncate font-semibold text-gray-900">{data.seller.name || <T text="Marketplace seller" />}</p>
              )}
              <div className="mt-1 flex items-center gap-1.5 text-xs text-gray-600">
                {Number.isFinite(sellerRating) && sellerRatingCount > 0 ? (
                  <>
                    <Star className="size-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />
                    <span className="font-semibold text-gray-800">{sellerRating.toFixed(1)}</span>
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
        <p className="mt-4 text-sm text-gray-600">
          <T text="Category" />:{' '}
          <Link href={`/category/${encodeURIComponent(data.categories?.[0]?.slug || categories[0])}`} className="font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-[#d99500]">
            <T text={translateCatalogName(categories[0], locale)} />
          </Link>
        </p>
      )}

      {(data.attributes ?? []).length > 0 && (
        <dl className="mt-5 space-y-3 rounded-xl bg-gray-50 p-4">
          {data.attributes.map((attribute, index) => (
            <div key={`${attribute.name}-${index}`} className="flex flex-wrap gap-x-2 text-sm">
              <dt className="font-semibold text-gray-700">{attribute.name}:</dt>
              <dd className="text-gray-600">{attribute.options.map((option) => option.name).join(', ')}</dd>
            </div>
          ))}
        </dl>
      )}

      <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
        {data.id ? <BuyNowButton productId={String(data.id)} className="h-12 rounded-full bg-[#f5b400] font-semibold text-gray-950 transition hover:bg-[#e4a600] disabled:opacity-60" /> : <button type="button" disabled className="h-12 rounded-full bg-[#f5b400] font-semibold text-gray-950 opacity-60"><T text="Buy now" /></button>}
        <AddToCartButton product={data.id ? {
          id: data.id,
          name: data.name,
          imageUrl: data.images[0]?.url,
          unitPrice: data.pricing.on_sale ? salePrice.amount : regularPrice.amount,
          available: data.stock?.status !== 'out_of_stock',
        } : undefined} />
      </div>
      <p className="mt-5 flex items-center justify-center gap-2 text-xs text-gray-500">
        <CircleHelp className="size-4 text-gray-400" /> <T text="Product information provided by the seller" />
      </p>
    </section>
  )
}
