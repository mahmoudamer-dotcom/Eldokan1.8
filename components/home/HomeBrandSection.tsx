'use client'

import Link from 'next/link'
import Image from 'next/image'
import { Store } from 'lucide-react'
import { useEffect, useState } from 'react'
import type { HomeBrand } from '@/types/home'
import { useLocale } from '@/components/i18n/LocaleProvider'

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

function BrandLogo({ name, images, mounted }: { name: string; images: string[]; mounted: boolean }) {
  const [imageIndex, setImageIndex] = useState(0)
  const image = images[imageIndex]

  if (!mounted) return <span className="size-10 animate-pulse rounded-full bg-gray-100" aria-hidden="true" />
  if (!image) return <Store className="size-8 text-gray-300" strokeWidth={1.4} aria-hidden="true" />

  return (
    <Image
      src={image}
      alt={name}
      width={160}
      height={80}
      loading="eager"
      decoding="async"
      onError={() => setImageIndex((index) => index + 1)}
      className="h-auto max-h-16 w-auto max-w-full object-contain transition duration-200 group-hover:scale-105"
    />
  )
}

export default function HomeBrandSection({ brands: brandItems, title }: { brands: HomeBrand[]; title?: string }) {
  const { t } = useLocale()
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
  }, [])

  const brands = brandItems
    .map((brand) => ({ brand, images: getBrandImages(brand) }))
    .filter((entry) => entry.images.length > 0)

  if (brands.length === 0) return null

  return (
    <section aria-labelledby="home-brands-title" className="overflow-hidden rounded-3xl border border-gray-100 bg-gradient-to-br from-white via-white to-amber-50/60 p-5 shadow-sm sm:p-7">
      <div className="flex items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#a66d00]">{t('Discover the names you love')}</p>
          <h2 id="home-brands-title" className="mt-1 text-xl font-bold text-gray-900 sm:text-2xl">{title ? t(title) : t('Shop by brand')}</h2>
        </div>
      </div>
      <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
        {brands.map(({ brand, images }) => (
          <Link key={brand.id} href={`/brand/${encodeURIComponent(brand.slug || brand.id)}`} aria-label={`${t('Shop')} ${brand.name}`} title={brand.name} className="group relative grid h-24 place-items-center overflow-hidden rounded-2xl border border-gray-100 bg-white p-4 shadow-[0_2px_12px_rgba(15,23,42,0.035)] transition duration-200 hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md sm:h-28">
            <span className="absolute inset-x-5 bottom-0 h-px bg-gradient-to-r from-transparent via-amber-200 to-transparent opacity-0 transition group-hover:opacity-100" aria-hidden="true" />
            <BrandLogo name={brand.name} images={images} mounted={mounted} />
          </Link>
        ))}
      </div>
    </section>
  )
}
