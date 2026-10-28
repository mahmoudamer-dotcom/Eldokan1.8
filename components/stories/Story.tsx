'use client'

import { formatMoney } from '@/lib/format-money'
import type { Locale } from '@/lib/i18n'
import Image from 'next/image'
import Link from 'next/link'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { ChevronLeft, ChevronRight, Pause, Play, ShoppingBag, X } from 'lucide-react'
import type { HomeProduct } from '@/types/home'
import { useLocale } from '@/components/i18n/LocaleProvider'

type SellerStory = {
  sellerId: string
  sellerName: string
  title?: string
  product: HomeProduct
}

export type ManagedStory = { id: string; title: string; image: { url: string; alt: string } | null; product: HomeProduct }

/** How long each story stays on screen before moving to the next one. */
const STORY_SECONDS = 5
const SEEN_KEY = 'eldokan_seen_stories'

function getImage(product: HomeProduct) {
  if (product.image?.url) return product.image.url
  const image = product.images?.[0]
  return typeof image === 'string' ? image : image?.url ?? undefined
}

function prices(product: HomeProduct, locale: Locale) {
  const sale = product.pricing?.on_sale && product.pricing.sale_price?.formatted ? product.pricing.sale_price : null
  const regular = product.pricing?.regular_price
  return {
    current: sale ? formatMoney(sale, locale) : regular ? formatMoney(regular, locale) : null,
    was: sale && regular ? formatMoney(regular, locale) : null,
  }
}

function readSeen(): Set<string> {
  try { return new Set(JSON.parse(localStorage.getItem(SEEN_KEY) ?? '[]') as string[]) } catch { return new Set() }
}

function Avatar({ name, image, seen, size = 'md' }: { name: string; image?: string; seen: boolean; size?: 'sm' | 'md' }) {
  const box = size === 'sm' ? 'size-9' : 'size-10'
  return (
    <span className={`${box} grid shrink-0 place-items-center rounded-full p-[2.5px] ${seen ? 'bg-white/60' : 'bg-gradient-to-tr from-[#f5b400] via-[#ef7540] to-[#d73565]'}`}>
      <span className="relative grid size-full place-items-center overflow-hidden rounded-full border-2 border-white bg-gray-900 text-sm font-bold uppercase text-white">
        {image ? <Image src={image} alt="" fill sizes="40px" className="object-cover" /> : name.trim().charAt(0)}
      </span>
    </span>
  )
}

export default function Story({ products, managedStories }: { products: HomeProduct[]; managedStories?: ManagedStory[] | null }) {
  const { t, locale } = useLocale()
  const [open, setOpen] = useState<{ stories: SellerStory[]; index: number } | null>(null)
  const [seen, setSeen] = useState<Set<string>>(() => new Set())
  useEffect(() => { queueMicrotask(() => setSeen(readSeen())) }, [])
  const stories = useMemo<SellerStory[]>(() => {
    if (managedStories) return managedStories.map(story => ({
      sellerId: story.id,
      sellerName: story.product.seller?.name || story.title,
      title: story.title,
      product: story.image ? { ...story.product, image: story.image } : story.product,
    }))
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
  }, [products, managedStories])

  const markSeen = useCallback((id: string) => {
    setSeen((current) => {
      if (current.has(id)) return current
      const next = new Set(current).add(id)
      try { localStorage.setItem(SEEN_KEY, JSON.stringify([...next].slice(-100))) } catch {}
      return next
    })
  }, [])

  if (stories.length === 0) return null
  // Unwatched stories first, like a social feed.
  const ordered = [...stories].sort((a, b) => Number(seen.has(a.sellerId)) - Number(seen.has(b.sellerId)))

  return (
    <>
      <section aria-labelledby="seller-stories-title" className="space-y-3">
        <h2 id="seller-stories-title" className="text-lg font-bold text-foreground sm:text-xl">{managedStories ? locale === 'ar' ? 'قصص الدكان' : 'Eldokan stories' : t('Seller stories')}</h2>
        <div className="category-scrollbar-hidden -mx-4 flex snap-x gap-2.5 overflow-x-auto px-4 pb-1 sm:gap-3">
          {ordered.map((story, index) => {
            const image = getImage(story.product)
            const watched = seen.has(story.sellerId)
            return (
              <button key={story.sellerId} type="button" onClick={() => setOpen({ stories: ordered, index })} aria-label={`${t('View')} ${story.sellerName} ${t('featured product')}`} className="group relative aspect-[9/16] w-[108px] shrink-0 snap-start overflow-hidden rounded-2xl bg-gray-900 text-start shadow-sm ring-1 ring-black/5 sm:w-[124px]">
                {image
                  ? <Image src={image} alt="" fill sizes="124px" className="object-cover transition duration-300 group-hover:scale-105" />
                  : <span className="absolute inset-0 grid place-items-center"><ShoppingBag className="size-9 text-[#ffd45f]" aria-hidden="true" /></span>}
                <span className="absolute inset-0 bg-gradient-to-b from-black/35 via-transparent to-black/75" aria-hidden="true" />
                <span className="absolute start-2 top-2"><Avatar name={story.sellerName} image={image} seen={watched} size="sm" /></span>
                <span className="absolute inset-x-2 bottom-2 line-clamp-2 text-xs font-semibold leading-4 text-white drop-shadow">{story.title || story.sellerName}</span>
              </button>
            )
          })}
        </div>
      </section>

      {open && <StoryViewer stories={open.stories} startIndex={open.index} onSeen={markSeen} onClose={() => setOpen(null)} />}
    </>
  )
}

