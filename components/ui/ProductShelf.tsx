'use client'

import { Children, useEffect, useRef, useState, type ReactNode } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'

/**
 * A row of product cards that scrolls natively: a finger swipe moves it at once (even before the page's
 * JavaScript loads), with the phone's own momentum, and settles on a card. Larger screens add arrows.
 */
export default function ProductShelf({ children, label }: { children: ReactNode; label?: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const track = useRef<HTMLDivElement>(null)
  const [edges, setEdges] = useState({ start: true, end: false })
  useEffect(() => {
    const element = track.current
    if (!element) return
    // scrollLeft runs negative in right-to-left pages, so compare magnitudes.
    const update = () => {
      const travelled = Math.abs(element.scrollLeft)
      setEdges({ start: travelled < 4, end: travelled + element.clientWidth >= element.scrollWidth - 4 })
    }
    update()
    element.addEventListener('scroll', update, { passive: true })
    window.addEventListener('resize', update)
    return () => { element.removeEventListener('scroll', update); window.removeEventListener('resize', update) }
  }, [])
  const move = (towardEnd: boolean) => {
    const element = track.current
    if (!element) return
    const step = element.clientWidth * 0.85 * (towardEnd ? 1 : -1)
    element.scrollBy({ left: ar ? -step : step, behavior: 'smooth' })
  }
  const arrow = 'absolute top-1/3 z-10 hidden size-10 place-items-center rounded-full border border-border bg-card/95 text-foreground shadow-md transition hover:bg-card disabled:pointer-events-none disabled:opacity-0 md:grid'
  const Back = ar ? ChevronRight : ChevronLeft
  const Forward = ar ? ChevronLeft : ChevronRight
  return <div className="relative" role="region" aria-roledescription={ar ? 'شريط منتجات' : 'product shelf'} aria-label={label}>
    <div ref={track} className="shelf-track -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain scroll-smooth px-4 pb-2 sm:mx-0 sm:px-0">
      {Children.map(children, (child) => <div className="w-[46%] shrink-0 snap-start sm:w-[31%] md:w-[27%] lg:w-[22%] xl:w-[18%]">{child}</div>)}
    </div>
    <button type="button" onClick={() => move(false)} disabled={edges.start} aria-label={ar ? 'المنتجات السابقة' : 'Previous products'} className={`${arrow} -start-3`}><Back aria-hidden="true" className="size-5" /></button>
    <button type="button" onClick={() => move(true)} disabled={edges.end} aria-label={ar ? 'منتجات أكتر' : 'More products'} className={`${arrow} -end-3`}><Forward aria-hidden="true" className="size-5" /></button>
  </div>
}
