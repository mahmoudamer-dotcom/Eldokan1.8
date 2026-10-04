'use client'

import { Heart } from 'lucide-react'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { EldokanClientError, type ProductId } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useWishlist } from './WishlistProvider'

export default function WishlistButton({ productId, className = '' }: { productId?: string | number; className?: string }) {
  const router = useRouter()
  const { t } = useLocale()
  const { productIds, ready, signedIn, toggle } = useWishlist()
  const [busy, setBusy] = useState(false)
  const id = String(productId ?? '')
  const validId = /^prd_[1-9][0-9]*$/.test(id)
  const active = productIds.has(id)

  async function handleClick() {
    if (!validId) return
    if (!signedIn) {
      router.push('/login')
      return
    }
    setBusy(true)
    try {
      await toggle(id as ProductId)
    } catch (cause) {
      if (cause instanceof EldokanClientError && cause.status === 401) router.push('/login')
    } finally {
      setBusy(false)
    }
  }

  return <button type="button" onClick={handleClick} disabled={!validId || !ready || busy}
    aria-label={t(active ? 'Remove from favorites' : 'Add to favorites')}
    aria-pressed={active}
    title={t(active ? 'Remove from favorites' : 'Add to favorites')}
    className={`rounded-full border border-gray-200 bg-white/95 p-2 text-gray-500 shadow-sm transition hover:border-rose-200 hover:text-rose-600 disabled:cursor-wait disabled:opacity-60 ${active ? 'text-rose-600' : ''} ${className}`}>
    <Heart className={`size-4 ${active ? 'fill-current' : ''}`} aria-hidden="true" />
  </button>
}
