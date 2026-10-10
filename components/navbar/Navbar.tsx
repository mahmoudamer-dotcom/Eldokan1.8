'use client'

import { formatMoney } from '@/lib/format-money'
import Image from 'next/image'
import Link from 'next/link'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { useEffect, useState } from 'react'
import { CiHeart } from 'react-icons/ci'
import { GitCompareArrows, Moon, Sun } from 'lucide-react'
import { IoLanguageOutline } from 'react-icons/io5'
import { PiShoppingCartLight } from 'react-icons/pi'
import { EldokanClientError, type CustomerAccount } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useWishlist } from '@/components/wishlist/WishlistProvider'
import { useCart } from '@/components/cart/CartProvider'
import type { Locale } from '@/lib/i18n'
import { createEldokanApi } from '@/lib/eldokan-api'
import AnnouncementTopBar from './AnnouncementTopBar'
import AccountShortcuts from './AccountShortcuts'
import { useShoppingChat } from '@/components/discovery/ShoppingChatProvider'
import { useProductComparison } from '@/lib/use-product-comparison'
import { useComparisonDrawer } from '@/components/productDetails/ComparisonProvider'

type SearchResult = {
  id?: string | number
  name?: string
  title?: string
  slug?: string
  image?: { url?: string }
  pricing?: {
    on_sale?: boolean
    sale_price?: { formatted?: string }
    regular_price?: { formatted?: string }
  }
}

