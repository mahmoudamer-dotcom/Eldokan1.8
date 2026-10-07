'use client'

import { useEffect, useState } from 'react'
import type { Address, AddressCreate, AddressUpdate } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { getEgyptGovernorateLabel, getEgyptGovernorateOptions } from '@/lib/egypt-governorates'

export default function AddressBook() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const api = createEldokanApi(locale)
  const [addresses, setAddresses] = useState<Address[]>([])
  const [states, setStates] = useState<string[]>([])
  const [editing, setEditing] = useState<Address | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function refresh() {
    const result = await api.addresses.list()
    setAddresses(result.data.items)
  }

  useEffect(() => {
    let active = true
    Promise.all([api.addresses.list(), api.checkout.get({ lang: locale })]).then(([book, checkout]) => {
      if (!active) return
      setAddresses(book.data.items)
      setStates(getEgyptGovernorateOptions(checkout.data.address_requirements.state_codes))
    }).catch(() => { if (active) setError(ar ? 'تعذر تحميل دفتر العناوين.' : 'Could not load saved addresses.') })
    return () => { active = false }
  }, [api, locale, ar])

  async function create(formData: FormData) {
    setBusy(true); setError('')
    const input: AddressCreate = {
      first_name: String(formData.get('first_name') ?? '').trim(),
      last_name: String(formData.get('last_name') ?? '').trim(),
      phone: String(formData.get('phone') ?? '').trim(),
      email: String(formData.get('email') ?? '').trim(),
      country: 'EG',
      state: String(formData.get('state') ?? ''),
      city: String(formData.get('city') ?? '').trim(),
      street_address: String(formData.get('street_address') ?? '').trim(),
      address_extra: String(formData.get('address_extra') ?? '').trim() || null,
      is_default: formData.get('is_default') === 'on',
    }
    try { await api.addresses.create(input); await refresh() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save address.') }
    finally { setBusy(false) }
  }

  async function remove(id: Address['id']) {
    setBusy(true); setError('')
    try { await api.addresses.remove(id); await refresh() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not remove address.') }
    finally { setBusy(false) }
  }

  async function update(formData: FormData) {
    if (!editing) return
    setBusy(true); setError('')
    const input: AddressUpdate = {
      first_name: String(formData.get('first_name') ?? '').trim(),
      last_name: String(formData.get('last_name') ?? '').trim(),
      phone: String(formData.get('phone') ?? '').trim(),
      email: String(formData.get('email') ?? '').trim(),
      country: 'EG',
      state: String(formData.get('state') ?? ''),
      city: String(formData.get('city') ?? '').trim(),
      street_address: String(formData.get('street_address') ?? '').trim(),
      address_extra: String(formData.get('address_extra') ?? '').trim() || null,
      is_default: formData.get('is_default') === 'on',
    }
    try { await api.addresses.update(editing.id, input); setEditing(null); await refresh() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update address.') }
    finally { setBusy(false) }
  }

  const field = 'w-full rounded-lg border border-input bg-transparent px-3 py-2'
  return <section className="mt-8 border-t border-border pt-6 dark:border-gray-700">
    <h2 className="text-xl font-bold">{ar ? 'دفتر العناوين' : 'Address book'}</h2>
    <div className="mt-4 space-y-3">{addresses.map((address) => <article key={address.id} className="rounded-xl border border-border p-4 dark:border-gray-700">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="font-semibold">{address.first_name} {address.last_name}{address.is_default ? ` · ${ar ? 'افتراضي' : 'Default'}` : ''}</p><p className="mt-1 text-sm text-muted-foreground">{address.phone} · {address.city}, {getEgyptGovernorateLabel(address.state, locale)}</p><p className="text-sm text-muted-foreground">{address.street_address}{address.address_extra ? ` · ${address.address_extra}` : ''}</p></div>
        <div className="flex gap-2"><button type="button" disabled={busy} onClick={() => setEditing(editing?.id === address.id ? null : address)} className="rounded-lg border px-3 py-2 text-sm disabled:opacity-50">{editing?.id === address.id ? (ar ? 'إلغاء' : 'Cancel') : (ar ? 'تعديل' : 'Edit')}</button><button type="button" disabled={busy} onClick={() => void remove(address.id)} className="rounded-lg border px-3 py-2 text-sm disabled:opacity-50">{ar ? 'حذف' : 'Remove'}</button></div>
      </div>
      {editing?.id === address.id && <form action={update} className="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2">
        <input required name="first_name" defaultValue={address.first_name} maxLength={100} className={field} />
        <input required name="last_name" defaultValue={address.last_name} maxLength={100} className={field} />
        <input required name="phone" defaultValue={address.phone} maxLength={32} className={field} />
        <input required name="email" type="email" defaultValue={address.email} maxLength={254} className={field} />
        <select required name="state" defaultValue={address.state} className={field}>{states.map((state) => <option key={state} value={state}>{getEgyptGovernorateLabel(state, locale)}</option>)}</select>
        <input required name="city" defaultValue={address.city} maxLength={150} className={field} />
        <input required name="street_address" defaultValue={address.street_address} maxLength={500} className={`${field} sm:col-span-2`} />
        <input name="address_extra" defaultValue={address.address_extra ?? ''} maxLength={500} className={`${field} sm:col-span-2`} />
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" defaultChecked={address.is_default} />{ar ? 'العنوان الافتراضي' : 'Default address'}</label>
        <button disabled={busy} className="rounded-lg bg-[#f5b400] text-primary-foreground px-4 py-2 font-semibold disabled:opacity-50">{busy ? (ar ? 'جارٍ الحفظ…' : 'Saving…') : (ar ? 'حفظ التغييرات' : 'Save changes')}</button>
      </form>}
    </article>)}</div>
    {addresses.length >= 20 ? <p className="mt-3 text-sm text-muted-foreground">{ar ? 'وصل دفتر العناوين للحد الأقصى (20).' : 'The address book limit is 20 addresses.'}</p> : <form action={create} className="mt-5 grid gap-3 rounded-xl border border-border p-4 sm:grid-cols-2 dark:border-gray-700">
      <h3 className="sm:col-span-2 font-semibold">{ar ? 'إضافة عنوان' : 'Add an address'}</h3>
      <input required name="first_name" maxLength={100} placeholder={ar ? 'الاسم الأول' : 'First name'} className={field} />
      <input required name="last_name" maxLength={100} placeholder={ar ? 'اسم العائلة' : 'Last name'} className={field} />
      <input required name="phone" maxLength={32} placeholder={ar ? 'هاتف العنوان' : 'Address phone'} className={field} />
      <input required name="email" type="email" maxLength={254} placeholder={ar ? 'البريد الإلكتروني' : 'Email'} className={field} />
      <select required name="state" className={field}><option value="">{ar ? 'اختر المحافظة' : 'Choose governorate'}</option>{states.map((state) => <option key={state} value={state}>{getEgyptGovernorateLabel(state, locale)}</option>)}</select>
      <input required name="city" maxLength={150} placeholder={ar ? 'المدينة' : 'City'} className={field} />
      <input required name="street_address" maxLength={500} placeholder={ar ? 'العنوان بالتفصيل' : 'Street address'} className={`${field} sm:col-span-2`} />
      <input name="address_extra" maxLength={500} placeholder={ar ? 'تفاصيل إضافية (اختياري)' : 'Additional details (optional)'} className={`${field} sm:col-span-2`} />
      <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" />{ar ? 'اجعله العنوان الافتراضي' : 'Set as default'}</label>
      <button disabled={busy} className="rounded-lg bg-[#f5b400] text-primary-foreground px-4 py-2 font-semibold disabled:opacity-50">{busy ? (ar ? 'جارٍ الحفظ…' : 'Saving…') : (ar ? 'حفظ العنوان' : 'Save address')}</button>
    </form>}
    {error && <p role="alert" className="mt-3 text-sm text-danger-foreground">{error}</p>}
  </section>
}
