'use client'

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import type { ProductCard as ApiProductCard } from '@eldokan/customer-api-client'
import { EldokanClientError, type ProductId } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { toStoreProduct } from '@/lib/catalog-adapters'
import type { StoreProduct } from '@/components/productCard/ProductCard'
import { useLocale } from '@/components/i18n/LocaleProvider'

type WishlistContextValue = {
  products: StoreProduct[]
  productIds: Set<string>
  ready: boolean
  signedIn: boolean
  error: string
  toggle: (productId: ProductId) => Promise<void>
  remove: (productId: ProductId) => Promise<void>
}

const WishlistContext = createContext<WishlistContextValue | null>(null)

export function WishlistProvider({ children }: { children: React.ReactNode }) {
  const { locale } = useLocale()
  const [products, setProducts] = useState<StoreProduct[]>([])
  const [ready, setReady] = useState(false)
  const [signedIn, setSignedIn] = useState(false)
  const [error, setError] = useState('')
  const loadSequence = useRef(0)

  const load = useCallback(async () => {
    const sequence = ++loadSequence.current
    const api = createEldokanApi(locale)
    try {
      await api.auth.session()
      if (sequence !== loadSequence.current) return
      setSignedIn(true)
      const response = await api.wishlist.get()
      if (sequence !== loadSequence.current) return
      setProducts(response.data.items.map((product: ApiProductCard) => toStoreProduct(product)))
      setError('')
    } catch (cause) {
      if (sequence !== loadSequence.current) return
      if (cause instanceof EldokanClientError && cause.status === 401) {
        setProducts([])
        setSignedIn(false)
        setError('')
      } else {
        setError('Unable to load your favorites.')
      }
    } finally {
      if (sequence === loadSequence.current) setReady(true)
    }
  }, [locale])

  useEffect(() => {
    queueMicrotask(() => { void load() })
    const handleSessionChange = () => { void load() }
    window.addEventListener('eldokan:session-changed', handleSessionChange)
    return () => {
      loadSequence.current += 1
      window.removeEventListener('eldokan:session-changed', handleSessionChange)
    }
  }, [load])

  const toggle = useCallback(async (productId: ProductId) => {
    const api = createEldokanApi(locale)
    try {
      const response = products.some((product) => String(product.id) === productId)
        ? await api.wishlist.remove(productId)
        : await api.wishlist.add(productId)
      setProducts(response.data.items.map((product: ApiProductCard) => toStoreProduct(product)))
      setSignedIn(true)
      setError('')
    } catch (cause) {
      if (cause instanceof EldokanClientError && cause.status === 401) setSignedIn(false)
      setError('Unable to update your favorites.')
      throw cause
    }
  }, [products, locale])

  const remove = useCallback(async (productId: ProductId) => {
    const response = await createEldokanApi(locale).wishlist.remove(productId)
    setProducts(response.data.items.map((product: ApiProductCard) => toStoreProduct(product)))
  }, [locale])

  const value = useMemo(() => ({
    products,
    productIds: new Set(products.map((product) => String(product.id))),
    ready,
    signedIn,
    error,
    toggle,
    remove,
  }), [products, ready, signedIn, error, toggle, remove])

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>
}

export function useWishlist() {
  const value = useContext(WishlistContext)
  if (!value) throw new Error('useWishlist must be used inside WishlistProvider')
  return value
}
