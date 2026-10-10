'use client'

import { createContext, useCallback, useContext, useRef, useState } from 'react'
import dynamic from 'next/dynamic'
import { usePathname } from 'next/navigation'
import { MessageCircle } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'

function ChatLoading() {
  const { locale } = useLocale()
  return <div role="status" className="fixed bottom-20 end-5 z-50 rounded-xl border border-border bg-card p-4 text-sm text-foreground shadow-lg">{locale === 'ar' ? 'جارٍ فتح مساعد التسوق…' : 'Opening shopping assistant…'}</div>
}
const Chat = dynamic(() => import('./ShoppingChatPanel'), { ssr: false, loading: ChatLoading })

export type OpenOptions = { categorySlug?: string; keywords?: string; resume?: boolean }
const Context = createContext<((options?: OpenOptions) => void) | null>(null)
export function useShoppingChat() {
  const value = useContext(Context)
  if (!value) throw new Error('ShoppingChatProvider is required')
  return value
}
export function ShoppingChatProvider({ children }: { children: React.ReactNode }) {
  const { locale } = useLocale()
  const pathname = usePathname()
  const [open, setOpen] = useState(false)
  const [started, setStarted] = useState(false)
  const [config, setConfig] = useState<{ id: number; options: OpenOptions }>({ id: 0, options: {} })
  const source = useRef<HTMLElement | null>(null)
  const show = useCallback((options: OpenOptions = {}) => {
    source.current = document.activeElement instanceof HTMLElement ? document.activeElement : null
    if (options.categorySlug || options.keywords || options.resume) setConfig(previous => ({ id: previous.id + 1, options }))
    setStarted(true); setOpen(true)
  }, [])
  const close = useCallback((restoreFocus = true) => {
    setOpen(false)
    if (restoreFocus) window.setTimeout(() => {
      if (source.current?.isConnected) source.current.focus()
      else document.querySelector<HTMLButtonElement>('[aria-controls="eldokan-shopping-chat"]')?.focus()
    }, 0)
  }, [])
  const hidden = pathname.startsWith('/checkout')
  return <Context.Provider value={show}>{children}
    {!hidden && !open && <button type="button" onClick={() => show()} aria-haspopup="dialog" aria-expanded={false} aria-controls="eldokan-shopping-chat" className="shopping-chat-launcher fixed bottom-[max(1rem,env(safe-area-inset-bottom))] end-4 z-50 inline-flex min-h-14 items-center gap-2 rounded-full bg-[#202124] px-5 text-sm font-bold text-white shadow-xl transition hover:bg-black focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary print:hidden"><MessageCircle aria-hidden="true" className="size-5 text-[#ffd45b]" /><span>{locale === 'ar' ? 'اسأل الدكان' : 'Ask Eldokan'}</span></button>}
    {started && <Chat key={`${locale}:${config.id}`} locale={locale} options={config.options} open={open && !hidden} close={close} />}
  </Context.Provider>
}

