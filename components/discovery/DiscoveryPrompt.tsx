'use client'
import { MessageCircle, ArrowUpRight } from 'lucide-react'
import { useShoppingChat } from './ShoppingChatProvider'

export default function DiscoveryPrompt({ locale, category, query }: { locale: 'ar' | 'en'; category?: string; query?: string }) {
  const show = useShoppingChat()
  const ar = locale === 'ar'
  return <div className="mt-4"><button type="button" onClick={() => show({ categorySlug: category, keywords: query?.slice(0, 100) })} className="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-card px-4 text-sm font-semibold text-card-foreground transition hover:border-primary"><MessageCircle aria-hidden="true" className="size-4 text-primary" />{ar ? 'محتاج مساعدة تختار؟ اسأل الدكان' : 'Need help choosing? Ask Eldokan'}<ArrowUpRight aria-hidden="true" className="size-4" /></button></div>
}