export default function Navbar() {
  const router = useRouter()
  const { locale, setLocale, theme, setTheme, t } = useLocale()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const urlQuery = searchParams.get('q') ?? ''
  const [query, setQuery] = useState(urlQuery)
  const [results, setResults] = useState<SearchResult[]>([])
  const [isSearching, setIsSearching] = useState(false)
  const [hasSearched, setHasSearched] = useState(false)
  const [isNavigatingToSearch, setIsNavigatingToSearch] = useState(false)
  const [customer, setCustomer] = useState<CustomerAccount | null>(null)
  const [hydrated, setHydrated] = useState(false)
  const { products: wishlistProducts } = useWishlist()
  const { itemCount: cartItemCount } = useCart()
  const comparisonIds = useProductComparison()
  const showComparison = useComparisonDrawer()
  const showShoppingChat = useShoppingChat()
  const wishlistCount = hydrated ? wishlistProducts.length : 0
  const visibleCartItemCount = hydrated ? cartItemCount : 0

  useEffect(() => {
    queueMicrotask(() => setHydrated(true))
  }, [])

  useEffect(() => {
    let active = true
    const api = createEldokanApi(locale)
    const loadSession = () => {
      api.auth.session()
        .then((response) => {
          if (active) setCustomer(response.data.customer)
        })
        .catch((cause: unknown) => {
          if (active && cause instanceof EldokanClientError && cause.status === 401) {
            setCustomer(null)
          }
        })
    }

    loadSession()
    window.addEventListener('eldokan:session-changed', loadSession)
    return () => {
      active = false
      window.removeEventListener('eldokan:session-changed', loadSession)
    }
  }, [locale])

  useEffect(() => {
    queueMicrotask(() => {
      setQuery(urlQuery)
      setIsNavigatingToSearch(false)
    })
  }, [urlQuery])

  useEffect(() => {
    queueMicrotask(() => setIsNavigatingToSearch(false))
  }, [pathname])

  useEffect(() => {
    const searchTerm = query.trim()
    let isCurrentSearch = true
    const controller = new AbortController()

    if (searchTerm.length < 2) {
      queueMicrotask(() => {
        setResults([])
        setHasSearched(false)
        setIsSearching(false)
      })
      return
    }

    queueMicrotask(() => setIsSearching(true))
    const timeout = window.setTimeout(async () => {
      try {
        const searchUrl = `/api/search?q=${encodeURIComponent(searchTerm)}&lang=${locale}`
        const searchResponse = await fetch(searchUrl, { signal: controller.signal })
        if (!searchResponse.ok) throw new Error('Search request failed')
        const response = await searchResponse.json()
        if (isCurrentSearch) {
          setResults(Array.isArray(response?.data) ? response.data : [])
          setHasSearched(true)
        }
      } catch {
        if (isCurrentSearch) {
          setResults([])
          setHasSearched(true)
        }
      } finally {
        if (isCurrentSearch) setIsSearching(false)
      }
    }, 150)

    return () => {
      isCurrentSearch = false
      window.clearTimeout(timeout)
      controller.abort()
    }
  }, [query, locale])

  function handleSearchSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const searchTerm = query.trim()
    if (searchTerm.length < 2) return

    setResults([])
    setHasSearched(false)
    setIsNavigatingToSearch(true)
    router.push(`/search?q=${encodeURIComponent(searchTerm)}`)
  }

  function handleLanguageChange() {
    const nextLocale: Locale = locale === 'en' ? 'ar' : 'en'
    setLocale(nextLocale)
    router.refresh()
  }

  return (
    <div className="bg-card">
      <AnnouncementTopBar
        announcements={[
          { text: t('Welcome to Eldokan') },
          { text: t('Great deals on your favorite products') },
          { text: t('Shop with us today') },
        ]}
      />
      <div className="border-b border-border shadow-sm">
          <nav aria-label={t('Main navigation')} className="mx-auto grid max-w-7xl grid-cols-[auto_minmax(0,1fr)] items-center gap-x-2 gap-y-3 px-3 py-2.5 sm:gap-x-4 sm:px-6 sm:py-3 lg:grid-cols-[auto_minmax(16rem,1fr)_auto] lg:gap-10 lg:px-8">
          <Link href="/" className="flex w-fit items-center" aria-label={t('Eldokan home')}>
            <Image src="/image/Eldokan-logo.webp" alt="Eldokan" width={132} height={72} priority className="h-10 w-14 object-contain min-[360px]:w-auto sm:h-14 dark:mix-blend-screen dark:invert" />
          </Link>

          <form onSubmit={handleSearchSubmit} className="relative col-span-2 row-start-2 w-full lg:col-span-1 lg:row-start-auto">
            <label htmlFor="navbar-product-search" className="sr-only">{t('Search products')}</label>
            <input
              id="navbar-product-search"
              type="search"
              placeholder={t('Search for products...')}
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              className="h-11 w-full min-w-0 rounded-full border border-input bg-background py-2 ps-4 pe-24 text-sm outline-none transition focus:border-[#C58A36] focus:bg-card focus:ring-2 focus:ring-[#C58A36]/20"
            />
            <button type="submit" className="absolute end-1 top-1 h-9 rounded-full bg-[#222222] px-5 text-sm font-medium text-white transition hover:bg-[#3b3b3b] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C58A36]">
              {t('Search')}
            </button>

            {query.trim() !== '' && pathname !== '/search' && !isNavigatingToSearch && (
              <div className="absolute z-50 mt-2 max-h-[70vh] w-full overflow-y-auto rounded-2xl border border-border bg-card shadow-xl">
              {query.trim().length < 2 ? (
                <p className="p-3 text-sm text-muted-foreground">{t('Type at least 2 characters to search.')}</p>
              ) : isSearching ? (
                <p className="p-3 text-sm text-muted-foreground">{t('Searching products...')}</p>
              ) : results.length > 0 ? (
                <ul>
                  {results.map((result, index) => {
                    const name = result.name ?? result.title ?? t('Product')
                    const price = result.pricing?.on_sale
                      ? formatMoney(result.pricing.sale_price, locale)
                      : formatMoney(result.pricing?.regular_price, locale)

                    return (
                      <li key={result.id ?? result.slug ?? index} className="border-b last:border-0">
                        {result.id ? (
                          <Link
                            href={`/product/${result.id}`}
                            className="flex items-center gap-3 p-3 hover:bg-background"
                            onClick={() => setQuery('')}
                          >
                            {result.image?.url && (
                              <Image src={result.image.url} alt="" width={48} height={48} className="h-12 w-12 rounded-2xl object-cover" />
                            )}
                            <span className="min-w-0 flex-1 truncate">{name}</span>
                            {price && <span className="text-sm text-muted-foreground">{price}</span>}
                          </Link>
                        ) : (
                          <span className="block p-3">{name}</span>
                        )}
                      </li>
                    )
                  })}
                </ul>
              ) : hasSearched ? (
                <div className="p-3"><p className="text-sm text-muted-foreground">{t('No matching products found.')}</p><button type="button" onClick={() => { showShoppingChat({ keywords: query.trim().slice(0, 100) }); setQuery('') }} className="mt-3 inline-block rounded-lg border border-border bg-card px-3 py-2 text-sm font-semibold text-card-foreground">{locale === 'ar' ? 'اسأل الدكان يساعدك تختار' : 'Ask Eldokan to help you choose'}</button></div>
              ) : null}
              </div>
            )}
          </form>

          <div className="category-scrollbar-hidden col-start-2 row-start-1 flex min-w-0 max-w-full flex-wrap items-center justify-end gap-0 min-[360px]:gap-0.5 sm:gap-2 lg:col-start-auto lg:row-start-auto lg:flex-nowrap">
            <button type="button" onClick={handleLanguageChange} aria-label={t('Change language')} title={t(locale === 'en' ? 'Switch to Arabic' : 'Switch to English')} className="flex h-9 shrink-0 items-center justify-center gap-1 rounded-full px-1.5 text-xs font-semibold text-foreground transition hover:bg-muted sm:h-10 sm:px-2 sm:text-sm">
              <IoLanguageOutline /><span>{locale === 'en' ? 'عربي' : 'EN'}</span>
            </button>
            <button type="button" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')} aria-label={t(theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode')} title={t(theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode')} className="flex size-8 shrink-0 min-[360px]:size-9 items-center justify-center rounded-full text-foreground transition hover:bg-muted sm:size-10">
              {theme === 'dark' ? <Sun className="size-5" /> : <Moon className="size-5" />}
            </button>
            <Link href="/wishlist" aria-label={`${t('Favorites')}${wishlistCount ? ` (${wishlistCount})` : ''}`} title={t('Favorites')} className="flex size-8 shrink-0 min-[360px]:size-9 items-center justify-center rounded-full text-2xl text-foreground transition hover:bg-muted hover:text-[#C58A36] sm:size-10">
              <span className="relative"><CiHeart /><CountBadge count={wishlistCount} /></span>
            </Link>
            <button type="button" onClick={() => showComparison()} aria-haspopup="dialog" aria-label={`${locale === 'ar' ? 'مقارنة المنتجات' : 'Compare products'}${comparisonIds.length ? ` (${comparisonIds.length})` : ''}`} className="flex h-8 min-w-8 shrink-0 items-center justify-center gap-2 rounded-full px-1 text-foreground transition hover:bg-muted hover:text-[#C58A36] min-[360px]:h-9 min-[360px]:min-w-9 sm:h-10 sm:min-w-10 sm:px-2">
              <span className="relative"><GitCompareArrows className="size-5" aria-hidden="true" /><CountBadge count={comparisonIds.length} /></span>
     
            </button>
          <Link href="/cart" aria-label={`${t('Shopping cart')}${visibleCartItemCount ? ` (${visibleCartItemCount})` : ''}`} title={t('Shopping cart')} className="flex size-8 shrink-0 min-[360px]:size-9 items-center justify-center rounded-full text-2xl text-foreground transition hover:bg-muted hover:text-[#C58A36] sm:size-10">
              <span className="relative"><PiShoppingCartLight /><CountBadge count={visibleCartItemCount} /></span>
            </Link>
            <AccountShortcuts customer={customer} />
          </div>
        </nav>
      </div>
    </div>
  )
}

function CountBadge({ count }: { count: number }) {
  if (!count) return null
  return (
    <span aria-hidden="true" className="absolute -end-2 -top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-4 text-white">
      {count > 99 ? '99+' : count}
    </span>
  )
}
