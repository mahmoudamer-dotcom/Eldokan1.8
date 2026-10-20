'use client'

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import type { ProductCard as ApiProductCard } from '@eldokan/customer-api-client'
import { EldokanClientError, type ProductId } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { toStoreProduct } from '@/lib/catalog-adapters'
import type { StoreProduct } from '@/components/productCard/ProductCard'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { translate } from '@/lib/i18n'

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
  // Product id -> wanted state while the store confirms the change.
  const [overrides, setOverrides] = useState<Map<string, boolean>>(() => new Map())
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
        setError(translate('Unable to load your favorites.', locale))
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
    const saved = products.some((product) => String(product.id) === productId)
    const wanted = !(overrides.get(productId) ?? saved)
    const setOverride = (value: boolean | null) => setOverrides((current) => {
      const next = new Map(current)
      if (value === null) next.delete(productId); else next.set(productId, value)
      return next
    })
    setOverride(wanted)
    try {
      const response = wanted ? await api.wishlist.add(productId) : await api.wishlist.remove(productId)
      setProducts(response.data.items.map((product: ApiProductCard) => toStoreProduct(product)))
      setSignedIn(true)
      setError('')
    } catch (cause) {
      if (cause instanceof EldokanClientError && cause.status === 401) setSignedIn(false)
      setError(translate('Unable to update your favorites.', locale))
      throw cause
    } finally { setOverride(null) }
  }, [products, overrides, locale])

  const remove = useCallback(async (productId: ProductId) => {
    const response = await createEldokanApi(locale).wishlist.remove(productId)
    setProducts(response.data.items.map((product: ApiProductCard) => toStoreProduct(product)))
  }, [locale])

  const value = useMemo(() => ({
    products,
    productIds: (() => {
      const ids = new Set(products.map((product) => String(product.id)))
      for (const [id, wanted] of overrides) { if (wanted) ids.add(id); else ids.delete(id) }
      return ids
    })(),
    ready,
    signedIn,
    error,
    toggle,
    remove,
  }), [products, overrides, ready, signedIn, error, toggle, remove])

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>
}

export function useWishlist() {
  const value = useContext(WishlistContext)
  if (!value) throw new Error('useWishlist must be used inside WishlistProvider')
  return value
}
