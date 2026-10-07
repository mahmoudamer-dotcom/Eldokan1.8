'use client'

import Link from 'next/link'
import Image from 'next/image'
import { useEffect, useState } from 'react'
import type { HomeBrand } from '@/types/home'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { fetchCatalogBrands } from '@/lib/catalog-brands'

function normalizeImageUrl(value: unknown) {
  if (typeof value !== 'string' || !value.trim()) return null

  try {
    const url = new URL(value.trim(), 'https://www.eldokan.com')
    if (url.protocol === 'http:') url.protocol = 'https:'
    return url.protocol === 'https:' || url.protocol === 'data:' ? url.toString() : null
  } catch {
    return null
  }
}

function getBrandImages(brand: HomeBrand) {
  const candidates = [brand.logo, brand.logo_url, brand.image, brand.image_url, brand.thumbnail, brand.thumbnail_url, brand.src, brand.source_url]
  return [...new Set(candidates.map((candidate) => {
    if (typeof candidate === 'string') return normalizeImageUrl(candidate)
    if (candidate && typeof candidate === 'object') {
      const value = candidate as { url?: unknown; src?: unknown; source_url?: unknown }
      return normalizeImageUrl(value.url ?? value.src ?? value.source_url)
    }
    return null
  }).filter((url): url is string => Boolean(url)))]
}

function BrandLogo({ name, images, onUnavailable }: { name: string; images: string[]; onUnavailable: () => void }) {
  const [imageIndex, setImageIndex] = useState(0)
  const [direct, setDirect] = useState(false)
  const [lightLogo, setLightLogo] = useState<boolean | null>(null)
  const image = images[imageIndex]

  if (!image) return null
  const canOptimize = new URL(image).hostname === 'www.eldokan.com'
  const darkCanvas = lightLogo ?? /(?:^|[_-])white(?:[_\-.]|$)/i.test(new URL(image).pathname)

  return (
    <Image
      src={image}
      alt={name}
      width={160}
      height={80}
      loading="lazy"
      unoptimized={direct || !canOptimize}
      decoding="async"
      style={{ backgroundColor: darkCanvas ? '#202124' : '#fff' }}
      onLoad={(event) => {
        // The optimized image is same-origin, so transparent white logos can
        // be detected without changing their actual colors.
        try {
          const canvas = document.createElement('canvas')
          canvas.width = canvas.height = 48
          const context = canvas.getContext('2d', { willReadFrequently: true })
          if (!context) return
          context.drawImage(event.currentTarget, 0, 0, 48, 48)
          const pixels = context.getImageData(0, 0, 48, 48).data
          let opaque = 0, bright = 0, transparent = 0
          for (let pixel = 0; pixel < pixels.length; pixel += 4) {
            if (pixels[pixel + 3] < 32) { transparent++; continue }
            opaque++
            if (Math.min(pixels[pixel], pixels[pixel + 1], pixels[pixel + 2]) > 190) bright++
          }
          setLightLogo(transparent > 230 && opaque > 0 && bright / opaque > 0.75)
        } catch {
          // External CDNs may block canvas reads; retain the filename hint.
        }
      }}
      onError={() => {
        if (canOptimize && !direct) { setDirect(true); return }
        if (imageIndex + 1 < images.length) {
          setImageIndex((index) => index + 1)
          setDirect(false)
          setLightLogo(null)
        }
        else onUnavailable()
      }}
      className="h-full w-full rounded-md object-contain transition duration-200 group-hover:scale-105"
    />
  )
}

export default function HomeBrandSection({ brands: brandItems, title }: { brands: HomeBrand[]; title?: string }) {
  const { t, locale } = useLocale()
  const [catalog, setCatalog] = useState<{ locale: string; brands: HomeBrand[] } | null>(null)
  const [failedImages, setFailedImages] = useState<Set<string>>(() => new Set())
  useEffect(() => {
    let active = true
    let loading = false
    async function refresh() {
      if (!active || loading || document.visibilityState !== 'visible') return
      loading = true
      try {
        const brands = await fetchCatalogBrands(locale)
        if (active) {
          setCatalog({ locale, brands })
          // Retry previously unavailable logos when catalog data is refreshed.
          setFailedImages(new Set())
        }
      } catch {
        // Preserve the displayed catalog during temporary API failures.
      } finally { loading = false }
    }
    void refresh()
    const interval = window.setInterval(() => { void refresh() }, 60_000)
    const onVisible = () => { void refresh() }
    document.addEventListener('visibilitychange', onVisible)
    window.addEventListener('focus', onVisible)
    return () => {
      active = false
      window.clearInterval(interval)
      document.removeEventListener('visibilitychange', onVisible)
      window.removeEventListener('focus', onVisible)
    }
  }, [locale])
  const brands: Array<{ brand: HomeBrand; images: string[] }> = []
  for (const item of catalog?.locale === locale ? catalog.brands : brandItems) {
    const brand = { ...item, name: item.name?.trim(), slug: item.slug?.trim() }
    if (!brand.name || !(brand.slug || brand.id)) continue
    const images = getBrandImages(brand)
    const existing = brands.find((entry) =>
      (brand.id && entry.brand.id === brand.id) ||
      (brand.slug && entry.brand.slug?.toLowerCase() === brand.slug.toLowerCase()),
    )
    if (existing) {
      existing.images = [...new Set([...existing.images, ...images])]
      existing.brand.slug ||= brand.slug
    } else {
      brands.push({ brand, images })
    }
  }

  const visibleBrands = brands.filter(({ brand, images }) =>
    images.length > 0 && !failedImages.has(JSON.stringify([brand.id || brand.slug, images])),
  )
  if (visibleBrands.length === 0) return null

  return (
    <section aria-labelledby="home-brands-title" className="overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-card via-card to-shop-soft p-5 shadow-sm sm:p-7">
      <div className="flex items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent">{t('Discover the names you love')}</p>
          <h2 id="home-brands-title" className="mt-1 text-xl font-bold text-foreground sm:text-2xl">{title ? t(title) : t('Shop by brand')}</h2>
        </div>
      </div>
      <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
        {visibleBrands.map(({ brand, images }) => (
          <Link key={brand.id || brand.slug} href={`/brand/${encodeURIComponent(brand.slug || brand.id)}`} aria-label={`${t('Shop')} ${brand.name}`} title={brand.name} className="group relative flex min-w-0 flex-col items-center gap-3 overflow-hidden rounded-2xl border border-border bg-card p-3 shadow-[0_2px_12px_rgba(15,23,42,0.035)] transition duration-200 hover:-translate-y-0.5 hover:border-shop-accent/30 hover:shadow-md focus-visible:outline-2 focus-visible:outline-amber-500 sm:p-4">
            <span className="absolute inset-x-5 bottom-0 h-px bg-gradient-to-r from-transparent via-amber-200 to-transparent opacity-0 transition group-hover:opacity-100" aria-hidden="true" />
            <span className="flex h-16 w-full items-center justify-center brand-logo-canvas rounded-lg p-2 sm:h-20">
              <BrandLogo key={images.join('|')} name={brand.name} images={images} onUnavailable={() => {
                const key = JSON.stringify([brand.id || brand.slug, images])
                setFailedImages((previous) => new Set(previous).add(key))
              }} />
            </span>
            <span className="line-clamp-2 min-h-10 w-full text-center text-sm font-semibold leading-5 text-foreground">{brand.name}</span>
          </Link>
        ))}
      </div>
    </section>
  )
}