function StoryViewer({ stories, startIndex, onSeen, onClose }: { stories: SellerStory[]; startIndex: number; onSeen: (id: string) => void; onClose: () => void }) {
  const { t, locale } = useLocale()
  const rtl = locale === 'ar'
  const [index, setIndex] = useState(startIndex)
  const [paused, setPaused] = useState(false)
  const [held, setHeld] = useState(false)
  const press = useRef<{ x: number; at: number } | null>(null)
  const closeRef = useRef<HTMLButtonElement>(null)
  const story = stories[index]
  const image = getImage(story.product)
  const price = prices(story.product, locale)

  const go = useCallback((step: 1 | -1) => {
    setIndex((current) => {
      const next = current + step
      if (next >= stories.length) { queueMicrotask(onClose); return current }
      return Math.max(0, next)
    })
  }, [stories.length, onClose])

  useEffect(() => { onSeen(story.sellerId) }, [story.sellerId, onSeen])
  useEffect(() => {
    closeRef.current?.focus()
    const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => { document.body.style.overflow = overflow }
  }, [])
  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') onClose()
      else if (event.key === 'ArrowRight') go(rtl ? -1 : 1)
      else if (event.key === 'ArrowLeft') go(rtl ? 1 : -1)
      else if (event.key === ' ') { event.preventDefault(); setPaused((value) => !value) }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [go, onClose, rtl])

  // Tap the start third to go back and the rest to go forward; hold to pause; swipe sideways to switch.
  function onPointerDown(event: React.PointerEvent) {
    press.current = { x: event.clientX, at: Date.now() }
    setHeld(true)
  }
  function onPointerUp(event: React.PointerEvent) {
    const start = press.current
    press.current = null
    setHeld(false)
    if (!start) return
    const dx = event.clientX - start.x
    if (Math.abs(dx) > 50) { go((dx < 0) !== rtl ? 1 : -1); return }
    if (Date.now() - start.at > 250) return
    const box = event.currentTarget.getBoundingClientRect()
    const fromStart = rtl ? box.right - event.clientX : event.clientX - box.left
    go(fromStart < box.width / 3 ? -1 : 1)
  }

  const running = !paused && !held
  const Prev = rtl ? ChevronRight : ChevronLeft
  const Next = rtl ? ChevronLeft : ChevronRight

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 sm:p-4" role="presentation" onClick={onClose}>
      <button type="button" onClick={(event) => { event.stopPropagation(); go(-1) }} disabled={index === 0} aria-label={t('Previous seller story')} className="me-4 hidden size-11 shrink-0 place-items-center rounded-full bg-white/15 text-white transition hover:bg-white/25 disabled:invisible sm:grid">
        <Prev className="size-6" />
      </button>

      <div role="dialog" aria-modal="true" aria-label={`${story.sellerName} ${t('product story')}`} dir={rtl ? 'rtl' : 'ltr'} onClick={(event) => event.stopPropagation()} className="relative flex h-dvh w-full flex-col overflow-hidden bg-gray-950 text-white sm:aspect-[9/16] sm:h-[min(92dvh,760px)] sm:w-auto sm:rounded-2xl sm:shadow-2xl">
        {image && <Image src={image} alt="" fill sizes="420px" className="scale-110 object-cover opacity-50 blur-2xl" aria-hidden="true" />}
        <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-black/10 to-black/85" aria-hidden="true" />

        <div className="relative z-20 flex gap-1 px-3 pt-3" aria-label={`${t('Story')} ${index + 1} ${t('of')} ${stories.length}`}>
          {stories.map((item, position) => (
            <span key={item.sellerId} className="h-[3px] flex-1 overflow-hidden rounded-full bg-white/35">
              {position < index && <span className="block h-full w-full bg-white" />}
              {position === index && <span key={index} onAnimationEnd={() => go(1)} className="story-progress block h-full w-full bg-white" style={{ animationDuration: `${STORY_SECONDS}s`, animationPlayState: running ? 'running' : 'paused' }} />}
            </span>
          ))}
        </div>

        <div className="relative z-20 flex items-center gap-2.5 px-3 pt-3">
          <Avatar name={story.sellerName} image={image} seen={false} size="sm" />
          <div className="min-w-0 flex-1">
            {story.product.seller?.id
              ? <Link href={`/seller/${story.product.seller.id}`} onClick={onClose} className="block truncate text-sm font-semibold hover:underline">{story.sellerName}</Link>
              : <span className="block truncate text-sm font-semibold">{story.sellerName}</span>}
            {story.title && story.title !== story.sellerName && <p className="truncate text-xs text-white/75">{story.title}</p>}
          </div>
          <button type="button" onClick={() => setPaused((value) => !value)} aria-label={paused ? (rtl ? 'تشغيل' : 'Play') : (rtl ? 'إيقاف مؤقت' : 'Pause')} className="grid size-9 place-items-center rounded-full transition hover:bg-white/15">
            {paused ? <Play className="size-5" /> : <Pause className="size-5" />}
          </button>
          <button ref={closeRef} type="button" onClick={onClose} aria-label={t('Close story')} className="grid size-9 place-items-center rounded-full transition hover:bg-white/15">
            <X className="size-6" />
          </button>
        </div>

        <div className="relative z-10 min-h-0 flex-1 touch-pan-y select-none" onPointerDown={onPointerDown} onPointerUp={onPointerUp} onPointerCancel={() => { press.current = null; setHeld(false) }} onContextMenu={(event) => event.preventDefault()}>
          {image
            ? <Image key={story.sellerId} src={image} alt={story.product.name} fill sizes="(max-width: 640px) 100vw, 420px" draggable={false} className="pointer-events-none object-contain p-4" />
            : <span className="absolute inset-0 grid place-items-center"><ShoppingBag className="size-24 text-[#ffd45f]" strokeWidth={1.2} aria-hidden="true" /></span>}
        </div>

        <div className="relative z-20 space-y-3 px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-2">
          <div>
            {price.was && <span className="mb-2 inline-block rounded-full bg-[#f5b400] px-2.5 py-1 text-[10px] font-extrabold tracking-wide text-primary-foreground">{t('ON SALE')}</span>}
            <h3 className="line-clamp-2 text-lg font-extrabold leading-snug">{story.product.name}</h3>
            <p className="mt-1 flex items-baseline gap-2">
              <span className="text-lg font-bold">{price.current ?? t('View product for price')}</span>
              {price.was && <span className="text-sm text-white/60 line-through">{price.was}</span>}
            </p>
          </div>
          <Link href={`/product/${story.product.id}`} onClick={onClose} className="flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-3.5 text-sm font-bold text-gray-950 transition hover:bg-[#fff3d1]">
            <ShoppingBag className="size-4" aria-hidden="true" />{t('View product')}
          </Link>
        </div>
      </div>

      <button type="button" onClick={(event) => { event.stopPropagation(); go(1) }} aria-label={t('Next seller story')} className="ms-4 hidden size-11 shrink-0 place-items-center rounded-full bg-white/15 text-white transition hover:bg-white/25 sm:grid">
        <Next className="size-6" />
      </button>
    </div>
  )
}
