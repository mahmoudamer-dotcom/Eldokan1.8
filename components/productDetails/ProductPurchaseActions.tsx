'use client'

import { useState } from 'react'
import AddToCartButton from '@/components/cart/AddToCartButton'
import BuyNowButton from '@/components/productDetails/BuyNowButton'
import { useLocale } from '@/components/i18n/LocaleProvider'

type Props = {
  productId: string
  name: string
  imageUrl?: string
  unitPrice: number
  available: boolean
  stockQuantity?: number | null
}

export default function ProductPurchaseActions({ productId, name, imageUrl, unitPrice, available, stockQuantity }: Props) {
  const { t } = useLocale()
  const maxQuantity = stockQuantity == null ? 999 : Math.max(0, Math.min(999, stockQuantity))
  const [quantity, setQuantity] = useState(maxQuantity > 0 ? 1 : 0)
  const canPurchase = available && maxQuantity > 0
  const setValidQuantity = (value: number) => {
    if (Number.isFinite(value)) setQuantity(Math.max(1, Math.min(maxQuantity, Math.floor(value))))
  }

  return <div className="mt-6 space-y-4">
    <div className="flex flex-wrap items-center gap-3">
      <label htmlFor="product-quantity" className="text-sm font-semibold text-gray-700">{t('Quantity')}</label>
      <div className="flex h-10 items-center overflow-hidden rounded-lg border border-gray-300">
        <button type="button" aria-label={t('Decrease quantity')} disabled={!canPurchase || quantity <= 1} onClick={() => setValidQuantity(quantity - 1)} className="h-full w-10 text-lg disabled:opacity-40">−</button>
        <input id="product-quantity" type="number" min={canPurchase ? 1 : 0} max={maxQuantity} value={quantity} disabled={!canPurchase} onChange={(event) => setValidQuantity(Number(event.target.value))} className="h-full w-14 border-x border-gray-300 text-center outline-none disabled:bg-gray-100" />
        <button type="button" aria-label={t('Increase quantity')} disabled={!canPurchase || quantity >= maxQuantity} onClick={() => setValidQuantity(quantity + 1)} className="h-full w-10 text-lg disabled:opacity-40">+</button>
      </div>
      {stockQuantity != null && <span className="text-xs text-gray-500">{t('Available')}: {stockQuantity}</span>}
    </div>
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <BuyNowButton productId={productId} quantity={quantity || 1} className={`flex h-12 items-center justify-center rounded-full bg-[#f5b400] font-semibold text-gray-950 transition hover:bg-[#e4a600] ${!canPurchase ? 'pointer-events-none opacity-50' : ''}`} />
      <AddToCartButton product={{ id: productId, name, imageUrl, unitPrice, available: canPurchase, quantity, stockQuantity }} />
    </div>
  </div>
}
