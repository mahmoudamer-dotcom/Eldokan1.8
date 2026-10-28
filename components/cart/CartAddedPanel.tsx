'use client'

import Image from 'next/image'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useEffect, useRef, useState } from 'react'
import { CheckCircle2, ShoppingBag, X } from 'lucide-react'
import { useCart } from './CartProvider'
import { useLocale } from '@/components/i18n/LocaleProvider'
import Money from '@/components/i18n/Money'
import { CART_ADDED_EVENT, type CartAddedDetail } from '@/lib/cart-events'

const SHOW_MS = 7000

/**
 * Confirms an added product in place: the shopper sees what went in and the cart total,
 * then keeps browsing or heads to the cart. It closes itself unless hovered or focused.
 */
export default function CartAddedPanel() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const pathname = usePathname()
  const { cart, itemCount } = useCart()
  const [added, setAdded] = useState<CartAddedDetail | null>(null)
  const [held, setHeld] = useState(false)
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined)

  useEffect(() => {
    const onAdded = (event: Event) => setAdded((event as CustomEvent<CartAddedDetail>).detail)
    window.addEventListener(CART_ADDED_EVENT, onAdded)
    return () => window.removeEventListener(CART_ADDED_EVENT, onAdded)
  }, [])
  useEffect(() => {
    clearTimeout(timer.current)
    if (added && !held) timer.current = setTimeout(() => setAdded(null), SHOW_MS)
    return () => clearTimeout(timer.current)
  }, [added, held])
  useEffect(() => { queueMicrotask(() => setAdded(null)) }, [pathname])

  if (!added || pathname.startsWith('/checkout') || pathname === '/cart') return null
  const lines = cart?.items ?? []
  const currency = lines.find((line) => line.line_subtotal)?.line_subtotal
  const subtotal = currency ? { ...currency, amount: lines.reduce((sum, line) => sum + (line.line_subtotal?.amount ?? 0), 0), formatted: undefined } : null

  return (
    <div role="status" aria-live="polite" onMouseEnter={() => setHeld(true)} onMouseLeave={() => setHeld(false)} onFocus={() => setHeld(true)} onBlur={() => setHeld(false)}
      className="cart-added-panel fixed inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-[60] mx-auto max-w-md rounded-2xl border border-border bg-card p-4 text-foreground shadow-2xl md:inset-x-auto md:end-6 md:top-24 md:bottom-auto md:w-96">
      <div className="flex items-start gap-3">
        <span className="relative grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-white ring-1 ring-border">
          {added.imageUrl ? <Image src={added.imageUrl} alt="" fill sizes="64px" className="object-contain p-1" /> : <ShoppingBag className="size-6 text-shop-accent" aria-hidden="true" />}
        </span>
        <div className="min-w-0 flex-1">
          <p className="flex items-center gap-1.5 text-sm font-bold text-success-foreground"><CheckCircle2 className="size-4" aria-hidden="true" />{ar ? 'اتضاف للسلة' : 'Added to cart'}</p>
          <p className="mt-1 line-clamp-2 text-sm leading-5">{added.quantity > 1 && <bdi className="font-bold">{added.quantity}× </bdi>}{added.name}</p>
          <p className="mt-1 text-xs text-muted-foreground">{ar ? `في السلة ${itemCount} ${itemCount === 1 ? 'منتج' : 'منتجات'}` : `${itemCount} ${itemCount === 1 ? 'item' : 'items'} in your cart`}{subtotal && <> · <Money value={subtotal} /></>}</p>
        </div>
        <button type="button" onClick={() => setAdded(null)} aria-label={ar ? 'إغلاق' : 'Close'} className="grid size-8 shrink-0 place-items-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground"><X className="size-4" /></button>
      </div>
      <div className="mt-4 grid grid-cols-2 gap-2">
        <Link href="/cart" className="flex h-11 items-center justify-center rounded-full border border-input text-sm font-bold transition hover:bg-muted">{ar ? 'عرض السلة' : 'View cart'}</Link>
        <Link href={cart?.valid === false ? "/cart" : "/checkout/cart"} className="flex h-11 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground transition hover:brightness-95">{ar ? 'إتمام الشراء' : 'Checkout'}</Link>
      </div>
      <button type="button" onClick={() => setAdded(null)} className="mt-2 w-full py-1.5 text-center text-sm font-semibold text-shop-accent hover:underline">{ar ? 'كمّل تسوق' : 'Continue shopping'}</button>
    </div>
  )
}
