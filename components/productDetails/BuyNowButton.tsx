'use client'

import { useRouter } from 'next/navigation'
import { useState } from 'react'
import T from '@/components/i18n/T'
import { useCart } from '@/components/cart/CartProvider'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { customerError } from '@/lib/customer-error'

type Props = { productId: string; variationId?: string; quantity?: number; className?: string; name?: string; imageUrl?: string; stockQuantity?: number | null }

export default function BuyNowButton({ productId, variationId, quantity = 1, className, name, imageUrl, stockQuantity }: Props) {
  const router = useRouter()
  const { addItem } = useCart()
  const { locale } = useLocale()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function buyNow() {
    setBusy(true); setError('')
    try {
      await addItem({ id: productId, variationId, quantity, name, imageUrl, stockQuantity })
      router.push('/checkout/cart')
    } catch (cause) {
      setError(customerError(cause, locale, 'Unable to prepare checkout.'))
      setBusy(false)
    }
  }

  return <>
    <button type="button" disabled={busy} onClick={() => void buyNow()} className={className}>
      {busy ? <T text="Preparing…" /> : <T text="Buy now" />}
    </button>
    {error && <p role="alert" className="text-sm text-danger-foreground">{error}</p>}
  </>
}
