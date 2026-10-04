'use client'

import { useState } from 'react'
import { Plus, ShoppingBag } from 'lucide-react'
import { Button } from '@/components/ui/button'
import T from '@/components/i18n/T'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useCart } from './CartProvider'

type AddToCartButtonProps = {
  compact?: boolean
  product?: {
    id?: string | number
    name: string
    imageUrl?: string
    unitPrice: number
    available: boolean
  }
}

export default function AddToCartButton({ product, compact = false }: AddToCartButtonProps) {
  const { addItem } = useCart()
  const { t } = useLocale()
  const [added, setAdded] = useState(false)
  const canAdd = Boolean(product?.id) && product?.available

  function handleAdd() {
    if (!product?.id || !product.available) return
    addItem({
      id: String(product.id),
      name: product.name,
      unitPrice: product.unitPrice,
      imageUrl: product.imageUrl,
    })
    setAdded(true)
  }

  return (
    <Button
      type="button"
      variant="outline"
      disabled={!canAdd}
      onClick={handleAdd}
      aria-live="polite"
      aria-label={t(added ? 'Added to cart' : 'Add to cart')}
      title={t(added ? 'Added to cart' : 'Add to cart')}
      className={compact
        ? 'absolute bottom-3 left-3 z-10 size-10 rounded-lg border-gray-200 bg-white/95 p-0 text-gray-800 shadow-sm hover:border-[#c58a36] hover:bg-white'
        : 'h-12 rounded-full border-gray-300 font-semibold text-gray-800 hover:border-gray-500 hover:bg-gray-50'}
    >
      {compact
        ? <Plus className="size-6" aria-hidden="true" />
        : <><ShoppingBag className="mr-2 size-4" /><T text={added ? 'Added to cart' : 'Add to cart'} /></>}
    </Button>
  )
}
