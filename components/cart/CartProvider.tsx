'use client'

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { EldokanClientError, type Cart, type CartItemId, type ProductId, type VariationId } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'

export type CartProductInput = {
  id: string
  name?: string
  imageUrl?: string
  variationId?: string
  quantity?: number
  stockQuantity?: number | null
}

type CartContextValue = {
  cart: Cart | null
  itemCount: number
  ready: boolean
  busy: boolean
  error: string
  refresh: () => Promise<void>
  addItem: (product: CartProductInput) => Promise<void>
  updateQuantity: (itemId: CartItemId, quantity: number) => Promise<void>
  removeItem: (itemId: CartItemId) => Promise<void>
}

const CartContext = createContext<CartContextValue | null>(null)

function errorMessage(cause: unknown) {
  if (cause instanceof EldokanClientError) return cause.message
  return cause instanceof Error ? cause.message : 'Unable to update your cart.'
}

export function CartProvider({ children }: { children: React.ReactNode }) {
  const { locale } = useLocale()
  const [cart, setCart] = useState<Cart | null>(null)
  const [ready, setReady] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const loadSequence = useRef(0)
  const mutationSequence = useRef(0)
  const imageCache = useRef(new Map<string, NonNullable<Cart['items'][number]['image']>>())

  const hydrateCartImages = useCallback(async (currentCart: Cart) => {
    const missingProductIds = [...new Set(currentCart.items
      .filter((item) => !imageCache.current.has(item.product_id))
      .map((item) => item.product_id))]

    await Promise.all(missingProductIds.map(async (productId) => {
      try {
        const response = await createEldokanApi(locale).products.get(productId)
        const product = response.data
        const image = product.images[0] ?? product.image ?? null
        if (image) imageCache.current.set(productId, image)
        for (const item of currentCart.items) {
          if (item.product_id === productId && item.variation_id) {
            const variationImage = product.variations.find((variation) => variation.id === item.variation_id)?.image
            if (variationImage) imageCache.current.set(`${productId}:${item.variation_id}`, variationImage)
          }
        }
      } catch {
        // Cart item details should remain usable if a product image cannot be loaded.
      }
    }))

    return {
      ...currentCart,
      items: currentCart.items.map((item) => ({
        ...item,
        image: imageCache.current.get(`${item.product_id}:${item.variation_id ?? ''}`) ?? imageCache.current.get(item.product_id) ?? item.image ?? null,
      })),
    }
  }, [locale])

  const refresh = useCallback(async () => {
    const sequence = ++loadSequence.current
    const mutationAtStart = mutationSequence.current
    try {
      const response = await createEldokanApi(locale).cart.get({ lang: locale })
      const hydratedCart = await hydrateCartImages(response.data)
      if (sequence === loadSequence.current && mutationAtStart === mutationSequence.current) {
        setCart(hydratedCart)
        setError('')
      }
    } catch (cause) {
      if (sequence === loadSequence.current && mutationAtStart === mutationSequence.current) setError(errorMessage(cause))
    } finally {
      if (sequence === loadSequence.current) setReady(true)
    }
  }, [locale, hydrateCartImages])

  useEffect(() => {
    let active = true
    queueMicrotask(() => { if (active) void refresh() })
    const handleSessionChange = () => { void refresh() }
    window.addEventListener('eldokan:session-changed', handleSessionChange)
    return () => {
      active = false
      loadSequence.current += 1
      window.removeEventListener('eldokan:session-changed', handleSessionChange)
    }
  }, [refresh])

  const addItem = useCallback(async (product: CartProductInput) => {
    if (product.imageUrl) imageCache.current.set(product.id, { url: product.imageUrl, alt: product.name ?? '' })
    const quantity = product.quantity ?? 1
    const existing = cart?.items.find((item) => item.product_id === product.id && item.variation_id === (product.variationId ?? null))
    const nextQuantity = (existing?.quantity ?? 0) + quantity
    const stockQuantity = product.stockQuantity ?? existing?.stock.quantity ?? null
    if (stockQuantity !== null && nextQuantity > stockQuantity) {
      const message = `Only ${Math.max(0, stockQuantity - (existing?.quantity ?? 0))} item(s) are currently in stock.`
      setError(message)
      throw new Error(message)
    }
    setBusy(true)
    setError('')
    mutationSequence.current += 1
    try {
      const api = createEldokanApi(locale)
      const response = existing
        ? await api.cart.update(existing.id as CartItemId, { quantity: nextQuantity }, { lang: locale })
        : await api.cart.add({
          productId: product.id as ProductId,
          variationId: product.variationId as VariationId | undefined,
          quantity,
        }, { lang: locale })
      setCart(await hydrateCartImages(response.data))
      setReady(true)
    } catch (cause) {
      setError(errorMessage(cause))
      throw cause
    } finally { setBusy(false) }
  }, [cart, locale, hydrateCartImages])

  const updateQuantity = useCallback(async (itemId: CartItemId, quantity: number) => {
    const item = cart?.items.find((entry) => entry.id === itemId)
    if (item && item.stock.quantity !== null && quantity > item.quantity && quantity > item.stock.quantity) {
      const message = `Only ${item.stock.quantity} item(s) are currently in stock.`
      setError(message)
      throw new Error(message)
    }
    setBusy(true)
    setError('')
    mutationSequence.current += 1
    try {
      const response = await createEldokanApi(locale).cart.update(itemId, { quantity }, { lang: locale })
      setCart(await hydrateCartImages(response.data))
    } catch (cause) {
      setError(errorMessage(cause))
      throw cause
    } finally { setBusy(false) }
  }, [cart, locale, hydrateCartImages])

  const removeItem = useCallback(async (itemId: CartItemId) => {
    setBusy(true)
    setError('')
    mutationSequence.current += 1
    try {
      const response = await createEldokanApi(locale).cart.remove(itemId, { lang: locale })
      setCart(await hydrateCartImages(response.data))
    } catch (cause) {
      setError(errorMessage(cause))
      throw cause
    } finally { setBusy(false) }
  }, [locale, hydrateCartImages])

  const value = useMemo(() => ({
    cart,
    itemCount: cart?.items.reduce((count, item) => count + item.quantity, 0) ?? 0,
    ready,
    busy,
    error,
    refresh,
    addItem,
    updateQuantity,
    removeItem,
  }), [cart, ready, busy, error, refresh, addItem, updateQuantity, removeItem])

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart() {
  const value = useContext(CartContext)
  if (!value) throw new Error('useCart must be used inside CartProvider')
  return value
}
