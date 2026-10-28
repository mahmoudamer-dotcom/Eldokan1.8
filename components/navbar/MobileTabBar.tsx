'use client'

import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
import { useEffect, useState } from 'react'
import { Heart, Home, Search, ShoppingCart, User } from 'lucide-react'
import { useCart } from '@/components/cart/CartProvider'
import { useWishlist } from '@/components/wishlist/WishlistProvider'
import { useLocale } from '@/components/i18n/LocaleProvider'

/**
 * App-style bottom bar on phones: home, search, favourites, cart and account are always one
 * tap away. Hidden where a page has its own bottom action (checkout, product buy bar).
 */
export default function MobileTabBar() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const pathname = usePathname()
  const router = useRouter()
  const { itemCount } = useCart()
  const { productIds } = useWishlist()
  const [hydrated, setHydrated] = useState(false)
  useEffect(() => { queueMicrotask(() => setHydrated(true)) }, [])
  if (pathname.startsWith('/checkout') || pathname.startsWith('/product/')) return null

  function openSearch() {
    const input = document.getElementById('navbar-product-search') as HTMLInputElement | null
    if (!input) { router.push('/search'); return }
    window.scrollTo({ top: 0, behavior: 'smooth' })
    input.focus({ preventScroll: true })
  }

  const tabs = [
    { href: '/', label: ar ? 'الرئيسية' : 'Home', icon: Home, active: pathname === '/' },
    { label: ar ? 'بحث' : 'Search', icon: Search, active: pathname.startsWith('/search'), onClick: openSearch },
    { href: '/wishlist', label: ar ? 'المفضلة' : 'Favorites', icon: Heart, active: pathname.startsWith('/wishlist'), count: hydrated ? productIds.size : 0 },
    { href: '/cart', label: ar ? 'السلة' : 'Cart', icon: ShoppingCart, active: pathname.startsWith('/cart'), count: hydrated ? itemCount : 0 },
    { href: '/account', label: ar ? 'حسابي' : 'Account', icon: User, active: ['/account', '/orders', '/login', '/register'].some((path) => pathname.startsWith(path)) },
  ]

  return <>
    {/* Keeps the end of every page clear of the bar. */}
    <div aria-hidden="true" className="h-[calc(4rem+env(safe-area-inset-bottom))] md:hidden" />
    <nav aria-label={ar ? 'التنقل السريع' : 'Quick navigation'} className="mobile-tab-bar fixed inset-x-0 bottom-0 z-50 border-t border-border bg-card/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgba(0,0,0,0.06)] backdrop-blur md:hidden print:hidden">
      <ul className="grid h-16 grid-cols-5">
        {tabs.map(({ href, label, icon: Icon, active, count, onClick }) => {
          const inner = <>
            <span className="relative">
              <Icon aria-hidden="true" className={`size-[22px] transition ${active ? 'text-shop-accent' : ''}`} strokeWidth={active ? 2.2 : 1.7} />
              {Boolean(count) && <span aria-hidden="true" className="absolute -end-2.5 -top-1.5 grid min-h-4 min-w-4 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-4 text-white">{count! > 99 ? '99+' : count}</span>}
            </span>
            <span className={`text-[11px] ${active ? 'font-bold text-foreground' : 'text-muted-foreground'}`}>{label}</span>
          </>
          const className = 'flex h-full w-full flex-col items-center justify-center gap-1 text-foreground'
          const name = count ? `${label} (${count})` : label
          return <li key={label}>{href
            ? <Link href={href} aria-label={name} aria-current={active ? 'page' : undefined} className={className}>{inner}</Link>
            : <button type="button" onClick={onClick} aria-label={name} className={className}>{inner}</button>}</li>
        })}
      </ul>
    </nav>
  </>
}
