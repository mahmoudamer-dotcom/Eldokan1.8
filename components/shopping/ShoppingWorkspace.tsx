'use client'

import Link from 'next/link'
import { ArrowRight, GitCompareArrows, Heart, MessageCircle, ShoppingBag } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useCart } from '@/components/cart/CartProvider'
import { useWishlist } from '@/components/wishlist/WishlistProvider'
import { useProductComparison } from '@/lib/use-product-comparison'
import { useComparisonDrawer } from '@/components/productDetails/ComparisonProvider'
import { useShoppingChat } from '@/components/discovery/ShoppingChatProvider'

export default function ShoppingWorkspace() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const { itemCount, ready } = useCart()
  const { productIds, ready: wishlistReady } = useWishlist()
  const compareIds = useProductComparison()
  const compare = useComparisonDrawer()
  const chat = useShoppingChat()
  const tile = 'flex min-h-20 min-w-0 items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-start transition hover:border-primary hover:bg-shop-soft'
  return <section aria-labelledby="shopping-workspace-title" className="space-y-3">
    <div className="flex flex-wrap items-baseline justify-between gap-2">
      <h2 id="shopping-workspace-title" className="text-lg font-bold">{ar ? 'كل اختياراتك في مكان واحد' : 'Your shopping, all together'}</h2>
      <p className="text-xs text-muted-foreground">{ar ? 'اختار، قارن، وكمّل على راحتك' : 'Choose, compare, and pick up where you left off'}</p>
    </div>
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <button type="button" onClick={() => chat({ resume: true })} aria-haspopup="dialog" className={tile}><MessageCircle aria-hidden="true" className="size-5 shrink-0 text-shop-accent" /><span className="min-w-0"><strong className="block text-sm">{ar ? 'اختار مع الدكان' : 'Choose with Eldokan'}</strong><span className="text-xs text-muted-foreground">{ar ? 'حسب احتياجك وميزانيتك' : 'For your needs and budget'}</span></span></button>
      <button type="button" onClick={() => compare()} aria-haspopup="dialog" className={tile}><GitCompareArrows aria-hidden="true" className="size-5 shrink-0 text-shop-accent" /><span><strong className="block text-sm">{ar ? 'قارن التفاصيل' : 'Compare the details'}</strong><span className="text-xs text-muted-foreground">{compareIds.length ? `${compareIds.length} ${ar ? 'منتجات للمقارنة' : 'products to compare'}` : ar ? 'لحد ٤ منتجات جنب بعض' : 'Up to 4 products side by side'}</span></span></button>
      <Link href="/wishlist" className={tile}><Heart aria-hidden="true" className="size-5 shrink-0 text-shop-accent" /><span><strong className="block text-sm">{ar ? 'اختياراتك المحفوظة' : 'Your saved picks'}</strong><span className="text-xs text-muted-foreground">{wishlistReady && productIds.size ? `${productIds.size} ${ar ? 'منتجات في المفضلة' : 'saved products'}` : ar ? 'ارجع للي عجبك بسهولة' : 'Keep your favorites close'}</span></span></Link>
      <Link href="/cart" className={tile}><ShoppingBag aria-hidden="true" className="size-5 shrink-0 text-shop-accent" /><span className="flex-1"><strong className="block text-sm">{ar ? 'كمّل مشترياتك' : 'Continue your purchase'}</strong><span className="text-xs text-muted-foreground">{ready && itemCount ? `${itemCount} ${ar ? 'قطع في سلتك' : 'items in your cart'}` : ar ? 'راجع سلتك قبل الدفع' : 'Review your cart before paying'}</span></span><ArrowRight aria-hidden="true" className="hidden size-4 shrink-0 rtl:rotate-180 sm:block" /></Link>
    </div>
  </section>
}
