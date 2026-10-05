'use client'

import { useState, type FormEvent } from 'react'
import GoogleMapPicker from './GoogleMapPicker'

type Point = { lat: number; lng: number }
type Product = { id: string; name: string; sku?: string; imageUrl?: string; amount: number; currency?: string; decimals?: number; formattedAmount: string; unavailable: boolean; quantity?: number; maxQuantity?: number }
type CheckoutLine = { productId: string; cartItemId?: string; stockQuantity?: number | null; name: string; sku?: string; imageUrl?: string; quantity: number; formattedAmount: string }
type Props = { locale: 'ar' | 'en'; product?: Product; items?: CheckoutLine[]; totalFormatted?: string; expectedAmountCents?: number; cartCheckout?: boolean; cartValid?: boolean; onCartQuantityChange?: (cartItemId: string, quantity: number) => Promise<void>; cartBusy?: boolean; googleMapsApiKey?: string }

function clampQuantity(value: string, maxQuantity?: number) {
  const parsed = Math.floor(Number(value))
  return Number.isFinite(parsed) ? Math.max(1, Math.min(maxQuantity ?? 999, parsed)) : 1
}

function formatProductTotal(product: Product, quantity: number, locale: 'ar' | 'en') {
  const decimals = product.decimals ?? 2
  const total = product.amount * quantity / (10 ** decimals)
  const formatted = total.toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-EG', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
  return `${formatted} ${product.currency ?? 'EGP'}`
}

