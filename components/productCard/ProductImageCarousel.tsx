'use client'

import Image from 'next/image'
import Link from 'next/link'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { ProductsDetails } from '@/services/productdetails'
import { useSwipeCarousel } from '@/lib/use-swipe-carousel'
import { useLocale } from '@/components/i18n/LocaleProvider'

type ProductImage = { url: string; alt?: string }
const imageRequests = new Map<string, Promise<ProductImage[]>>()

function uniqueImages(images: ProductImage[]) {
  return images.filter((image, index, all) => image.url && all.findIndex((candidate) => candidate.url === image.url) === index)
}

function fetchProductImages(id: string | number, locale: 'ar' | 'en') {
  const key = `${locale}:${id}`
  const cached = imageRequests.get(key)
  if (cached) return cached

  const request = ProductsDetails(String(id), locale)
    .then((response) => {
      const gallery = response?.data?.images
      if (!Array.isArray(gallery)) {
        imageRequests.delete(key)
        return []
      }
      const images = uniqueImages(gallery.flatMap((image: unknown) => {
        if (typeof image === 'string') return image ? [{ url: image }] : []
        if (image && typeof image === 'object' && 'url' in image && typeof image.url === 'string') {
          const alt = 'alt' in image && typeof image.alt === 'string' ? image.alt : undefined
          return [{ url: image.url, alt }]
        }
        return []
      }))
      if (images.length === 0) imageRequests.delete(key)
      return images
    })
    .catch(() => {
      imageRequests.delete(key)
      return []
    })

  if (imageRequests.size >= 100) imageRequests.delete(imageRequests.keys().next().value!)
  imageRequests.set(key, request)
  return request
}

export default function ProductImageCarousel({
  productId,
  productName,
  initialImages,
  swipeImages = true,
}: {
  productId?: string | number
  productName: string
  swipeImages?: boolean
  initialImages: ProductImage[]
}) {
  const { t, locale } = useLocale()
  const [images, setImages] = useState(() => uniqueImages(initialImages))
  const { viewportRef, api, selectedIndex: activeIndex, direction } = useSwipeCarousel(images.length, 0, swipeImages)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!swipeImages || !productId || images.length > 1 || !containerRef.current) return

    const container = containerRef.current
    let active = true
    let requested = false
    const loadGallery = async () => {
      if (requested) return
      requested = true
      const gallery = await fetchProductImages(productId, locale)
      const combined = uniqueImages([...initialImages, ...gallery])
      if (active && combined.length > 1) setImages(combined)
    }
    const interact = () => { void loadGallery() }
    container.addEventListener('pointerenter', interact)
    container.addEventListener('pointerdown', interact, { passive: true })
    container.addEventListener('focusin', interact)
    return () => {
      active = false
      container.removeEventListener('pointerenter', interact)
      container.removeEventListener('pointerdown', interact)
      container.removeEventListener('focusin', interact)
    }
  }, [productId, images.length, initialImages, swipeImages, locale])

  function move(direction: number) {
    if (direction > 0) api?.scrollNext()
    else api?.scrollPrev()
  }

  return (
    <div
      ref={containerRef}
      className="group relative aspect-square w-full overflow-hidden rounded-xl product-photo-canvas"
      dir={direction}
    >
      <div ref={viewportRef} data-swipe-viewport={swipeImages && images.length > 1 ? "" : undefined} className="h-full overflow-hidden touch-pan-y touch-pinch-zoom">
        <div className="flex h-full">
          {images.map((activeImage, index) => <div key={activeImage.url} inert={index !== activeIndex} className="relative h-full min-w-0 shrink-0 basis-full">
            {activeImage ? (
              productId ? (
                <Link href={`/product/${productId}`} aria-label={`${t('View')} ${t(productName)}`} className="absolute inset-0 z-0">
                  <Image
                    src={activeImage.url}
                    key={activeImage.url}
                    alt={activeImage.alt || t(productName)}
                    fill
                    sizes={swipeImages ? "(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw" : "(max-width: 640px) 68vw, (max-width: 1024px) 42vw, 22vw"}
                    className="rounded-xl object-contain p-4 transition duration-300 group-hover:scale-[1.03] sm:p-5"
                  />
                </Link>
              ) : (
                <Image src={activeImage.url} alt={activeImage.alt || t(productName)} fill sizes={swipeImages ? "(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw" : "(max-width: 640px) 68vw, (max-width: 1024px) 42vw, 22vw"} className="rounded-xl object-contain p-4 sm:p-5" />
              )
            ) : (
              <div className="absolute inset-0 flex items-center justify-center text-sm text-[#4b5563]">{t('Image unavailable')}</div>
            )}

          </div>)}
          {images.length === 0 && <div className="flex w-full items-center justify-center text-sm text-[#4b5563]">{t('Image unavailable')}</div>}
        </div>
      </div>
      {images.length > 1 && (
        <>
          <button type="button" aria-label={t('Previous product image')} onClick={(event) => { event.preventDefault(); event.stopPropagation(); move(direction === 'rtl' ? 1 : -1) }} className="absolute left-2 top-1/2 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full border border-border bg-card/95 text-foreground opacity-100 shadow-md transition hover:scale-105 hover:bg-card [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:group-focus-within:opacity-100">
            <ChevronLeft className="size-4" />
          </button>
          <button type="button" aria-label={t('Next product image')} onClick={(event) => { event.preventDefault(); event.stopPropagation(); move(direction === 'rtl' ? -1 : 1) }} className="absolute right-2 top-1/2 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full border border-border bg-card/95 text-foreground opacity-100 shadow-md transition hover:scale-105 hover:bg-card [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:group-focus-within:opacity-100">
            <ChevronRight className="size-4" />
          </button>
          <div className="pointer-events-none absolute inset-x-0 bottom-0 z-10 flex items-center justify-center bg-gradient-to-t from-black/25 to-transparent pb-2 pt-6" aria-live="polite">
            <div className="flex items-center gap-1.5 rounded-full bg-card/90 px-2.5 py-1.5 shadow-sm backdrop-blur-sm">
              {images.map((image, index) => (
                <span key={image.url} className={`h-1.5 rounded-full transition-all ${index === activeIndex ? 'w-4 bg-foreground' : 'w-1.5 bg-muted'}`} />
              ))}
            <span className="ms-1 text-[10px] font-semibold tabular-nums text-muted-foreground" aria-label={`${t('Image')} ${activeIndex + 1} ${t('of')} ${images.length}`}>{activeIndex + 1}/{images.length}</span>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
