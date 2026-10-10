'use client'

import { useState } from 'react'
import Link from 'next/link'
import { EldokanClientError, type ProductId } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'

export default function ProductAlertForm({ productId, inStock }: { productId: ProductId; inStock: boolean }) {
  const { locale } = useLocale(); const ar = locale === 'ar'
  const [busy, setBusy] = useState(false); const [message, setMessage] = useState(''); const [opened, setOpened] = useState(false)
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (busy) return
    const fields = new FormData(event.currentTarget); setBusy(true); setMessage('')
    try { const api = createEldokanApi(locale); await api.auth.session(); await api.commerce.saveAlert({ product_id: productId, kind: inStock ? 'price' : 'stock', ...(inStock ? { target_price: Number(fields.get('price')) } : {}) }); setMessage(ar ? 'تم حفظ التنبيه. هيوصلك على البريد المسجل بحسابك.' : 'Alert saved. We will email your account when it matches.') }
    catch (cause) {
      const code = cause instanceof EldokanClientError ? cause.code : ''
      setMessage(code === 'alert_price_not_lower' ? (ar ? 'اختار سعرًا أقل من سعر المنتج الحالي.' : 'Choose a target below the current price.') : code === 'alert_already_in_stock' ? (ar ? 'المنتج متوفر بالفعل. حدّث الصفحة.' : 'This product is already in stock. Please refresh.') : (ar ? 'تعذر حفظ التنبيه. تأكد من تسجيل الدخول وحاول مرة أخرى.' : 'Could not save the alert. Make sure you are signed in and try again.'))
    } finally { setBusy(false) }
  }
  return <section className="mt-5 rounded-xl border p-4"><button type="button" aria-expanded={opened} onClick={() => setOpened(!opened)} className="text-sm font-semibold">{inStock ? (ar ? 'نبّهني لو السعر نزل' : 'Notify me when the price drops') : (ar ? 'نبّهني لما يتوفر' : 'Notify me when back in stock')}</button>{opened && <form onSubmit={submit} className="mt-3 flex flex-wrap items-end gap-3">{inStock && <label className="text-sm">{ar ? 'السعر المستهدف بالجنيه' : 'Target price in EGP'}<input required name="price" type="number" min="0.01" max="100000000" step="0.01" className="mt-2 block w-40 rounded-lg border bg-background p-2" /></label>}<button disabled={busy} className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">{ar ? 'حفظ التنبيه' : 'Save alert'}</button><Link href="/account" className="text-sm underline">{ar ? 'إدارة التنبيهات' : 'Manage alerts'}</Link></form>}{message && <p role="status" className="mt-3 text-sm text-muted-foreground">{message}</p>}</section>
}
