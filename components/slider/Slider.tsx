'use client'

import Image from 'next/image'
import Link from 'next/link'
import { useEffect, useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { HomeHeroSlide } from '@/types/home'
import { useLocale } from '@/components/i18n/LocaleProvider'

function imageUrl(image: HomeHeroSlide['desktop_image']) {
  if (typeof image === 'string') return image
  return image?.url ?? undefined
}

function ctaHref(slide: HomeHeroSlide) {
  const value = slide.cta?.value?.trim()
  if (!value) return '/#products'

  switch (slide.cta?.type) {
    case 'search':
      return `/search?q=${encodeURIComponent(value)}`
    case 'category':
      return `/category/${encodeURIComponent(value)}`
    case 'product':
      return `/product/${encodeURIComponent(value)}`
    default:
      return value.startsWith('/') ? value : '/#products'
  }
}

const defaultSlide: HomeHeroSlide = {
  id: 'eldokan-welcome',
  title: 'Find your next favorite thing.',
  subtitle: 'Explore a growing range of products, all in one place.',
  cta: { text: 'Explore products', type: 'url', value: '/#products' },
}

export default function Slider({
  slides = [],
  eyebrow = 'WELCOME TO ELDOKAN',
}: {
  slides?: HomeHeroSlide[]
  eyebrow?: string
}) {
  const { t } = useLocale()
  const banners = slides.length > 0 ? slides : [defaultSlide]
  const [activeIndex, setActiveIndex] = useState(0)
  const activeSlide = banners[activeIndex] ?? banners[0]
  const desktopImage = imageUrl(activeSlide.desktop_image)
  const mobileImage = imageUrl(activeSlide.mobile_image)
  const hasImage = Boolean(desktopImage || mobileImage)

  useEffect(() => {
    if (banners.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    const timer = window.setInterval(() => {
      setActiveIndex((index) => (index + 1) % banners.length)
    }, 6000)

    return () => window.clearInterval(timer)
  }, [banners.length])

  function showSlide(index: number) {
    setActiveIndex((index + banners.length) % banners.length)
  }

  return (
    <section aria-label={t('Featured offers')} className={`relative isolate flex min-h-[250px] items-center overflow-hidden rounded-2xl px-7 py-8 sm:min-h-[340px] sm:px-14 sm:py-12 ${hasImage ? 'bg-gray-800 text-white' : 'bg-gradient-to-r from-[#fff2c9] via-[#ffe6a1] to-[#f5c65a] text-gray-950'}`}>
      {hasImage && (
        <>
          <picture className="absolute inset-0 -z-20">
            {mobileImage && <source media="(max-width: 640px)" srcSet={mobileImage} />}
            {(desktopImage || mobileImage) && (
              <Image
                src={desktopImage ?? mobileImage!}
                alt=""
                fill
                priority
                sizes="100vw"
                className="object-cover"
              />
            )}
          </picture>
          <div className="absolute inset-0 -z-10 bg-gradient-to-r from-black/75 via-black/45 to-black/10" />
        </>
      )}

      {!hasImage && (
        <>
          <div aria-hidden="true" className="absolute -right-12 -top-24 -z-10 size-72 rounded-full border-[36px] border-white/25 sm:right-12 sm:top-[-9rem] sm:size-[28rem] sm:border-[54px]" />
          <div aria-hidden="true" className="absolute bottom-[-6rem] right-24 -z-10 size-48 rounded-full bg-white/25 blur-2xl sm:bottom-[-8rem] sm:right-48 sm:size-72" />
        </>
      )}

      <div key={activeSlide.id} className="relative z-10 max-w-2xl animate-in fade-in duration-500">
        <p className={`text-xs font-bold tracking-[0.2em] ${hasImage ? 'text-white/80' : 'text-gray-700'}`}>{t(eyebrow)}</p>
        <h1 className="mt-3 text-3xl font-bold leading-tight tracking-tight sm:text-5xl">{t(activeSlide.title || 'Discover something new.')}</h1>
        {activeSlide.subtitle && <p className={`mt-3 max-w-xl text-sm leading-6 sm:text-base ${hasImage ? 'text-white/90' : 'text-gray-700'}`}>{t(activeSlide.subtitle)}</p>}
        {activeSlide.cta?.text && (
          <Link href={ctaHref(activeSlide)} className={`mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold transition ${hasImage ? 'bg-white text-gray-950 hover:bg-gray-100' : 'bg-gray-950 text-white hover:bg-gray-800'}`}>
            {t(activeSlide.cta.text)}
          </Link>
        )}
      </div>

      {banners.length > 1 && (
        <>
          <button type="button" aria-label={t('Previous banner')} onClick={() => showSlide(activeIndex - 1)} className="absolute left-3 top-1/2 z-20 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-gray-900 shadow transition hover:bg-white">
            <ChevronLeft className="size-5" />
          </button>
          <button type="button" aria-label={t('Next banner')} onClick={() => showSlide(activeIndex + 1)} className="absolute right-3 top-1/2 z-20 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-gray-900 shadow transition hover:bg-white">
            <ChevronRight className="size-5" />
          </button>
          <div className="absolute bottom-4 right-6 z-20 flex gap-2" aria-label={t('Choose banner')}>
            {banners.map((banner, index) => (
              <button key={banner.id} type="button" aria-label={`${t('Show banner')} ${index + 1}`} aria-pressed={activeIndex === index} onClick={() => setActiveIndex(index)} className={`size-2.5 rounded-full transition ${activeIndex === index ? 'bg-white' : 'bg-white/50 hover:bg-white/80'}`} />
            ))}
          </div>
        </>
      )}
    </section>
  )
}
