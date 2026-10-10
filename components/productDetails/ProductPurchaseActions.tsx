'use client'
import Money from '@/components/i18n/Money'

import { useMemo, useState } from 'react'
import type { Attribute, Variation } from '@eldokan/customer-api-client'
import AddToCartButton from '@/components/cart/AddToCartButton'
import BuyNowButton from '@/components/productDetails/BuyNowButton'
import { useLocale } from '@/components/i18n/LocaleProvider'

type Props = {
  productId: string
  productType: 'simple' | 'variable' | 'grouped' | 'external' | 'other'
  attributes: Attribute[]
  variations: Variation[]
  name: string
  imageUrl?: string
  unitPrice: number
  available: boolean
  stockQuantity?: number | null
}

export default function ProductPurchaseActions({ productId, productType, attributes, variations, name, imageUrl, unitPrice, available, stockQuantity }: Props) {
  const { t } = useLocale()
  const variableAttributes = attributes.filter((attribute) => attribute.variation)
  const [selections, setSelections] = useState<Record<string, string>>({})
  const [quantity, setQuantity] = useState(1)
  const attributeKey = (attribute: Attribute) => attribute.id ?? attribute.slug
  const selectedVariation = useMemo(() => {
    if (productType !== 'variable' || !variableAttributes.length || variableAttributes.some((attribute) => !selections[attributeKey(attribute)])) return undefined
    return variations.find((variation) => variation.purchasable && variation.attributes.length === variableAttributes.length && variation.attributes.every((selection) => {
      if (selection.option_id === null && selection.option_slug === null) return false
      const key = selection.attribute_id ?? selection.attribute_slug
      const value = selection.option_id ?? selection.option_slug
      return value !== null && selections[key] === value
    }))
  }, [productType, variableAttributes, variations, selections])

  const selectedMoney = selectedVariation?.pricing.price ?? selectedVariation?.pricing.sale_price ?? selectedVariation?.pricing.regular_price
  const selectedImage = selectedVariation?.image?.url ?? imageUrl
  const maxQuantity = productType === 'variable'
    ? selectedVariation?.stock.quantity == null ? 999 : Math.max(0, Math.min(999, selectedVariation.stock.quantity))
    : stockQuantity == null ? 999 : Math.max(0, Math.min(999, stockQuantity))
  const canPurchase = productType === 'variable'
    ? Boolean(selectedVariation?.purchasable && selectedVariation.stock.status !== 'out_of_stock' && maxQuantity > 0)
    : productType === 'simple' && available && maxQuantity > 0
  const setValidQuantity = (value: number) => {
    if (Number.isFinite(value)) setQuantity(Math.max(1, Math.min(maxQuantity, Math.floor(value))))
  }

  return <div className="mt-6 space-y-4">
    {productType === 'variable' && <div className="space-y-3 rounded-xl bg-background p-4">
      {variableAttributes.length ? variableAttributes.map((attribute) => <label key={attributeKey(attribute)} className="block text-sm font-semibold text-foreground">
        {t(attribute.name)}
        <select value={selections[attributeKey(attribute)] ?? ''} onChange={(event) => {
          const value = event.target.value
          setSelections((current) => ({ ...current, [attributeKey(attribute)]: value }))
          setQuantity(1)
        }} className="mt-1 w-full rounded-lg border border-input bg-card px-3 py-2.5 font-normal">
          <option value="">{t('Choose an option')}</option>
          {attribute.options.map((option) => <option key={`${option.id ?? option.slug}`} value={option.id ?? option.slug}>{t(option.name)}</option>)}
        </select>
      </label>) : <p className="text-sm text-shop-accent">{t('No purchasable variation choices are available.')}</p>}
      {selectedVariation && <p className="text-sm font-semibold">{selectedMoney ? <Money value={selectedMoney} /> : t('Price unavailable')}</p>}
    </div>}
    <div className="flex flex-wrap items-center gap-3">
      <label htmlFor="product-quantity" className="text-sm font-semibold text-foreground">{t('Quantity')}</label>
      <div className="flex h-10 items-center overflow-hidden rounded-lg border border-input">
        <button type="button" aria-label={t('Decrease quantity')} disabled={!canPurchase || quantity <= 1} onClick={() => setValidQuantity(quantity - 1)} className="h-full w-10 text-lg disabled:opacity-40">−</button>
        <input id="product-quantity" type="number" min={canPurchase ? 1 : 0} max={maxQuantity} value={canPurchase ? Math.min(quantity, maxQuantity) : 0} disabled={!canPurchase} onChange={(event) => setValidQuantity(Number(event.target.value))} className="h-full w-14 border-x border-input text-center outline-none disabled:bg-muted" />
        <button type="button" aria-label={t('Increase quantity')} disabled={!canPurchase || quantity >= maxQuantity} onClick={() => setValidQuantity(quantity + 1)} className="h-full w-10 text-lg disabled:opacity-40">+</button>
      </div>
      {canPurchase && (productType === 'variable' ? selectedVariation?.stock.quantity : stockQuantity) != null && <span className="text-xs text-muted-foreground">{t('Available')}: {productType === 'variable' ? selectedVariation?.stock.quantity : stockQuantity}</span>}
    </div>
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <BuyNowButton productId={productId} variationId={selectedVariation?.id} name={name} imageUrl={selectedImage} stockQuantity={maxQuantity} quantity={Math.min(quantity, maxQuantity)} className={`flex h-12 items-center justify-center rounded-full bg-[#f5b400] font-semibold text-primary-foreground transition hover:bg-[#e4a600] disabled:pointer-events-none disabled:opacity-50 ${!canPurchase ? 'pointer-events-none opacity-50' : ''}`} />
      <AddToCartButton product={{ id: productId, type: productType, variationId: selectedVariation?.id, name, imageUrl: selectedImage, unitPrice: selectedMoney?.amount ?? unitPrice, available: canPurchase, quantity: Math.min(quantity, maxQuantity), stockQuantity: maxQuantity }} />
    </div>
  </div>
}
