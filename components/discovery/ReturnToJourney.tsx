'use client'
import { useEffect, useState } from 'react'
import { MessageCircle } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { readSelectionDraft } from '@/lib/selection-journey-storage'
import { useShoppingChat } from './ShoppingChatProvider'

export default function ReturnToJourney() {
  const [hasJourney, setHasJourney] = useState(false)
  const { locale } = useLocale()
  const show = useShoppingChat()
  useEffect(() => { queueMicrotask(() => setHasJourney(Boolean(readSelectionDraft()))) }, [])
  if (!hasJourney) return null
  return <button type="button" onClick={() => show()} className="mb-5 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-semibold text-card-foreground transition hover:border-primary"><MessageCircle aria-hidden="true" className="size-4 text-primary" />{locale === 'ar' ? 'كمّل مع شات الدكان' : 'Continue with Eldokan chat'}</button>
}