export default function CheckoutForm({ locale, product, items, totalFormatted, expectedAmountCents, cartCheckout = false, cartValid = true, onCartQuantityChange, cartBusy = false, googleMapsApiKey }: Props) {
  const [point, setPoint] = useState<Point | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [productQuantity, setProductQuantity] = useState(Math.max(1, product?.quantity ?? 1))
  const ar = locale === 'ar'
  const checkoutLines: CheckoutLine[] = items ?? (product ? [{ productId: product.id, name: product.name, sku: product.sku, imageUrl: product.imageUrl, quantity: productQuantity, formattedAmount: formatProductTotal(product, productQuantity, locale) }] : [])
  const checkoutUnavailable = Boolean(product?.unavailable) || !cartValid || checkoutLines.length === 0

  async function submitCheckout(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    const data = new FormData(event.currentTarget)
    try {
      const response = await fetch('/api/paymob/checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale },
        body: JSON.stringify({
          productId: product?.id,
          quantity: product ? productQuantity : undefined,
          checkoutCart: cartCheckout,
          expectedAmountCents,
          firstName: data.get('firstName'), lastName: data.get('lastName'),
          email: data.get('email'), phoneNumber: data.get('phoneNumber'),
          city: data.get('city'), area: data.get('area'), street: data.get('street'),
          building: data.get('building'), apartment: data.get('apartment'), floor: data.get('floor'),
          landmark: data.get('landmark'), postalCode: data.get('postalCode'), instructions: data.get('instructions'),
          latitude: point?.lat, longitude: point?.lng,
        }),
      })
      const result = await response.json() as { checkoutUrl?: string; error?: string }
      if (!response.ok || !result.checkoutUrl) throw new Error(result.error || (ar ? 'تعذر بدء الدفع.' : 'Could not start payment.'))
      window.location.assign(result.checkoutUrl)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : (ar ? 'تعذر بدء الدفع.' : 'Could not start payment.'))
      setBusy(false)
    }
  }

  const labelClass = 'block text-sm font-medium text-gray-700'
  const inputClass = 'mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-gray-900 outline-none focus:border-[#bd832e] focus:ring-2 focus:ring-[#bd832e]/20'

  return <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <form onSubmit={submitCheckout} className="min-w-0 space-y-7 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-7" dir={ar ? 'rtl' : 'ltr'}>
      <section>
        <h2 className="text-xl font-bold">{ar ? 'بيانات العميل' : 'Customer details'}</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <label className={labelClass}>{ar ? 'الاسم الأول' : 'First name'}<input name="firstName" required maxLength={100} autoComplete="given-name" className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'اسم العائلة' : 'Last name'}<input name="lastName" required maxLength={100} autoComplete="family-name" className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'البريد الإلكتروني' : 'Email'}<input name="email" type="email" required maxLength={254} autoComplete="email" className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'رقم الهاتف' : 'Phone number'}<input name="phoneNumber" type="tel" required minLength={7} maxLength={30} autoComplete="tel" placeholder="+20 1XXXXXXXXX" className={inputClass} /></label>
        </div>
      </section>

      {product && <section className="border-t border-gray-100 pt-6">
        <label htmlFor="checkout-product-quantity" className="text-sm font-semibold">{ar ? 'الكمية' : 'Quantity'}</label>
        <div className="mt-2 flex h-11 w-fit items-center overflow-hidden rounded-lg border border-gray-300">
          <button type="button" aria-label={ar ? 'تقليل الكمية' : 'Decrease quantity'} disabled={productQuantity <= 1} onClick={() => setProductQuantity((value) => Math.max(1, value - 1))} className="h-full w-11 text-lg disabled:opacity-40">−</button>
          <input id="checkout-product-quantity" type="number" min={1} max={product.maxQuantity ?? 999} value={productQuantity} onChange={(event) => setProductQuantity(clampQuantity(event.target.value, product.maxQuantity))} className="h-full w-16 border-x border-gray-300 text-center outline-none" />
          <button type="button" aria-label={ar ? 'زيادة الكمية' : 'Increase quantity'} disabled={productQuantity >= (product.maxQuantity ?? 999)} onClick={() => setProductQuantity((value) => Math.min(product.maxQuantity ?? 999, value + 1))} className="h-full w-11 text-lg disabled:opacity-40">+</button>
        </div>
        {product.maxQuantity != null && <p className="mt-1 text-xs text-gray-500">{ar ? `المتاح في المخزون: ${product.maxQuantity}` : `Available in stock: ${product.maxQuantity}`}</p>}
      </section>}

      <section className="border-t border-gray-100 pt-6">
        <h2 className="text-xl font-bold">{ar ? 'عنوان التوصيل' : 'Delivery address'}</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <label className={labelClass}>{ar ? 'المحافظة / المدينة' : 'Governorate / city'}<input name="city" required maxLength={100} autoComplete="address-level1" className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'المنطقة / الحي' : 'Area / district'}<input name="area" required maxLength={120} autoComplete="address-level2" className={inputClass} /></label>
          <label className={`${labelClass} sm:col-span-2`}>{ar ? 'اسم الشارع' : 'Street'}<input name="street" required maxLength={160} autoComplete="street-address" className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'رقم المبنى' : 'Building number'}<input name="building" required maxLength={40} className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'الشقة' : 'Apartment'}<input name="apartment" maxLength={40} className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'الدور' : 'Floor'}<input name="floor" maxLength={40} className={inputClass} /></label>
          <label className={labelClass}>{ar ? 'الرمز البريدي (اختياري)' : 'Postal code (optional)'}<input name="postalCode" maxLength={20} autoComplete="postal-code" className={inputClass} /></label>
          <label className={`${labelClass} sm:col-span-2`}>{ar ? 'علامة مميزة قريبة' : 'Nearby landmark'}<input name="landmark" maxLength={120} className={inputClass} /></label>
          <label className={`${labelClass} sm:col-span-2`}>{ar ? 'تعليمات التوصيل' : 'Delivery instructions'}<textarea name="instructions" maxLength={250} rows={2} className={inputClass} /></label>
        </div>
      </section>

      <section className="border-t border-gray-100 pt-6">
        <GoogleMapPicker apiKey={googleMapsApiKey} point={point} onChange={setPoint} locale={locale} />
      </section>

      <section className="border-t border-gray-100 pt-6">
        <h2 className="text-xl font-bold">{ar ? 'طريقة الدفع' : 'Payment method'}</h2>
        <div className="mt-3 flex items-center gap-3 rounded-xl border border-[#d59a3a] bg-amber-50 p-4">
          <input type="radio" checked readOnly aria-label={ar ? 'بطاقة بنكية' : 'Bank card'} className="accent-[#ba7d1f]" />
          <div><p className="font-semibold">{ar ? 'بطاقة بنكية عبر Paymob' : 'Bank card via Paymob'}</p><p className="text-sm text-gray-600">{ar ? 'ستنتقل إلى صفحة دفع آمنة لإدخال بيانات البطاقة.' : 'You will continue to a secure page to enter your card details.'}</p></div>
        </div>
      </section>

      {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
      <button type="submit" disabled={busy || checkoutUnavailable} className="w-full rounded-xl bg-[#f5b400] px-5 py-3.5 font-bold text-gray-950 transition hover:bg-[#e4a600] disabled:cursor-not-allowed disabled:opacity-60">
        {checkoutUnavailable ? (ar ? 'راجع السلة قبل المتابعة' : 'Review your cart before continuing') : busy ? (ar ? 'جارٍ التحويل إلى Paymob…' : 'Connecting to Paymob…') : (ar ? 'تأكيد العنوان والمتابعة للدفع' : 'Confirm address and continue to payment')}
      </button>
    </form>

    <aside className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:sticky lg:top-6" dir={ar ? 'rtl' : 'ltr'}>
      <h2 className="text-lg font-bold">{ar ? 'ملخص الطلب' : 'Order summary'}</h2>
      <div className="mt-4 space-y-4">
        {checkoutLines.map((line, index) => <div key={`${line.productId}-${index}`} className="flex gap-3">
          {line.imageUrl && <img src={line.imageUrl} alt="" className="size-16 rounded-lg bg-gray-50 object-contain" />}
      <div className="min-w-0 flex-1"><p className="break-words font-semibold">{line.name}</p>{line.sku && <p className="mt-1 break-all text-xs text-gray-500">SKU: {line.sku}</p>}
            {cartCheckout && line.cartItemId && onCartQuantityChange ? <div className="mt-2 flex items-center gap-2 text-sm">
              <span>{ar ? 'الكمية' : 'Quantity'}:</span>
              <button type="button" aria-label={ar ? 'تقليل الكمية' : 'Decrease quantity'} disabled={cartBusy || line.quantity <= 1} onClick={() => void onCartQuantityChange(line.cartItemId!, line.quantity - 1).catch((cause) => setError(cause instanceof Error ? cause.message : 'Unable to update quantity.'))} className="grid size-7 place-items-center rounded border disabled:opacity-40">−</button>
              <span className="min-w-5 text-center font-semibold">{line.quantity}</span>
              <button type="button" aria-label={ar ? 'زيادة الكمية' : 'Increase quantity'} disabled={cartBusy || line.quantity >= 999 || (line.stockQuantity != null && line.quantity >= line.stockQuantity)} onClick={() => void onCartQuantityChange(line.cartItemId!, line.quantity + 1).catch((cause) => setError(cause instanceof Error ? cause.message : 'Unable to update quantity.'))} className="grid size-7 place-items-center rounded border disabled:opacity-40">+</button>
            </div> : <p className="mt-1 text-sm">{ar ? 'الكمية' : 'Quantity'}: {line.quantity}</p>}
          </div>
          <p className="text-sm font-semibold">{line.formattedAmount}</p>
        </div>)}
      </div>
      <dl className="mt-5 space-y-3 border-t border-gray-100 pt-4 text-sm">
        <div className="flex justify-between gap-3"><dt className="text-gray-600">{ar ? 'المنتجات' : 'Products'}</dt><dd className="font-semibold">{checkoutLines.reduce((count, line) => count + line.quantity, 0)}</dd></div>
        <div className="flex justify-between gap-3 border-t border-gray-100 pt-3 text-base"><dt className="font-bold">{ar ? 'الإجمالي' : 'Total'}</dt><dd className="font-bold">{totalFormatted ?? (product ? formatProductTotal(product, productQuantity, locale) : '—')}</dd></div>
      </dl>
      <p className="mt-4 text-xs text-gray-500">{ar ? 'رسوم الشحن غير متاحة من النظام الحالي، لذلك الإجمالي المعروض هو سعر المنتج فقط.' : 'Shipping rates are not available from the current store API, so this total includes the product price only.'}</p>
    </aside>
  </div>
}
