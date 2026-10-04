'use client'

import { useCallback, useEffect, useState } from 'react'
import { useLocale } from '@/components/i18n/LocaleProvider'

type Invoice = {
  transactionId?: number
  orderId?: number
  paidAt?: string
  amountCents: number
  currency: string
  product: { id: string | number; name: string; sku?: string; imageUrl?: string; quantity: number }
  customer: Record<string, unknown>
}
type Result = { status?: 'paid' | 'pending' | 'failed'; error?: string; invoice?: Invoice }

export default function InvoiceResult({ reference }: { reference: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const t = useCallback((en: string, arabic: string) => ar ? arabic : en, [ar])
  const [result, setResult] = useState<Result | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const verify = useCallback(async () => {
    if (!reference) { setError(t('Missing payment reference.', 'مرجع الدفع غير موجود.')); setLoading(false); return }
    setLoading(true)
    setError('')
    try {
      const response = await fetch(`/api/paymob/verify?reference=${encodeURIComponent(reference)}`, { cache: 'no-store' })
      const body = await response.json() as Result
      if (!response.ok) throw new Error(body.error || t('Unable to verify payment.', 'تعذر التحقق من الدفع.'))
      setResult(body)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('Unable to verify payment.', 'تعذر التحقق من الدفع.'))
    } finally { setLoading(false) }
  }, [reference, t])

  useEffect(() => { void verify() }, [verify])

  const invoice = result?.invoice
  const customer = invoice?.customer ?? {}
  const value = (key: string) => typeof customer[key] === 'string' ? customer[key] as string : ''
  const paidAt = invoice?.paidAt ? new Date(invoice.paidAt).toLocaleString(ar ? 'ar-EG' : 'en') : '—'

  if (loading) return <section dir={ar ? 'rtl' : 'ltr'} className="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm"><p className="text-lg font-semibold">{t('Checking payment with Paymob…', 'جارٍ التحقق من الدفع مع Paymob…')}</p></section>

  if (invoice && result?.status === 'paid') return <section dir={ar ? 'rtl' : 'ltr'} className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-5">
      <div><p className="font-semibold text-emerald-700">{t('Payment confirmed', 'تم تأكيد الدفع')}</p><h1 className="mt-1 text-2xl font-bold">{t('Eldokan invoice', 'فاتورة الدكان')}</h1></div>
      <button type="button" onClick={() => window.print()} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold hover:bg-gray-50">{t('Print invoice', 'طباعة الفاتورة')}</button>
    </div>
    <dl className="mt-5 grid gap-3 text-sm sm:grid-cols-2">
      <div><dt className="text-gray-500">{t('Invoice / order ID', 'رقم الفاتورة / الطلب')}</dt><dd className="font-semibold">{invoice.orderId ?? '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Paymob transaction', 'رقم معاملة Paymob')}</dt><dd className="font-semibold">{invoice.transactionId ?? '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Payment date', 'تاريخ الدفع')}</dt><dd className="font-semibold">{paidAt}</dd></div>
      <div><dt className="text-gray-500">{t('Payment status', 'حالة الدفع')}</dt><dd className="font-semibold text-emerald-700">{t('Paid', 'مدفوع')}</dd></div>
    </dl>
    <h2 className="mt-7 border-b border-gray-100 pb-2 text-lg font-bold">{t('Product', 'المنتج')}</h2>
    <div className="flex items-center gap-4 py-4">
      {invoice.product.imageUrl && <img src={invoice.product.imageUrl} alt="" className="size-20 rounded-lg bg-gray-50 object-contain" />}
      <div className="flex-1"><p className="font-semibold">{invoice.product.name}</p>{invoice.product.sku && <p className="mt-1 text-sm text-gray-500">SKU: {invoice.product.sku}</p>}<p className="mt-1 text-sm text-gray-600">{t('Quantity', 'الكمية')}: {invoice.product.quantity}</p></div>
      <p className="font-bold">{(invoice.amountCents / 100).toLocaleString(ar ? 'ar-EG' : 'en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} {invoice.currency}</p>
    </div>
    <div className="flex justify-between border-t border-gray-100 pt-4 text-lg font-bold"><span>{t('Total paid', 'الإجمالي المدفوع')}</span><span>{(invoice.amountCents / 100).toLocaleString(ar ? 'ar-EG' : 'en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} {invoice.currency}</span></div>
    <h2 className="mt-7 border-b border-gray-100 pb-2 text-lg font-bold">{t('Customer and delivery details', 'بيانات العميل والتوصيل')}</h2>
    <dl className="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
      <div><dt className="text-gray-500">{t('Customer name', 'اسم العميل')}</dt><dd>{[value('first_name'), value('last_name')].filter(Boolean).join(' ') || '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Email', 'البريد الإلكتروني')}</dt><dd className="break-all">{value('email') || '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Phone', 'الهاتف')}</dt><dd>{value('phone_number') || '—'}</dd></div>
      <div><dt className="text-gray-500">{t('City / governorate', 'المدينة / المحافظة')}</dt><dd>{value('city') || '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Street and area', 'الشارع والمنطقة')}</dt><dd>{value('street') || '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Building', 'المبنى')}</dt><dd>{value('building') || '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Apartment / floor', 'الشقة / الدور')}</dt><dd>{[value('apartment'), value('floor')].filter(Boolean).join(' / ') || '—'}</dd></div>
      <div><dt className="text-gray-500">{t('Postal code', 'الرمز البريدي')}</dt><dd>{value('postal_code') || '—'}</dd></div>
      <div className="sm:col-span-2"><dt className="text-gray-500">{t('Delivery notes and map coordinates', 'ملاحظات التوصيل وإحداثيات الخريطة')}</dt><dd>{value('extra_description') || '—'}</dd></div>
    </dl>
  </section>

  return <section dir={ar ? 'rtl' : 'ltr'} className="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
    <h1 className="text-2xl font-bold">{result?.status === 'failed' ? t('Payment was not completed', 'لم تكتمل عملية الدفع') : t('Payment confirmation pending', 'بانتظار تأكيد الدفع')}</h1>
    <p className="mt-3 text-gray-600">{error || (result?.status === 'failed' ? t('Paymob did not confirm a successful payment. No paid invoice has been issued.', 'لم يؤكد Paymob نجاح الدفع، لذلك لم تصدر فاتورة مدفوعة.') : t('Paymob has not confirmed the transaction yet. Check again in a moment.', 'لم يؤكد Paymob العملية بعد. حاول التحقق مرة أخرى بعد قليل.'))}</p>
    <button type="button" onClick={() => void verify()} className="mt-6 rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-gray-950 hover:bg-[#e4a600]">{t('Check payment again', 'تحقق من الدفع مرة أخرى')}</button>
  </section>
}
