'use client'
import { useEffect } from 'react'
import { MessageCircle } from 'lucide-react'
import { useShoppingChat } from './ShoppingChatProvider'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function SelectionJourney({ categorySlug, keywords, autoOpen = false }: { categorySlug?: string; keywords?: string; autoOpen?: boolean }) {
  const show = useShoppingChat()
  const { locale } = useLocale()
  useEffect(() => {
    if (autoOpen) show(categorySlug || keywords ? { categorySlug, keywords } : { resume: true })
  }, [show, autoOpen, categorySlug, keywords])
  return <button type="button" onClick={() => show(categorySlug || keywords ? { categorySlug, keywords } : { resume: true })} className="inline-flex min-h-12 items-center gap-2 rounded-xl bg-[#202124] px-5 text-sm font-bold text-white"><MessageCircle aria-hidden="true" className="size-5 text-[#ffd45b]" />{locale === 'ar' ? 'افتح شات الدكان' : 'Open Eldokan chat'}</button>
}