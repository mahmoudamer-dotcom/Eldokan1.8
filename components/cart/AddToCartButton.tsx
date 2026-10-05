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
    stockQuantity?: number | null
    quantity?: number
    available: boolean
  }
}

export default function AddToCartButton({ product, compact = false }: AddToCartButtonProps) {
  const { addItem, busy, error, ready } = useCart()
  const { t } = useLocale()
  const [added, setAdded] = useState(false)
  const [failed, setFailed] = useState(false)
  const canAdd = Boolean(product?.id) && product?.available && (product?.stockQuantity == null || product.stockQuantity > 0)

  async function handleAdd() {
    if (!product?.id || !product.available || (product.stockQuantity != null && product.stockQuantity < 1)) return
    setFailed(false)
    try {
      await addItem({ id: String(product.id), imageUrl: product.imageUrl, name: product.name, quantity: product.quantity, stockQuantity: product.stockQuantity })
      setAdded(true)
    } catch {
      setAdded(false)
      setFailed(true)
    }
  }

  return <>
    <Button
      type="button"
      variant="outline"
      disabled={!canAdd || busy || !ready}
      onClick={handleAdd}
      aria-live="polite"
      aria-label={t(failed ? 'Unable to add to cart' : added ? 'Added to cart' : 'Add to cart')}
      title={t(failed ? 'Unable to add to cart' : added ? 'Added to cart' : 'Add to cart')}
      className={compact
        ? 'absolute bottom-3 left-3 z-10 size-10 rounded-lg border-gray-200 bg-white/95 p-0 text-gray-800 shadow-sm hover:border-[#c58a36] hover:bg-white'
        : 'h-12 rounded-full border-gray-300 font-semibold text-gray-800 hover:border-gray-500 hover:bg-gray-50'}
    >
      {compact
        ? failed ? <span className="text-xs font-bold text-red-700" aria-hidden="true">!</span> : <Plus className="size-6" aria-hidden="true" />
        : <><ShoppingBag className="mr-2 size-4" /><T text={added ? 'Added to cart' : 'Add to cart'} /></>}
    </Button>
    {failed && <span role="alert" className={compact
      ? 'absolute bottom-14 left-3 z-20 max-w-52 rounded-lg bg-red-50 px-3 py-2 text-left text-xs text-red-800 shadow-md'
      : 'mt-2 block text-sm text-red-700'}>{error || t('Unable to add to cart. Try again.')}</span>}
  </>
}
