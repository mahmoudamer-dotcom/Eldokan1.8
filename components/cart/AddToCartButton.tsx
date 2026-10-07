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
    type?: 'simple' | 'variable' | 'grouped' | 'external' | 'other'
    variationId?: string
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
  const supportedType = product?.type === undefined || product.type === 'simple' || product.type === 'variable'
  const canAdd = Boolean(product?.id) && supportedType && product?.available && (product.type !== 'variable' || Boolean(product.variationId)) && (product?.stockQuantity == null || product.stockQuantity > 0)

  async function handleAdd() {
    if (!product?.id || !product.available || (product.stockQuantity != null && product.stockQuantity < 1)) return
    setFailed(false)
    try {
      await addItem({ id: String(product.id), variationId: product.variationId, imageUrl: product.imageUrl, name: product.name, quantity: product.quantity, stockQuantity: product.stockQuantity })
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
        ? 'absolute bottom-3 left-3 z-10 size-10 rounded-lg border-border bg-card/95 p-0 text-foreground shadow-sm disabled:opacity-100 disabled:text-muted-foreground hover:border-[#c58a36] hover:bg-card'
        : 'h-12 rounded-full border-input font-semibold text-foreground hover:border-muted-foreground hover:bg-background'}
    >
      {compact
        ? failed ? <span className="text-xs font-bold text-danger-foreground" aria-hidden="true">!</span> : <Plus className="size-6" aria-hidden="true" />
        : <><ShoppingBag className="mr-2 size-4" /><T text={added ? 'Added to cart' : 'Add to cart'} /></>}
    </Button>
    {failed && <span role="alert" className={compact
      ? 'absolute bottom-14 left-3 z-20 max-w-52 rounded-lg bg-danger-soft px-3 py-2 text-left text-xs text-danger-foreground shadow-md'
      : 'mt-2 block text-sm text-danger-foreground'}>{error || t('Unable to add to cart. Try again.')}</span>}
  </>
}
