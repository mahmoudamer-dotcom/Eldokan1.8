'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useEffect, useRef, useState } from 'react'
import { ChevronDown, Heart, LogOut, MapPin, PackageCheck, ShoppingBag, UserRound } from 'lucide-react'
import type { CustomerAccount } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'

export default function AccountShortcuts({ customer }: { customer: CustomerAccount | null }) {
  const { locale, t } = useLocale()
  const [open, setOpen] = useState(false)
  const [signingOut, setSigningOut] = useState(false)
  const router = useRouter()
  const container = useRef<HTMLDivElement>(null)
  const trigger = useRef<HTMLButtonElement>(null)
  useEffect(() => {
    if (!open) return
    const outside = (event: PointerEvent) => {
      if (!container.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('pointerdown', outside)
    return () => document.removeEventListener('pointerdown', outside)
  }, [open])
  async function logout() {
    if (signingOut) return
    setSigningOut(true)
    // Respond at once: hide the account and leave the page while the store ends the session.
    const request = createEldokanApi(locale).auth.logout()
    setOpen(false)
    window.dispatchEvent(new Event('eldokan:signed-out'))
    router.replace('/')
    try { await request } catch { /* session-changed below restores the real state. */ }
    window.dispatchEvent(new Event('eldokan:session-changed'))
    router.refresh()
    setSigningOut(false)
  }
  const shortcuts = [
    // Guests only see what works without an account; their orders live in this browser.
    ...(customer ? [{ href: '/account', icon: UserRound, label: t('My account') }] : []),
    { href: customer ? '/account/orders' : '/orders', icon: PackageCheck, label: locale === 'ar' ? 'طلباتي ومتابعتها' : 'My orders & tracking' },
    ...(customer ? [{ href: '/account#saved-addresses', icon: MapPin, label: locale === 'ar' ? 'عنواني' : 'My address' }] : []),
    { href: '/wishlist', icon: Heart, label: t('Favorites') },
    { href: '/cart', icon: ShoppingBag, label: t('Shopping cart') },
  ]
  return <div ref={container} className="relative shrink-0"
    onPointerEnter={event => { if (event.pointerType === 'mouse') setOpen(true) }}
    onPointerLeave={event => { if (event.pointerType === 'mouse') setOpen(false) }}
    onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false) }}
    onKeyDown={event => { if (event.key === 'Escape') { setOpen(false); trigger.current?.focus() } }}>
    <button ref={trigger} type="button" aria-expanded={open} aria-controls="navbar-account-shortcuts"
      aria-label={t('Account')} onClick={() => setOpen(value => !value)}
      className="flex h-9 max-w-10 items-center justify-center gap-1 rounded-full px-1 text-foreground transition hover:bg-muted hover:text-shop-accent sm:h-10 sm:max-w-40 sm:px-2">
      <UserRound className="size-5 shrink-0" aria-hidden="true" />
      {customer && <span className="hidden max-w-24 truncate text-sm font-semibold sm:inline">{customer.display_name || customer.first_name || t('Account')}</span>}
      <ChevronDown className={`hidden size-3.5 transition-transform sm:block ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
    </button>
    {open && <div className="absolute end-0 top-full z-[60] w-72 max-w-[calc(100vw-1.5rem)] pt-2">
      <nav id="navbar-account-shortcuts" aria-label={locale === 'ar' ? 'اختصارات الحساب' : 'Account shortcuts'} className="rounded-2xl border border-border bg-card p-2 text-card-foreground shadow-xl">
        <div className="mb-1 border-b border-border px-3 py-3">
          <p className="truncate text-sm font-bold">{customer ? customer.display_name || customer.first_name || t('My account') : locale === 'ar' ? 'أهلًا بيك في الدكان' : 'Welcome to Eldokan'}</p>
          {!customer && <>
            <p className="mt-1 text-xs leading-5 text-muted-foreground">{locale === 'ar' ? 'سجّل دخولك عشان تتابع طلباتك وعناوينك.' : 'Sign in to track orders and save addresses.'}</p>
            <div className="mt-3 grid grid-cols-2 gap-2">
              <Link href="/login" onClick={() => setOpen(false)} className="flex h-10 items-center justify-center whitespace-nowrap rounded-xl bg-primary px-3 text-sm font-bold text-primary-foreground transition hover:brightness-95">{t('Sign in')}</Link>
              <Link href="/register" onClick={() => setOpen(false)} className="flex h-10 items-center justify-center whitespace-nowrap rounded-xl border border-border px-3 text-sm font-bold transition hover:bg-muted">{locale === 'ar' ? 'حساب جديد' : 'Register'}</Link>
            </div>
          </>}
        </div>
        {shortcuts.map(({ href, icon: Icon, label }) => <Link key={href} href={href} onClick={() => setOpen(false)} className="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-shop-soft hover:text-shop-accent focus-visible:bg-muted focus-visible:outline-2 focus-visible:outline-primary">
          <Icon className="size-4 shrink-0" aria-hidden="true" />{label}
        </Link>)}
        {customer && <button type="button" onClick={() => void logout()} disabled={signingOut} className="mt-1 flex min-h-11 w-full items-center gap-3 rounded-xl border-t border-border px-3 py-2 text-start text-sm font-medium text-danger-foreground transition hover:bg-danger-soft focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-60">
          <LogOut className={`size-4 shrink-0 ${locale === 'ar' ? 'rotate-180' : ''}`} aria-hidden="true" />{signingOut ? (locale === 'ar' ? 'جارٍ تسجيل الخروج…' : 'Signing out…') : (locale === 'ar' ? 'تسجيل الخروج' : 'Sign out')}
        </button>}
      </nav>
    </div>}
  </div>
}
