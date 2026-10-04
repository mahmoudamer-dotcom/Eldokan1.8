'use client'

import Image from 'next/image'
import Link from 'next/link'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { ProductsDetails } from '@/services/productdetails'
import { useLocale } from '@/components/i18n/LocaleProvider'

type ProductImage = { url: string; alt?: string }
const imageRequests = new Map<string, Promise<ProductImage[]>>()

function uniqueImages(images: ProductImage[]) {
  return images.filter((image, index, all) => image.url && all.findIndex((candidate) => candidate.url === image.url) === index)
}

function fetchProductImages(id: string | number) {
  const key = String(id)
  const cached = imageRequests.get(key)
  if (cached) return cached

  const request = ProductsDetails(key, document.documentElement.lang === 'ar' ? 'ar' : 'en')
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

  imageRequests.set(key, request)
  return request
}

export default function ProductImageCarousel({
  productId,
  productName,
  initialImages,
}: {
  productId?: string | number
  productName: string
  initialImages: ProductImage[]
}) {
  const { t } = useLocale()
  const [images, setImages] = useState(() => uniqueImages(initialImages))
  const [activeIndex, setActiveIndex] = useState(0)
  const touchStartX = useRef<number | null>(null)
  const containerRef = useRef<HTMLDivElement>(null)
  const activeImage = images[activeIndex]

  useEffect(() => {
    if (!productId || images.length > 1 || !containerRef.current) return

    const container = containerRef.current
    const loadGallery = async () => {
      const gallery = await fetchProductImages(productId)
      const combined = uniqueImages([...initialImages, ...gallery])
      if (combined.length > 1) setImages(combined)
    }

    if (!('IntersectionObserver' in window)) {
      void loadGallery()
      return
    }

    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        observer.disconnect()
        void loadGallery()
      }
    }, { rootMargin: '160px' })
    observer.observe(container)
    return () => observer.disconnect()
  }, [productId, images.length, initialImages])

  function move(direction: number) {
    setActiveIndex((index) => (index + direction + images.length) % images.length)
  }

  return (
    <div
      ref={containerRef}
      className="group relative aspect-square w-full overflow-hidden rounded-lg bg-gradient-to-br from-gray-50 to-white"
      onTouchStart={(event) => { touchStartX.current = event.touches[0]?.clientX ?? null }}
      onTouchEnd={(event) => {
        const touchEndX = event.changedTouches[0]?.clientX
        if (touchStartX.current === null || touchEndX === undefined || images.length < 2) return
        const distance = touchEndX - touchStartX.current
        if (distance && Math.abs(distance) > 40) move(distance > 0 ? -1 : 1)
        touchStartX.current = null
      }}
    >
      {activeImage ? (
        productId ? (
          <Link href={`/product/${productId}`} aria-label={`${t('View')} ${t(productName)}`} className="absolute inset-0 z-0">
            <Image
              src={activeImage.url}
              key={activeImage.url}
              alt={activeImage.alt || t(productName)}
              fill
              sizes="(max-width: 640px) 68vw, (max-width: 1024px) 42vw, 22vw"
              className="product-card-image-enter object-contain p-4 transition duration-300 group-hover:scale-[1.03] sm:p-5"
            />
          </Link>
        ) : (
          <Image src={activeImage.url} alt={activeImage.alt || t(productName)} fill sizes="(max-width: 640px) 68vw, (max-width: 1024px) 42vw, 22vw" className="object-contain p-4 sm:p-5" />
        )
      ) : (
        <div className="absolute inset-0 flex items-center justify-center text-sm text-gray-400">{t('Image unavailable')}</div>
      )}

      {images.length > 1 && (
        <>
          <button type="button" aria-label={t('Previous product image')} onClick={(event) => { event.preventDefault(); event.stopPropagation(); move(-1) }} className="absolute left-2 top-1/2 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full border border-gray-200 bg-white/95 text-gray-700 opacity-100 shadow-md transition hover:scale-105 hover:bg-white sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
            <ChevronLeft className="size-4" />
          </button>
          <button type="button" aria-label={t('Next product image')} onClick={(event) => { event.preventDefault(); event.stopPropagation(); move(1) }} className="absolute right-2 top-1/2 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full border border-gray-200 bg-white/95 text-gray-700 opacity-100 shadow-md transition hover:scale-105 hover:bg-white sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
            <ChevronRight className="size-4" />
          </button>
          <div className="pointer-events-none absolute inset-x-0 bottom-0 z-10 flex items-center justify-center bg-gradient-to-t from-black/25 to-transparent pb-2 pt-6" aria-live="polite">
            <div className="flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1.5 shadow-sm backdrop-blur-sm">
              {images.map((image, index) => (
                <span key={image.url} className={`h-1.5 rounded-full transition-all ${index === activeIndex ? 'w-4 bg-gray-900' : 'w-1.5 bg-gray-300'}`} />
              ))}
            <span className="ml-1 text-[10px] font-semibold tabular-nums text-gray-600" aria-label={`${t('Image')} ${activeIndex + 1} ${t('of')} ${images.length}`}>{activeIndex + 1}/{images.length}</span>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
