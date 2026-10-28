'use client'

import Image from 'next/image'
import Link from 'next/link'
import { ArrowLeft, ArrowRight, ShoppingCart } from 'lucide-react'
import { useCart } from '@/components/cart/CartProvider'
import { useLocale } from '@/components/i18n/LocaleProvider'

/** A returning shopper with items waiting in the cart sees them first, one tap from checkout. */
export default function HomeCartReminder() {
  const { cart, itemCount, ready } = useCart()
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const items = cart?.items ?? []
  if (!ready || itemCount === 0 || items.length === 0) return null
  const Arrow = ar ? ArrowLeft : ArrowRight
  return (
    <Link href="/cart" className="group flex items-center gap-4 rounded-2xl bg-card p-3 pe-4 shadow-sm ring-1 ring-border transition hover:shadow-md sm:p-4">
      <span className="flex shrink-0 -space-x-3 rtl:space-x-reverse">
        {items.slice(0, 3).map((item) => (
          <span key={item.id} className="relative grid size-12 place-items-center overflow-hidden rounded-full bg-white ring-2 ring-card">
            {item.image?.url ? <Image src={item.image.url} alt="" fill sizes="48px" className="object-contain p-1" /> : <ShoppingCart className="size-5 text-shop-accent" aria-hidden="true" />}
          </span>
        ))}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-bold text-foreground sm:text-base">{ar ? `عندك ${itemCount} ${itemCount === 1 ? 'منتج' : 'منتجات'} في السلة` : `${itemCount} ${itemCount === 1 ? 'item is' : 'items are'} waiting in your cart`}</span>
        <span className="block truncate text-xs text-muted-foreground sm:text-sm">{ar ? 'كمّل طلبك قبل ما الكمية تخلص' : 'Finish your order before they sell out'}</span>
      </span>
      <span className="hidden shrink-0 items-center gap-1 rounded-full bg-primary px-4 py-2 text-sm font-bold text-primary-foreground sm:inline-flex">{ar ? 'كمّل الطلب' : 'Checkout'}<Arrow className="size-4" aria-hidden="true" /></span>
      <Arrow className="size-5 shrink-0 text-shop-accent sm:hidden" aria-hidden="true" />
    </Link>
  )
}
