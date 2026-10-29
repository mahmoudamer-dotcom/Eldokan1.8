'use client'

import { useEffect, useRef, useState } from 'react'
import { CircleAlert, CircleCheck, Info, X } from 'lucide-react'
import { TOAST_EVENT, type ToastDetail } from '@/lib/toast'
import { useLocale } from '@/components/i18n/LocaleProvider'

type Toast = ToastDetail & { id: number }

const icons = { success: CircleCheck, error: CircleAlert, info: Info }
const tones = { success: 'text-emerald-400', error: 'text-rose-400', info: 'text-[#ffd45b]' }

/**
 * Short confirmations at the top centre, clear of the bottom tab bar, cart panel and chat button.
 * Up to three at a time; each leaves after four seconds (errors after six) or when closed.
 */
export default function Toaster() {
  const { locale } = useLocale()
  const [toasts, setToasts] = useState<Toast[]>([])
  const nextId = useRef(0)
  useEffect(() => {
    const timers = new Set<number>()
    const add = (event: Event) => {
      const { message, tone } = (event as CustomEvent<ToastDetail>).detail
      const id = ++nextId.current
      setToasts((current) => [...current.filter((item) => item.message !== message), { id, message, tone }].slice(-3))
      const timer = window.setTimeout(() => { timers.delete(timer); setToasts((current) => current.filter((item) => item.id !== id)) }, tone === 'error' ? 6000 : 4000)
      timers.add(timer)
    }
    window.addEventListener(TOAST_EVENT, add)
    return () => { window.removeEventListener(TOAST_EVENT, add); timers.forEach((timer) => window.clearTimeout(timer)) }
  }, [])

  return <div aria-live="polite" role="status" className="pointer-events-none fixed inset-x-0 top-3 z-[80] flex flex-col items-center gap-2 px-4 print:hidden">
    {toasts.map(({ id, message, tone }) => {
      const Icon = icons[tone]
      return <div key={id} className="eldokan-toast pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-2xl bg-[#1d1d1b] py-3 ps-4 pe-2 text-sm font-semibold text-white shadow-2xl">
        <Icon aria-hidden="true" className={`size-5 shrink-0 ${tones[tone]}`} />
        <span className="min-w-0 flex-1">{message}</span>
        <button type="button" onClick={() => setToasts((current) => current.filter((item) => item.id !== id))} aria-label={locale === 'ar' ? 'إغلاق' : 'Dismiss'} className="grid size-8 shrink-0 place-items-center rounded-full text-white/60 transition hover:bg-white/10 hover:text-white"><X aria-hidden="true" className="size-4" /></button>
      </div>
    })}
  </div>
}
