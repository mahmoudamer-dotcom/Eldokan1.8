'use client'

import Image from 'next/image'
import Link from 'next/link'
import { useMemo, useState } from 'react'
import { ChevronLeft, ChevronRight, ShoppingBag, X } from 'lucide-react'
import type { HomeProduct } from '@/types/home'
import { useLocale } from '@/components/i18n/LocaleProvider'

type SellerStory = {
  sellerId: string
  sellerName: string
  product: HomeProduct
}

function getImage(product: HomeProduct) {
  if (product.image?.url) return product.image.url
  const image = product.images?.[0]
  return typeof image === 'string' ? image : image?.url ?? undefined
}

function getPrice(product: HomeProduct, t: (value: string) => string) {
  if (product.pricing?.on_sale && product.pricing.sale_price?.formatted) return product.pricing.sale_price.formatted
  return product.pricing?.regular_price?.formatted ?? t('View product for price')
}

export default function Story({ products }: { products: HomeProduct[] }) {
  const { t } = useLocale()
  const [activeIndex, setActiveIndex] = useState<number | null>(null)
  const stories = useMemo(() => {
    const bySeller = new Map<string, SellerStory>()

    for (const product of products) {
      const sellerId = product.seller?.id
      const sellerName = product.seller?.name
      if (!sellerId || !sellerName || product.stock?.status !== 'in_stock') continue

      const current = bySeller.get(sellerId)
      const isBetterStoryProduct = Boolean(product.pricing?.on_sale && product.pricing.sale_price?.formatted)
      const currentIsOnSale = Boolean(current?.product.pricing?.on_sale && current.product.pricing.sale_price?.formatted)
      if (!current || (isBetterStoryProduct && !currentIsOnSale)) {
        bySeller.set(sellerId, { sellerId, sellerName, product })
      }
    }

    return [...bySeller.values()].slice(0, 10)
  }, [products])

  const activeStory = activeIndex === null ? null : stories[activeIndex]
  if (stories.length === 0) return null

  function showStory(index: number) {
    setActiveIndex((index + stories.length) % stories.length)
  }

  return (
    <>
      <section aria-labelledby="seller-stories-title" className="space-y-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]">{t('Products and offers from our sellers')}</p>
          <h2 id="seller-stories-title" className="mt-1 text-xl font-bold text-gray-900 sm:text-2xl ">{t('Seller stories')}</h2>
        </div>
        <div className="category-scrollbar-hidden flex gap-5 overflow-x-auto pb-2 sm:gap-7">
          {stories.map((story, index) => {
            const image = getImage(story.product)

            return (
            <button key={story.sellerId} type="button" onClick={() => setActiveIndex(index)} aria-label={`${t('View')} ${story.sellerName} ${t('featured product')}`} className="group flex w-[76px] shrink-0 flex-col items-center gap-2 text-center">
                <span className="grid size-[76px] place-items-center rounded-full bg-gradient-to-br from-[#f5b400] via-[#ef7540] to-[#d73565] p-[3px] transition duration-200 group-hover:scale-105">
                  <span className="relative grid size-full place-items-center overflow-hidden rounded-full border-[3px] border-white bg-gray-100">
                    {image ? <Image src={image} alt="" fill sizes="76px" className="object-cover" /> : <ShoppingBag className="size-7 text-[#a66d00]" aria-hidden="true" />}
                  </span>
                </span>
                <span className="w-full truncate text-xs font-medium text-gray-700 group-hover:text-gray-950">{story.sellerName}</span>
              </button>
            )
          })}
        </div>
      </section>

      {activeStory && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm h-screen" role="presentation" onClick={() => setActiveIndex(null)}>
          <button type="button" className="absolute inset-0 cursor-default" aria-label={t('Close story')} />
          <div role="dialog" aria-modal="true" aria-label={`${activeStory.sellerName} ${t('product story')}`} className="relative z-10 flex aspect-[9/16] max-h-[min(82vh,760px)] w-full max-w-[390px] flex-col overflow-hidden rounded-[28px] bg-gray-950 text-white shadow-2xl" onClick={(event) => event.stopPropagation()}>
            <div className="absolute inset-0 bg-gradient-to-b from-black/50 via-transparent to-black/80" />
            <div className="relative z-10 flex items-center gap-1.5 px-4 pt-4" aria-label={`${t('Story')} ${activeIndex! + 1} ${t('of')} ${stories.length}`}>
              {stories.map((story, index) => (
                <span key={story.sellerId} className="h-1 flex-1 overflow-hidden rounded-full bg-white/35">
                  <span className={`block h-full rounded-full ${index === activeIndex ? 'bg-white' : 'bg-transparent'}`} />
                </span>
              ))}
            </div>

            <div className="relative z-10 flex items-center justify-between gap-3 px-4 pt-4">
              <span className="truncate text-sm font-semibold">{activeStory.sellerName}</span>
              <button type="button" onClick={() => setActiveIndex(null)} aria-label={t('Close story')} className="grid size-9 shrink-0 place-items-center rounded-full bg-black/30 transition hover:bg-black/50">
                <X className="size-5" />
              </button>
            </div>

            <div className="relative z-10 flex flex-1 flex-col items-center justify-center px-7 text-center">
              {getImage(activeStory.product) ? (
                <div className="relative size-52 overflow-hidden rounded-2xl bg-white/10 shadow-xl sm:size-64">
                  <Image src={getImage(activeStory.product)!} alt={activeStory.product.name} fill sizes="(max-width: 640px) 52vw, 256px" className="object-contain p-3" />
                </div>
              ) : (
                <ShoppingBag className="size-24 text-[#ffd45f]" strokeWidth={1.2} aria-hidden="true" />
              )}
              {activeStory.product.pricing?.on_sale && activeStory.product.pricing.sale_price?.formatted && (
                <span className="mt-5 rounded-full bg-[#f5b400] px-3 py-1.5 text-[10px] font-extrabold tracking-wide text-gray-950">{t('ON SALE')}</span>
              )}
              <h3 className="mt-4 line-clamp-3 text-xl font-extrabold leading-tight sm:text-2xl">{activeStory.product.name}</h3>
              <p className="mt-2 text-lg font-bold text-white">{getPrice(activeStory.product, t)}</p>
            </div>

            <div className="relative z-10 p-5">
              <Link href={`/product/${activeStory.product.id}`} onClick={() => setActiveIndex(null)} className="flex w-full items-center justify-center rounded-full bg-white px-5 py-3.5 text-sm font-bold text-gray-950 transition hover:bg-[#fff2c9]">
                {t('View product')}
              </Link>
            </div>

            <button type="button" onClick={() => showStory(activeIndex! - 1)} aria-label={t('Previous seller story')} className="absolute left-2 top-1/2 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-black/30 transition hover:bg-black/50">
              <ChevronLeft className="size-5" />
            </button>
            <button type="button" onClick={() => showStory(activeIndex! + 1)} aria-label={t('Next seller story')} className="absolute right-2 top-1/2 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-black/30 transition hover:bg-black/50">
              <ChevronRight className="size-5" />
            </button>
          </div>
        </div>
      )}
    </>
  )
}
