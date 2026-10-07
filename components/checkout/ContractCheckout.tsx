'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { EldokanClientError, type CheckoutAddressInput, type CheckoutResponse, type PurchaseInput, type QuoteInput } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { getEgyptGovernorateLabel, getEgyptGovernorateOptions } from '@/lib/egypt-governorates'
import { useLocale } from '@/components/i18n/LocaleProvider'

type AddressFields = CheckoutAddressInput

const emptyAddress: AddressFields = { first_name: '', last_name: '', phone: '', email: '', country: 'EG', state: '', city: '', street_address: '', address_extra: '' }

function checkoutMessage(message: string, ar: boolean) {
  if (!ar) return message
  const normalized = message.toLocaleLowerCase()
  if (normalized.includes('no supported shipping method covers')) return 'لا توجد طريقة شحن متاحة لكل المنتجات في السلة لهذا العنوان.'
  if (normalized.includes('provide a complete egypt checkout address')) return 'أكمل بيانات عنوان التوصيل داخل مصر.'
  if (normalized.includes('shipping is calculated after a valid address')) return 'سيتم حساب تكلفة الشحن بعد إدخال عنوان صحيح.'
  if (normalized.includes('shipping') && (normalized.includes('unavailable') || normalized.includes('no shipping'))) return 'لا توجد وسيلة شحن متاحة للمحافظة المختارة. راجع إعداد مناطق الشحن في المتجر.'
  if (normalized.includes('payment') && (normalized.includes('unavailable') || normalized.includes('no payment'))) return 'لا توجد وسيلة دفع مفعّلة لهذا الطلب. راجع إعدادات الدفع في المتجر.'
  if (normalized.includes('shipping method') && normalized.includes('required')) return 'اختر طريقة الشحن للمتابعة.'
  if (normalized.includes('payment method') && normalized.includes('available')) return 'وسيلة الدفع المختارة غير متاحة لهذا الطلب.'
  if (normalized.includes('cart') && (normalized.includes('empty') || normalized.includes('no items'))) return 'سلة التسوق فارغة.'
  if (/[\u0600-\u06FF]/.test(message)) return message
  return 'تعذر إكمال الطلب. راجع البيانات وحاول مرة أخرى.'
}

function paymentLabel(id: 'cod' | 'paymob', name: string, ar: boolean) {
  if (!ar) return name
  return id === 'cod' ? 'الدفع عند الاستلام' : 'الدفع الإلكتروني عبر Paymob'
}

function paymentDescription(id: 'cod' | 'paymob', description: string, ar: boolean) {
  if (!ar) return description
  return id === 'cod' ? 'ادفع قيمة الطلب عند استلامه.' : 'ستنتقل إلى صفحة Paymob الآمنة لإتمام الدفع.'
}

function checkoutError(cause: unknown, fallback: string, ar: boolean) {
  const message = cause instanceof EldokanClientError && cause.issues.length
    ? cause.issues.map((issue) => checkoutMessage(issue.message, ar)).join(' · ')
    : cause instanceof Error ? checkoutMessage(cause.message, ar) : fallback
  return `${message}${cause instanceof EldokanClientError && cause.requestId ? ` (${cause.requestId})` : ''}`
}

