'use client'

import { useEffect, useState } from 'react'
import { Pencil } from 'lucide-react'
import type { OrderDetail } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { getEgyptGovernorateLabel } from '@/lib/egypt-governorates'

type EditPolicy = { available: boolean }
const FIELDS = ['first_name', 'last_name', 'phone', 'city', 'street_address', 'address_extra'] as const

/** Change delivery details before fulfillment. Items, payment and totals are not editable. */
export default function OrderEdit({ order, busy, onBusy, onRefresh }: {
  order: OrderDetail; busy: boolean; onBusy: (value: boolean) => void; onRefresh: () => Promise<unknown>
}) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [open, setOpen] = useState(false)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)
  const [signedIn, setSignedIn] = useState(false)
  useEffect(() => {
    let active = true
    createEldokanApi(locale).auth.session().then(() => {
      if (!active) return
      setSignedIn(true)
      if (window.location.hash === '#edit-order') { setOpen(true); window.setTimeout(() => document.getElementById('edit-order')?.scrollIntoView({ block: 'start' }), 50) }
    }, () => {})
    return () => { active = false }
  }, [locale])
  const policy = (order as OrderDetail & { edit?: EditPolicy }).edit
  if (!policy?.available || !signedIn) return null

  const labels: Record<(typeof FIELDS)[number], string> = {
    first_name: ar ? 'الاسم الأول' : 'First name', last_name: ar ? 'اسم العائلة' : 'Last name', phone: ar ? 'رقم الموبايل' : 'Mobile number',
    city: ar ? 'المدينة أو المنطقة' : 'City or district', street_address: ar ? 'العنوان بالتفصيل' : 'Street address', address_extra: ar ? 'علامة مميزة (اختياري)' : 'Nearby landmark (optional)',
  }

  async function save(form: FormData) {
    if (busy) return
    onBusy(true); setError(''); setSaved(false)
    const value = (name: string) => String(form.get(name) ?? '').trim()
    const address = {
      first_name: value('first_name'), last_name: value('last_name'), phone: value('phone'), email: order.address.email,
      country: 'EG', state: order.address.state, city: value('city'), street_address: value('street_address'), address_extra: value('address_extra') || null,
    }
    try {
      const session = await createEldokanApi(locale).auth.session()
      const response = await fetch(`/api/customer/orders/${encodeURIComponent(order.id)}/update?lang=${locale}`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-ElDokan-CSRF': session.data.csrf_token },
        body: JSON.stringify({ address, order_notes: value('order_notes') }),
      })
      const result = await response.json() as { success?: boolean; error?: { code?: string; message?: string } }
      if (!response.ok || !result.success) {
        throw new Error(result.error?.code === 'order_edit_unavailable'
          ? (ar ? 'الطلب مبقاش ينفع يتعدل من الموقع لأنه بدأ يتجهز. كلم الدعم.' : 'This order can no longer be changed online. Contact support.')
          : result.error?.code === 'invalid_address'
            ? (ar ? 'راجع البيانات: رقم الموبايل لازم يكون صحيح وكل الخانات المطلوبة متملية.' : 'Check the details: a valid phone and all required fields are needed.')
            : (ar ? 'مقدرناش نحفظ التعديل. حدّث الصفحة وحاول تاني.' : 'Could not save your changes. Refresh and try again.'))
      }
      setOpen(false); setSaved(true)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : (ar ? 'مقدرناش نحفظ التعديل.' : 'Could not save your changes.'))
    } finally {
      onBusy(false)
      await onRefresh()
    }
  }

  const input = 'mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/20'
  return <section id="edit-order" className="mt-5 scroll-mt-32 rounded-xl border border-border bg-card p-4 print:hidden">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 className="font-semibold">{ar ? 'تعديل بيانات التوصيل' : 'Change delivery details'}</h2>
        <p className="mt-1 text-xs leading-5 text-muted-foreground">{ar ? 'تقدر تعدّل العنوان والتليفون والملاحظات لحد ما الطلب يبدأ يتجهز.' : 'You can change the address, phone and notes until the order starts being prepared.'}</p>
      </div>
      {!open && <button type="button" disabled={busy} onClick={() => { setOpen(true); setSaved(false) }} className="inline-flex h-10 items-center gap-2 rounded-lg border border-border px-4 text-sm font-semibold transition hover:bg-muted disabled:opacity-50"><Pencil aria-hidden="true" className="size-4" />{ar ? 'تعديل الطلب' : 'Edit order'}</button>}
    </div>
    {saved && <p role="status" className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{ar ? 'تم حفظ التعديل.' : 'Your changes were saved.'}</p>}
    {open && <form action={save} className="mt-4 grid gap-4 sm:grid-cols-2">
      {FIELDS.map((name) => <label key={name} className={`block text-sm font-medium ${name === 'street_address' || name === 'address_extra' ? 'sm:col-span-2' : ''}`}>{labels[name]}
        <input name={name} required={name !== 'address_extra'} defaultValue={order.address[name] ?? ''} dir={name === 'phone' ? 'ltr' : undefined} type={name === 'phone' ? 'tel' : 'text'} maxLength={name === 'phone' ? 32 : name === 'city' ? 150 : name.endsWith('name') ? 100 : 500} className={input} />
      </label>)}
      <div className="text-sm sm:col-span-2">
        <span className="font-medium">{ar ? 'المحافظة' : 'Governorate'}</span>
        <p className="mt-1 rounded-lg bg-muted/50 px-3 py-2.5">{getEgyptGovernorateLabel(order.address.state, locale)} <span className="text-xs text-muted-foreground">— {ar ? 'مينفعش تتغير لأن الشحن اتحسب عليها. لو عايز محافظة تانية ألغي الطلب واطلب من جديد.' : 'can’t change because shipping was priced for it. For another governorate, cancel and order again.'}</span></p>
      </div>
      <label className="block text-sm font-medium sm:col-span-2">{ar ? 'ملاحظات الطلب (اختياري)' : 'Order notes (optional)'}
        <textarea name="order_notes" maxLength={1000} defaultValue={order.order_notes ?? ''} className="mt-1 min-h-20 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-normal outline-none focus:border-primary" />
      </label>
      <div className="flex flex-wrap gap-3 sm:col-span-2">
        <button type="submit" disabled={busy} className="h-11 rounded-lg bg-primary px-5 font-semibold text-primary-foreground disabled:opacity-50">{busy ? (ar ? 'جارٍ الحفظ…' : 'Saving…') : (ar ? 'حفظ التعديل' : 'Save changes')}</button>
        <button type="button" disabled={busy} onClick={() => { setOpen(false); setError('') }} className="h-11 rounded-lg border border-border px-5 font-semibold">{ar ? 'إلغاء' : 'Cancel'}</button>
      </div>
    </form>}
    {error && <p role="alert" className="mt-3 text-sm text-danger-foreground">{error}</p>}
  </section>
}
