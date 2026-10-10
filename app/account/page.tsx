'use client'
import CommercePreferences from '@/components/account/CommercePreferences'

import { customerError } from '@/lib/customer-error'
import Link from 'next/link'
import { Heart, MapPin, PackageCheck, ShoppingBag } from 'lucide-react'
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
      await createEldokanApi(locale).auth.logout()
      window.dispatchEvent(new Event('eldokan:session-changed'))
      router.replace('/login')
      router.refresh()
    } catch (cause) {
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

  return <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:py-12">
    <section className="rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-8">
      <h1 className="text-2xl font-bold">{t('My account')}</h1>
      {customer ? <>
        <p className="mt-2 text-muted-foreground">{t('Signed in as')} {customer.display_name || customer.email}</p>
        <nav aria-label={locale === 'ar' ? 'اختصارات حسابك' : 'Account shortcuts'} className="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
          {[
            { href: '/account/orders', icon: PackageCheck, title: locale === 'ar' ? 'طلباتي' : 'My orders', description: locale === 'ar' ? 'تابع طلبك أو اطلب إلغاءه' : 'Track or request cancellation' },
            { href: '/wishlist', icon: Heart, title: locale === 'ar' ? 'المفضلة' : 'Favorites', description: locale === 'ar' ? 'اختياراتك محفوظة هنا' : 'Your saved product picks' },
            { href: '/cart', icon: ShoppingBag, title: locale === 'ar' ? 'سلة التسوق' : 'Shopping cart', description: locale === 'ar' ? 'كمّل مشترياتك' : 'Continue your purchase' },
            { href: '#saved-addresses', icon: MapPin, title: locale === 'ar' ? 'عناويني' : 'My addresses', description: locale === 'ar' ? 'لتوصيل أسهل في كل مرة' : 'Easier delivery next time' },
          ].map(({ href, icon: Icon, title, description }) => <Link key={href} href={href} className="rounded-xl border border-border bg-background p-4 transition hover:border-primary hover:bg-shop-soft"><Icon aria-hidden="true" className="mb-3 size-5 text-shop-accent" /><span className="block font-bold">{title}</span><span className="mt-1 block text-xs text-muted-foreground">{description}</span></Link>)}
        </nav>
        {editing ? <form action={updateAccount} className="mt-7 space-y-4 rounded-xl border border-border p-4 dark:border-gray-700">
          <label className="block text-sm font-medium">{t('First name')}<input name="first_name" defaultValue={customer.first_name} autoComplete="given-name" className="mt-1 w-full rounded-lg border border-input bg-transparent px-3 py-2 font-normal dark:border-gray-600" /></label>
          <label className="block text-sm font-medium">{t('Last name')}<input name="last_name" defaultValue={customer.last_name} autoComplete="family-name" className="mt-1 w-full rounded-lg border border-input bg-transparent px-3 py-2 font-normal dark:border-gray-600" /></label>
          <label className="block text-sm font-medium">{t('Display name')}<input name="display_name" defaultValue={customer.display_name} autoComplete="nickname" className="mt-1 w-full rounded-lg border border-input bg-transparent px-3 py-2 font-normal dark:border-gray-600" /></label>
          <label className="block text-sm font-medium">{t('Phone')}<input name="phone" type="tel" maxLength={32} defaultValue={customer.phone} autoComplete="tel" className="mt-1 w-full rounded-lg border border-input bg-transparent px-3 py-2 font-normal dark:border-gray-600" /><span className="mt-1 block text-xs text-muted-foreground">{t('Optional. Leave empty to clear; at least six digits when provided.')}</span></label>
          <div className="flex flex-wrap gap-3">
            <button type="submit" disabled={saving} className="rounded-lg bg-[#222] px-5 py-3 font-semibold text-white hover:bg-[#444] disabled:opacity-60">{saving ? t('Saving…') : t('Save changes')}</button>
            <button type="button" onClick={() => { setEditing(false); setError('') }} className="rounded-lg border border-input px-5 py-3 font-semibold dark:border-gray-600">{t('Cancel')}</button>
          </div>
        </form> : <dl className="mt-7 divide-y divide-border rounded-xl border border-border px-4 dark:divide-gray-700 dark:border-gray-700">
          <div className="grid gap-1 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><dt className="text-sm text-muted-foreground">{t('Name')}</dt><dd>{[customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.display_name}</dd></div>
          <div className="grid gap-1 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><dt className="text-sm text-muted-foreground">{t('Email')}</dt><dd>{customer.email}</dd></div>
          <div className="grid gap-1 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><dt className="text-sm text-muted-foreground">{t('Phone')}</dt><dd>{customer.phone || '—'}</dd></div>
          <div className="grid gap-1 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><dt className="text-sm text-muted-foreground">{t('Customer ID')}</dt><dd className="break-all">{customer.id}</dd></div>
        </dl>}
        {error && <p role="alert" className="mt-4 text-sm text-danger-foreground">{error}</p>}
        {!editing && <div className="mt-6 flex flex-wrap gap-3">
          <button type="button" onClick={() => setEditing(true)} className="rounded-lg border border-input px-5 py-3 font-semibold dark:border-gray-600">{t('Edit profile')}</button>
          <button type="button" onClick={logout} disabled={busy} className="rounded-lg bg-[#222] px-5 py-3 font-semibold text-white hover:bg-[#444] disabled:opacity-60">{busy ? t('Signing out…') : t('Sign out')}</button>
        </div>}
        <AddressBook />
        <CommercePreferences />
      </> : error ? <div className="mt-5"><p role="alert" className="text-sm text-danger-foreground">{error}</p><p className="mt-4 text-sm"><Link className="font-semibold text-shop-accent hover:underline" href="/login">{t('Sign in')}</Link> {t('to view your account.')}</p></div> : <p className="mt-5 text-sm text-muted-foreground">{t('Loading your account…')}</p>}
    </section>
  </main>
}
