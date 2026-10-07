'use client'

import { useEffect, useState } from 'react'
import useEmblaCarousel, { type UseEmblaCarouselType } from 'embla-carousel-react'
import { useLocale } from '@/components/i18n/LocaleProvider'

// A gallery inside a product shelf owns its gesture; the shelf must not move too.
export function ownsCarouselDrag(api: NonNullable<UseEmblaCarouselType[1]>, event: MouseEvent | TouchEvent) {
  return event.target instanceof Element &&
    event.target.closest('[data-swipe-viewport]') === api.rootNode()
}

export function useSwipeCarousel(count: number, startIndex = 0, swipeEnabled = true) {
  const { locale } = useLocale()
  const direction = locale === 'ar' ? 'rtl' : 'ltr'
  const [viewportRef, api] = useEmblaCarousel({
    direction,
    loop: true,
    startIndex,
    watchDrag: swipeEnabled && count > 1 ? ownsCarouselDrag : false,
  })
  const [selectedIndex, setSelectedIndex] = useState(startIndex)

  useEffect(() => {
    if (!api) return
    const update = () => setSelectedIndex(api.selectedScrollSnap())
    queueMicrotask(update)
    api.on('select', update).on('reInit', update)
    return () => { api.off('select', update).off('reInit', update) }
  }, [api])

  return { viewportRef, api, selectedIndex, direction }
}
