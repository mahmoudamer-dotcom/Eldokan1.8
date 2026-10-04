import Link from 'next/link'
import { Check, Store } from 'lucide-react'
import type { StoreProduct } from '@/components/productCard/ProductCard'
import T from '@/components/i18n/T'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'

function getAmount(product: StoreProduct) {
  const price = product.pricing?.on_sale ? product.pricing.sale_price : product.pricing?.regular_price
  return price?.amount ?? Number.POSITIVE_INFINITY
}

function getFormattedPrice(product: StoreProduct) {
  return product.pricing?.on_sale
    ? product.pricing.sale_price?.formatted ?? product.pricing.regular_price?.formatted
    : product.pricing?.regular_price?.formatted
}

export default async function ProductOffers({ offers }: { offers: StoreProduct[] }) {
  const locale = await getLocale()
  const t = (text: string) => translate(text, locale)
  if (offers.length === 0) return null
  const orderedOffers = [...offers].sort((a, b) => getAmount(a) - getAmount(b))

  return (
    <section className="mt-10" aria-labelledby="seller-offers-title">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]"><T text="Compare sellers" /></p>
          <h2 id="seller-offers-title" className="mt-1 text-xl font-bold text-gray-900 sm:text-2xl"><T text="Prices from sellers" /></h2>
        </div>
        <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
          {orderedOffers.length} {t(orderedOffers.length === 1 ? 'offer' : 'offers')}
        </span>
      </div>

      <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        {orderedOffers.map((offer, index) => {
          const isBestPrice = index === 0 && Number.isFinite(getAmount(offer))
          const price = getFormattedPrice(offer)

          return (
            <article key={offer.id ?? `${offer.seller?.name ?? 'seller'}-${index}`} className="flex flex-col gap-3 border-b border-gray-100 p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <div className="flex min-w-0 items-center gap-3">
                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-gray-100 text-gray-600">
                  <Store className="size-5" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                  <p className="truncate font-semibold text-gray-900">{offer.seller?.name || <T text="Marketplace seller" />}</p>
                  <p className="mt-0.5 text-xs text-gray-500"><T text={offer.stock?.status === 'in_stock' ? 'In stock' : 'Available from this seller'} /></p>
                </div>
              </div>

              <div className="flex items-center justify-between gap-4 sm:justify-end">
                <div className="text-left sm:text-right">
                  <p className="text-lg font-bold tabular-nums text-gray-950">{price ?? <T text="Price unavailable" />}</p>
                  {offer.pricing?.on_sale && offer.pricing.regular_price?.formatted && (
                    <p className="text-xs tabular-nums text-gray-400 line-through">{offer.pricing.regular_price.formatted}</p>
                  )}
                  {isBestPrice && <span className="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><Check className="size-3.5" /> <T text="Best price" /></span>}
                </div>
                {offer.id != null && (
                  <Link href={`/product/${offer.id}`} className="shrink-0 rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-800 transition hover:border-gray-900 hover:bg-gray-900 hover:text-white">
                    <T text="View offer" />
                  </Link>
                )}
              </div>
            </article>
          )
        })}
      </div>
    </section>
  )
}
