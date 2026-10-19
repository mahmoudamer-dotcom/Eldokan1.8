'use client'
import CommercePreferences from '@/components/account/CommercePreferences'

import { customerError } from '@/lib/customer-error'
import Link from 'next/link'
import { Heart, LogOut, Mail, MapPin, PackageCheck, Pencil, Phone, ShoppingBag, UserRound } from 'lucide-react'
import { useRouter } from 'next/navigation'
import { useEffect, useState } from 'react'
import { EldokanClientError, type CustomerAccount } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'
import AddressBook from '@/components/account/AddressBook'

export default function AccountPage() {
  const router = useRouter()
  const { locale, t } = useLocale()
  const [customer, setCustomer] = useState<CustomerAccount | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState(false)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let active = true
    const api = createEldokanApi(locale)
    api.auth.session()
      .then(() => api.account.me())
      .then((response) => { if (active) setCustomer(response.data) })
      .catch((cause: unknown) => {
        if (!active) return
        if (cause instanceof EldokanClientError && cause.status === 401) {
          router.replace('/login')
          return
        }
        setError(customerError(cause, locale, 'Unable to load your account.'))
      })
    return () => { active = false }
  }, [locale, router])

  async function logout() {
    setBusy(true)
    setError('')
    try {
      const request = createEldokanApi(locale).auth.logout()
      window.dispatchEvent(new Event('eldokan:signed-out'))
      router.replace('/login')
      await request
      window.dispatchEvent(new Event('eldokan:session-changed'))
      router.refresh()
    } catch (cause) {
      window.dispatchEvent(new Event('eldokan:session-changed'))
      setError(customerError(cause, locale, 'Unable to sign out.'))
      setBusy(false)
    }
  }

  async function updateAccount(formData: FormData) {
    if (!customer) return

    setSaving(true)
    setError('')
    try {
      const phone = String(formData.get('phone') ?? '').trim()
      if (phone && (!/^\+?[0-9 ()-]+$/.test(phone) || (phone.match(/[0-9]/g)?.length ?? 0) < 6 || phone.length > 32)) {
        setError(t('Enter a valid phone using at least six digits, or leave it empty.'))
        return
      }
      const response = await createEldokanApi(locale).account.update({
        first_name: String(formData.get('first_name') ?? '').trim(),
        last_name: String(formData.get('last_name') ?? '').trim(),
        display_name: String(formData.get('display_name') ?? '').trim(),
        phone,
      })
      setCustomer(response.data)
      window.dispatchEvent(new Event('eldokan:session-changed'))
      setEditing(false)
    } catch (cause) {
      if (cause instanceof EldokanClientError && cause.status === 401) {
        router.replace('/login')
        return
      }
      if (cause instanceof EldokanClientError && cause.code === 'phone_storage_unavailable') {
        try { setCustomer((await createEldokanApi(locale).account.me()).data) } catch { /* Keep the last profile if reload is unavailable. */ }
      }
      setError(customerError(cause, locale, 'Unable to update your account.'))
    } finally {
      setSaving(false)
    }
  }

  const ar = locale === 'ar'
  const fullName = customer ? [customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.display_name || customer.email : ''
  const initials = fullName.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
  const field = 'mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/20'

  return <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:py-12">
    <h1 className="sr-only">{t('My account')}</h1>
    {customer ? <div className="space-y-6">
      <section className="flex flex-wrap items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <span aria-hidden="true" className="grid size-14 shrink-0 place-items-center rounded-full bg-primary text-lg font-extrabold text-primary-foreground">{initials}</span>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-semibold text-muted-foreground">{ar ? 'أهلًا بيك' : 'Welcome back'}</p>
          <p className="truncate text-xl font-bold">{fullName}</p>
          <p className="truncate text-sm text-muted-foreground"><bdi dir="ltr">{customer.email}</bdi></p>
        </div>
        <button type="button" onClick={logout} disabled={busy} className="inline-flex h-10 items-center gap-2 rounded-xl border border-border px-4 text-sm font-semibold text-danger-foreground transition hover:bg-danger-soft disabled:opacity-60">
          <LogOut aria-hidden="true" className={`size-4 ${ar ? 'rotate-180' : ''}`} />{busy ? t('Signing out…') : t('Sign out')}
        </button>
      </section>

      <nav aria-label={ar ? 'اختصارات حسابك' : 'Account shortcuts'} className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {[
          { href: '/account/orders', icon: PackageCheck, title: ar ? 'طلباتي' : 'My orders', description: ar ? 'تابع طلبك أو اطلب إلغاءه' : 'Track or request cancellation' },
          { href: '/wishlist', icon: Heart, title: ar ? 'المفضلة' : 'Favorites', description: ar ? 'اختياراتك محفوظة هنا' : 'Your saved product picks' },
          { href: '/cart', icon: ShoppingBag, title: ar ? 'سلة التسوق' : 'Shopping cart', description: ar ? 'كمّل مشترياتك' : 'Continue your purchase' },
          { href: '#saved-addresses', icon: MapPin, title: ar ? 'عنواني' : 'My address', description: ar ? 'لتوصيل أسهل في كل مرة' : 'Easier delivery next time' },
        ].map(({ href, icon: Icon, title, description }) => <Link key={href} href={href} className="group flex items-start gap-3 rounded-2xl border border-border bg-card p-4 transition hover:border-primary hover:bg-shop-soft">
          <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-shop-soft text-shop-accent transition group-hover:bg-primary group-hover:text-primary-foreground"><Icon aria-hidden="true" className="size-5" /></span>
          <span className="min-w-0"><span className="block font-bold">{title}</span><span className="mt-0.5 block text-xs leading-5 text-muted-foreground">{description}</span></span>
        </Link>)}
      </nav>

      <section className="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-lg font-bold">{ar ? 'بياناتي' : 'Personal details'}</h2>
          {!editing && <button type="button" onClick={() => setEditing(true)} className="inline-flex h-9 items-center gap-2 rounded-xl border border-border px-3 text-sm font-semibold transition hover:bg-muted"><Pencil aria-hidden="true" className="size-4" />{t('Edit profile')}</button>}
        </div>
        {editing ? <form action={updateAccount} className="mt-5 grid gap-4 sm:grid-cols-2">
          <label className="block text-sm font-medium">{t('First name')}<input name="first_name" defaultValue={customer.first_name} autoComplete="given-name" className={field} /></label>
          <label className="block text-sm font-medium">{t('Last name')}<input name="last_name" defaultValue={customer.last_name} autoComplete="family-name" className={field} /></label>
          <label className="block text-sm font-medium">{t('Display name')}<input name="display_name" defaultValue={customer.display_name} autoComplete="nickname" className={field} /></label>
          <label className="block text-sm font-medium">{ar ? 'رقم الموبايل' : 'Mobile number'}<input name="phone" type="tel" dir="ltr" maxLength={32} defaultValue={customer.phone} autoComplete="tel" placeholder="01XXXXXXXXX" className={field} /></label>
          <div className="flex flex-wrap gap-3 sm:col-span-2">
            <button type="submit" disabled={saving} className="h-11 rounded-xl bg-primary px-5 font-semibold text-primary-foreground transition hover:brightness-95 disabled:opacity-60">{saving ? t('Saving…') : t('Save changes')}</button>
            <button type="button" onClick={() => { setEditing(false); setError('') }} className="h-11 rounded-xl border border-border px-5 font-semibold transition hover:bg-muted">{t('Cancel')}</button>
          </div>
        </form> : <dl className="mt-4 grid gap-3 sm:grid-cols-3">
          {[
            { icon: UserRound, label: t('Name'), value: fullName, ltr: false },
            { icon: Mail, label: t('Email'), value: customer.email, ltr: true },
            { icon: Phone, label: ar ? 'رقم الموبايل' : 'Mobile number', value: customer.phone, ltr: true },
          ].map(({ icon: Icon, label, value, ltr }) => <div key={label} className="flex min-w-0 items-start gap-3 rounded-xl bg-muted/50 p-3">
            <Icon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0">
              <dt className="text-xs text-muted-foreground">{label}</dt>
              <dd className="mt-0.5 truncate text-sm font-semibold">{value ? <bdi dir={ltr ? 'ltr' : 'auto'}>{value}</bdi> : <button type="button" onClick={() => setEditing(true)} className="font-semibold text-shop-accent hover:underline">{ar ? '+ أضف رقمك' : '+ Add number'}</button>}</dd>
            </div>
          </div>)}
        </dl>}
        {error && <p role="alert" className="mt-4 text-sm text-danger-foreground">{error}</p>}
      </section>

      {/* These sections bring their own top divider; inside a card it is not needed. */}
      <div className="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6 [&>section]:mt-0 [&>section]:border-t-0 [&>section]:pt-0">
        <AddressBook />
      </div>
      <div className="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6 [&>section]:mt-0 [&>section]:border-t-0 [&>section]:pt-0">
        <CommercePreferences />
      </div>
    </div> : <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
      {error ? <><p role="alert" className="text-sm text-danger-foreground">{error}</p><p className="mt-4 text-sm"><Link className="font-semibold text-shop-accent hover:underline" href="/login">{t('Sign in')}</Link> {t('to view your account.')}</p></> : <p className="text-sm text-muted-foreground">{t('Loading your account…')}</p>}
    </section>}
  </main>
}
