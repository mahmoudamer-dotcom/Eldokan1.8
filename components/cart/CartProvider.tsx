'use client'

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

export type CartProductInput = {
  id: string
  name: string
  unitPrice: number
  imageUrl?: string
}

type CartItem = CartProductInput & { quantity: number }

type CartContextValue = {
  itemCount: number
  addItem: (product: CartProductInput) => void
}

const CartContext = createContext<CartContextValue | null>(null)
const CART_STORAGE_KEY = 'eldokan:cart:v1'

export function CartProvider({ children }: { children: React.ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([])
  const [hydrated, setHydrated] = useState(false)

  useEffect(() => {
    try {
      const saved = window.localStorage.getItem(CART_STORAGE_KEY)
      if (saved) {
        const parsed: unknown = JSON.parse(saved)
        if (Array.isArray(parsed)) setItems(parsed)
      }
    } catch {
      window.localStorage.removeItem(CART_STORAGE_KEY)
    } finally {
      setHydrated(true)
    }
  }, [])

  useEffect(() => {
    if (hydrated) window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items))
  }, [hydrated, items])

  const addItem = useCallback((product: CartProductInput) => {
    setItems((current) => {
      const existing = current.find((item) => item.id === product.id)
      if (existing) {
        return current.map((item) => item.id === product.id
          ? { ...item, quantity: item.quantity + 1 }
          : item)
      }
      return [...current, { ...product, quantity: 1 }]
    })
  }, [])

  const value = useMemo(() => ({
    itemCount: items.reduce((total, item) => total + item.quantity, 0),
    addItem,
  }), [items, addItem])

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart() {
  const value = useContext(CartContext)
  if (!value) throw new Error('useCart must be used inside CartProvider')
  return value
}