export default function ContractCheckout() {
  const { locale } = useLocale()
  const router = useRouter()
  const ar = locale === 'ar'
  const [checkout, setCheckout] = useState<CheckoutResponse['data'] | null>(null)
  const [address, setAddress] = useState<AddressFields>(emptyAddress)
  const [savedAddressId, setSavedAddressId] = useState<`adr_${string}` | null>(null)
  const [quote, setQuote] = useState<CheckoutResponse['data'] | null>(null)
  const [shippingId, setShippingId] = useState<string | null>(null)
  const [paymentMethod, setPaymentMethod] = useState<'cod' | 'paymob' | null>(null)
  const [orderNotes, setOrderNotes] = useState('')
  const [busy, setBusy] = useState(false)
  const [attemptSaved, setAttemptSaved] = useState(false)
  const [existingGuestOrder, setExistingGuestOrder] = useState(false)
  const [error, setError] = useState('')
  const [quoteRequestId, setQuoteRequestId] = useState<string | null>(null)
  const [recoveryReady, setRecoveryReady] = useState(false)

  function applyQuote(result: CheckoutResponse, preferredShippingId: string | null = null) {
    setQuote(result.data)
    setQuoteRequestId(result.meta.request_id ?? null)
    const selected = result.data.selected_shipping_method?.id ?? preferredShippingId
    setShippingId(result.data.shipping_methods.some((method) => method.available && method.id === selected) ? selected : null)
    setPaymentMethod((current) => result.data.payment_methods.find((method) => method.available && method.id === current)?.id
      ?? result.data.payment_methods.find((method) => method.available)?.id ?? null)
  }

  useEffect(() => {
    let active = true
    async function load() {
      setRecoveryReady(false)
      try {
        const result = await createEldokanApi(locale).checkout.get({ lang: locale })
        if (active) setCheckout(result.data)
      } catch (cause) {
        if (active) setError(cause instanceof Error ? checkoutMessage(cause.message, ar) : (ar ? 'خدمة إتمام الشراء غير متاحة حاليًا.' : 'Checkout is unavailable.'))
      }
      try {
        const response = await fetch('/api/orders/recovery', { cache: 'no-store', headers: { 'x-eldokan-locale': locale } })
        if (response.status === 404) { if (active) setRecoveryReady(true); return }
        if (!response.ok) throw new Error('Could not check existing order recovery. Refresh the page before starting a purchase.')
        const recovery = await response.json() as { state?: string; success?: boolean }
        if (!active) return
        setRecoveryReady(true)
        if (recovery.state === 'attempt_pending') {
          setAttemptSaved(true)
          setError(ar ? 'هناك محاولة شراء سابقة لم يتأكد مصيرها. استعد نفس المحاولة قبل إنشاء طلب جديد.' : 'A previous checkout attempt is unresolved. Recover that same attempt before starting a new order.')
        } else if (recovery.success) {
          setExistingGuestOrder(true)
          setError(ar ? 'لديك طلب محفوظ. افتح حالته قبل بدء عملية شراء أخرى.' : 'An order is saved. Open its status before starting another purchase.')
        }
      } catch {
        if (active) setError(ar ? 'تعذر التحقق من الطلب السابق. أعد تحميل الصفحة قبل بدء عملية شراء.' : 'Could not check previous checkout state. Reload the page before starting a purchase.')
      }
    }
    queueMicrotask(() => { if (active) void load() })
    return () => { active = false }
  }, [locale, ar])

  function setField<K extends keyof AddressFields>(key: K, value: AddressFields[K]) {
    if (busy || attemptSaved) return
    setAddress((current) => ({ ...current, [key]: value }))
    setSavedAddressId(null)
    setQuote(null)
  }

  async function reviewQuote(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy || !recoveryReady || attemptSaved || existingGuestOrder) return
    setBusy(true); setError('')
    setQuote(null); setQuoteRequestId(null)
    try {
      const input: QuoteInput = savedAddressId ? { address_id: savedAddressId } : { address }
      const result = await createEldokanApi(locale).checkout.quote(input, { lang: locale })
      const nextShippingId = result.data.selected_shipping_method?.id ?? result.data.shipping_methods.find((method) => method.available)?.id ?? null
      if (result.data.shipping_required && nextShippingId) {
        const selectedInput = savedAddressId ? { address_id: savedAddressId, shipping_method_id: nextShippingId as `shp_${string}` } : { address, shipping_method_id: nextShippingId as `shp_${string}` }
        const selected = await createEldokanApi(locale).checkout.quote(selectedInput, { lang: locale })
        applyQuote(selected, nextShippingId)
      } else applyQuote(result)
    } catch (cause) { setError(checkoutError(cause, ar ? 'تعذر مراجعة الطلب.' : 'Could not review this order.', ar)) }
    finally { setBusy(false) }
  }

  async function changeShipping(id: string) {
    if (busy || attemptSaved || existingGuestOrder) return
    const selectedId = id || null
    setShippingId(selectedId)
    setBusy(true); setError('')
    try {
      const input: QuoteInput = savedAddressId
        ? { address_id: savedAddressId, ...(selectedId ? { shipping_method_id: selectedId as `shp_${string}` } : {}) }
        : { address, ...(selectedId ? { shipping_method_id: selectedId as `shp_${string}` } : {}) }
      const result = await createEldokanApi(locale).checkout.quote(input, { lang: locale })
      applyQuote(result, selectedId)
    } catch (cause) {
      setQuote(null)
      setError(checkoutError(cause, ar ? 'تعذر تحديث تكلفة الشحن.' : 'Could not update the quote.', ar))
    }
    finally { setBusy(false) }
  }

  async function placeOrder() {
    if (busy || !recoveryReady || attemptSaved || existingGuestOrder || !quote || !quote.ready || !paymentMethod || !quote.payment_methods.some((method) => method.available && method.id === paymentMethod)
      || (quote.shipping_required && !quote.shipping_methods.some((method) => method.available && method.id === shippingId))) return
    setBusy(true); setError('')
    const selection = {
      shipping_method_id: quote.shipping_required ? shippingId as `shp_${string}` : null,
      payment_method: paymentMethod,
      ...(orderNotes.trim() ? { order_notes: orderNotes.trim() } : {}),
    } as const
    const purchase = (savedAddressId ? { address_id: savedAddressId, ...selection } : { address, ...selection }) as PurchaseInput
    try {
      const api = createEldokanApi(locale)
      const attempt = await api.checkout.createAttempt(purchase, { lang: locale })
      const recoveryResponse = await fetch('/api/orders/recovery', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale },
        body: JSON.stringify({ action: 'save-attempt', checkout_attempt_id: attempt.data.checkout_attempt_id, purchase }),
      })
      if (!recoveryResponse.ok) throw new Error('Could not safely save checkout recovery state. No order was placed.')
      setAttemptSaved(true)

      const placed = await fetch('/api/orders/recovery', { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale }, body: JSON.stringify({ action: 'place' }) })
      const envelope = await placed.json() as { data?: { order_id: string; payment?: { requires_redirect?: boolean; redirect_url?: string | null } | { issue?: { code: string } } }; error?: string; code?: string; request_id?: string | null }
      if (!placed.ok || !envelope.data) throw new Error([envelope.error || 'Order placement is uncertain. Recover the same checkout attempt before trying again.', envelope.code, envelope.request_id].filter(Boolean).join(' · '))
      const payment = envelope.data.payment
      if (payment && 'requires_redirect' in payment && payment.requires_redirect === true && payment.redirect_url) {
        const url = new URL(payment.redirect_url)
        if (url.protocol !== 'https:' || url.hostname !== 'accept.paymob.com') throw new Error('The hosted payment destination failed validation. Your order is saved; open the order result to recover payment.')
        window.location.assign(url.toString())
        return
      }
      if (paymentMethod === 'paymob') {
        const recovered = await fetch('/api/orders/recovery', { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale }, body: JSON.stringify({ action: 'payment' }) })
        const recoveredBody = await recovered.json() as { data?: { requires_redirect: boolean; redirect_url: string | null } }
        if (recoveredBody.data?.requires_redirect && recoveredBody.data.redirect_url) {
          const url = new URL(recoveredBody.data.redirect_url)
          if (url.protocol === 'https:' && url.hostname === 'accept.paymob.com') { window.location.assign(url.toString()); return }
        }
      }
      router.push('/checkout/result')
    } catch (cause) {
      setError(checkoutError(cause, ar ? 'حالة الطلب غير مؤكدة. استعد نفس المحاولة قبل تكرارها.' : 'Checkout outcome is uncertain. Recover the same attempt before trying again.', ar))
    } finally { setBusy(false) }
  }

  async function recoverPlacement() {
    if (busy) return
    setBusy(true); setError('')
    try {
      const response = await fetch('/api/orders/recovery', { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale }, body: JSON.stringify({ action: 'place' }) })
      const result = await response.json() as { data?: { payment?: { requires_redirect?: boolean; redirect_url?: string | null } }; error?: string; code?: string; request_id?: string | null }
      if (!response.ok || !result.data) throw new Error([result.error || 'Order is still being recovered. Do not start another checkout.', result.code, result.request_id].filter(Boolean).join(' · '))
      const payment = result.data.payment
      if (payment?.requires_redirect && payment.redirect_url) {
        const url = new URL(payment.redirect_url)
        if (url.protocol === 'https:' && url.hostname === 'accept.paymob.com') { window.location.assign(url.toString()); return }
      }
      router.push('/checkout/result')
    } catch (cause) { setError(cause instanceof Error ? checkoutMessage(cause.message, ar) : (ar ? 'ما زال جارٍ استعادة الطلب.' : 'Order is still being recovered.')) }
    finally { setBusy(false) }
  }

  const label = 'block text-sm font-medium text-foreground'
  const input = 'mt-1 w-full rounded-lg border border-input bg-card px-3 py-2.5 text-foreground'
  if (!checkout && !error) return <p className="rounded-xl border bg-card p-8 text-center">{ar ? 'جاري تحميل خيارات الشراء…' : 'Loading checkout…'}</p>

  return <form onSubmit={reviewQuote} className="mx-auto max-w-3xl space-y-6 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8" dir={ar ? 'rtl' : 'ltr'}>
    {checkout?.cart.items.length ? <section className="space-y-3 rounded-xl bg-background p-4" aria-label={ar ? 'محتويات السلة' : 'Cart summary'}>
      <h2 className="font-bold">{ar ? 'مراجعة المنتجات' : 'Review items'}</h2>
      <ul className="divide-y">{checkout.cart.items.map((item) => <li key={item.id} className="flex justify-between gap-4 py-3 text-sm"><span>{item.name} × {item.quantity}</span><span>{item.line_subtotal?.formatted ?? item.unit_price?.formatted ?? '—'}</span></li>)}</ul>
      {!checkout.cart.valid && <p role="alert" className="text-sm text-danger-foreground">{ar ? 'بعض المنتجات غير متاحة للشراء حاليًا. حدّث السلة قبل المتابعة.' : 'Some cart items are not currently available. Update your cart before continuing.'}</p>}
    </section> : null}
    <h2 className="text-xl font-bold">{ar ? 'عنوان التوصيل' : 'Delivery address'}</h2>
    {checkout?.saved_addresses.items.length ? <label className={label}>{ar ? 'عنوان محفوظ' : 'Saved address'}
      <select className={input} value={savedAddressId ?? ''} onChange={(event) => {
        if (busy || attemptSaved) return
        const saved = checkout.saved_addresses.items.find((item) => item.id === event.target.value)
        if (saved) {
          setSavedAddressId(saved.id)
          setAddress({ first_name: saved.first_name, last_name: saved.last_name, phone: saved.phone, email: saved.email, country: 'EG', state: saved.state, city: saved.city, street_address: saved.street_address, address_extra: saved.address_extra })
          setQuote(null)
        } else { setSavedAddressId(null); setQuote(null) }
      }}><option value="">{ar ? 'أدخل عنوانًا جديدًا' : 'Enter a new address'}</option>{checkout.saved_addresses.items.map((saved) => <option key={saved.id} value={saved.id}>{saved.first_name} {saved.last_name} · {saved.city}</option>)}</select>
    </label> : null}
    <div className="grid gap-4 sm:grid-cols-2">
      {([['first_name', ar ? 'الاسم الأول' : 'First name'], ['last_name', ar ? 'اسم العائلة' : 'Last name'], ['phone', ar ? 'رقم الهاتف' : 'Phone number'], ['email', ar ? 'البريد الإلكتروني' : 'Email'], ['city', ar ? 'المدينة أو المركز' : 'City or district'], ['street_address', ar ? 'العنوان بالتفصيل' : 'Street address'], ['address_extra', ar ? 'علامة مميزة (اختياري)' : 'Nearby landmark (optional)']] as const).map(([key, title]) => <label key={key} className={`${label} ${key === 'street_address' || key === 'address_extra' ? 'sm:col-span-2' : ''}`}>{title}<input required={key !== 'address_extra'} maxLength={key === 'phone' ? 32 : undefined} type={key === 'email' ? 'email' : key === 'phone' ? 'tel' : 'text'} autoComplete={{ first_name: 'given-name', last_name: 'family-name', phone: 'tel', email: 'email', city: 'address-level2', street_address: 'street-address', address_extra: 'address-line2' }[key]} value={address[key] ?? ''} onChange={(event) => setField(key, event.target.value)} className={input} placeholder={key === 'address_extra' && ar ? 'مثال: بجوار المدرسة أو المسجد' : undefined} /></label>)}
      <label className={label}>{ar ? 'المحافظة' : 'Governorate'}<select required value={address.state} onChange={(event) => setField('state', event.target.value)} className={input}><option value="">{ar ? 'اختر المحافظة' : 'Choose governorate'}</option>{getEgyptGovernorateOptions(checkout?.address_requirements.state_codes ?? []).map((state) => <option key={state} value={state}>{getEgyptGovernorateLabel(state, locale)}</option>)}</select></label>
    </div>
    <label className={label}>{ar ? 'ملاحظات الطلب (اختياري)' : 'Order notes (optional)'}<textarea maxLength={500} value={orderNotes} onChange={(event) => setOrderNotes(event.target.value)} className={`${input} min-h-24`} placeholder={ar ? 'تعليمات إضافية للتوصيل' : 'Any extra delivery instructions'} /></label>
    {!quote && checkout?.issues.map((issue, index) => <p key={`${issue.code}-${index}`} className="rounded-lg bg-shop-soft p-3 text-sm text-shop-accent">{checkoutMessage(issue.message, ar)}</p>)}
    {quote && <section className="space-y-4 border-t pt-5">
      <h3 className="font-bold">{ar ? 'خيارات الشحن والدفع' : 'Shipping and payment'}</h3>
      {quote.shipping_required && !quote.shipping_methods.some((item) => item.available) && <p role="alert" className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{ar ? 'لا توجد طريقة شحن متاحة لكل المنتجات لهذا العنوان. جرّب تحديث الخيارات أو تواصل مع المتجر.' : 'Shipping is unavailable for this cart and address. Refresh the options or contact the store.'}</p>}
      {quote.shipping_required && quote.shipping_methods.some((item) => item.available) && <label className={label}>{ar ? 'طريقة الشحن' : 'Shipping method'}<select required value={shippingId ?? ''} onChange={(event) => void changeShipping(event.target.value)} className={input}><option value="">{ar ? 'اختر طريقة الشحن' : 'Choose shipping'}</option>{quote.shipping_methods.filter((item) => item.available).map((item) => <option key={item.id} value={item.id}>{ar ? (item.type === 'pickup' ? 'استلام من المتجر' : 'توصيل للمنزل') : item.name} · {item.amount.formatted}</option>)}</select></label>}
      <fieldset className="space-y-2"><legend className="text-sm font-medium">{ar ? 'طريقة الدفع' : 'Payment method'}</legend>{quote.payment_methods.filter((item) => item.available).map((method) => <label key={method.id} className="flex items-start gap-3 rounded-lg border p-3"><input type="radio" name="payment" required checked={paymentMethod === method.id} onChange={() => setPaymentMethod(method.id)} /><span><strong>{paymentLabel(method.id, method.name, ar)}</strong><span className="block text-sm text-muted-foreground">{paymentDescription(method.id, method.description, ar)}</span></span></label>)}{!quote.payment_methods.some((method) => method.available) && <p role="alert" className="text-sm text-danger-foreground">{!quote.payment_availability_calculable ? (ar ? 'ستظهر وسائل الدفع بعد توفر الشحن والإجمالي.' : 'Payment options will appear once shipping and the total are available.') : (ar ? 'لا توجد وسيلة دفع متاحة لهذا الطلب حاليًا. تواصل مع المتجر.' : 'No payment method is currently available for this order. Contact the store.')}</p>}</fieldset>
      {!quote.ready && <p role="alert" className="rounded-lg bg-shop-soft p-3 text-sm">{quote.issues.map((issue) => checkoutMessage(issue.message, ar)).join(' · ') || (ar ? 'راجع البيانات قبل المتابعة.' : 'Review the checkout details before continuing.')}</p>}
      <div className="space-y-2 rounded-xl bg-background p-4 text-sm">
        {([[ar ? 'المجموع الفرعي' : 'Subtotal', quote.totals.subtotal], [ar ? 'الشحن' : 'Shipping', quote.totals.shipping], [ar ? 'الضريبة' : 'Tax', quote.totals.tax], [ar ? 'الخصم' : 'Discount', quote.totals.discount], [ar ? 'الرسوم' : 'Fees', quote.totals.fees]] as const).filter(([, amount]) => amount).map(([title, amount]) => <p key={title} className="flex justify-between gap-4"><span>{title}</span><span>{amount?.formatted}</span></p>)}
        <p className="flex justify-between gap-4 border-t pt-2 text-lg font-bold"><span>{ar ? 'الإجمالي' : 'Total'}</span><span>{quote.totals.total?.formatted ?? (ar ? 'بانتظار حساب الشحن' : 'Waiting for shipping quote')}</span></p>
      </div>
      {quoteRequestId && !quote.ready && <p className="text-xs text-muted-foreground">{ar ? 'رقم مرجع المراجعة' : 'Quote reference'}: <span dir="ltr">{quoteRequestId}</span></p>}
      <button type="button" disabled={busy || attemptSaved || existingGuestOrder || !quote.ready || !paymentMethod || (quote.shipping_required && !shippingId)} onClick={() => void placeOrder()} className="w-full rounded-xl bg-[#f5b400] text-primary-foreground px-5 py-3.5 font-bold disabled:opacity-50">{busy ? (ar ? 'جارٍ تأكيد الطلب…' : 'Processing…') : (paymentMethod === 'paymob' ? (ar ? 'تأكيد الطلب والانتقال للدفع' : 'Confirm order and pay') : (ar ? 'تأكيد الطلب' : 'Confirm order'))}</button>
    </section>}
      {error && <p role="alert" className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{error}</p>}
      {attemptSaved && error && <button type="button" disabled={busy} onClick={() => void recoverPlacement()} className="w-full rounded-xl border px-5 py-3 font-semibold disabled:opacity-50">{ar ? 'استعادة نفس محاولة الطلب' : 'Recover the same checkout attempt'}</button>}
    <button type="submit" disabled={busy || !recoveryReady || !checkout || attemptSaved || existingGuestOrder} className="w-full rounded-xl border px-5 py-3.5 font-bold disabled:opacity-50">{busy ? (ar ? 'جارٍ مراجعة الإجمالي…' : 'Reviewing…') : quote ? (ar ? 'تحديث خيارات الشحن والدفع' : 'Refresh shipping and payment') : (ar ? 'مراجعة الإجمالي' : 'Review total')}</button>
    {!recoveryReady && error && <button type="button" onClick={() => window.location.reload()} className="w-full rounded-xl border px-5 py-3 font-semibold">{ar ? 'إعادة تحميل حالة الشراء' : 'Reload checkout state'}</button>}
    {existingGuestOrder && <button type="button" onClick={() => router.push('/checkout/result')} className="w-full rounded-xl border px-5 py-3 font-semibold">{ar ? 'عرض الطلب المحفوظ' : 'Open saved order'}</button>}
    <p className="text-xs leading-6 text-muted-foreground">{ar ? 'راجع معلومات الشراء والسياسات: ' : 'Review shopping information and policies: '}<Link href="/policies/buyer-terms" className="underline">{ar ? 'شروط العملاء' : 'Buyer terms'}</Link> · <Link href="/policies/shipping" className="underline">{ar ? 'الشحن' : 'Shipping'}</Link> · <Link href="/policies/returns" className="underline">{ar ? 'الإرجاع' : 'Returns'}</Link> · <Link href="/policies/privacy" className="underline">{ar ? 'الخصوصية' : 'Privacy'}</Link></p>
  </form>
}
