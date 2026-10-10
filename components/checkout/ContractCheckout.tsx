'use client'
import Money from '@/components/i18n/Money'
import { formatMoney } from '@/lib/format-money'

import { useCallback, useEffect, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { EldokanClientError, type CheckoutAddressInput, type CheckoutResponse, type OrderDetail, type PurchaseInput, type QuoteInput } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { getEgyptGovernorateLabel, getEgyptGovernorateOptions } from '@/lib/egypt-governorates'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { canStartNewCheckout, orderStatusLabel, paymentStatusLabel } from '@/lib/order-status'
import CheckoutPaymentMethods from './CheckoutPaymentMethods'
import CheckoutInstallmentPlans from './CheckoutInstallmentPlans'
import { installmentQuoteKey, type SelectedInstallmentPlan } from '@/lib/checkout-payment-options'
import { availablePaymobOptions, checkoutPaymentChoices, type PaymobOptionId, type CheckoutPaymentChoiceId, type CheckoutWithPaymentOptions } from '@/lib/checkout-payment-options'

type AddressFields = CheckoutAddressInput

const emptyAddress: AddressFields = { first_name: '', last_name: '', phone: '', email: '', country: 'EG', state: '', city: '', street_address: '', address_extra: '' }

function addressInput(address: AddressFields): CheckoutAddressInput {
  return {
    first_name: address.first_name.trim(), last_name: address.last_name.trim(),
    phone: address.phone.trim(), email: address.email.trim(), country: 'EG',
    state: address.state, city: address.city.trim(), street_address: address.street_address.trim(),
    address_extra: address.address_extra?.trim() ?? '',
    ...(address.company ? { company: address.company.trim() } : {}),
  }
}

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

function cartIssueMessage(issue: { code: string; message: string }, ar: boolean) {
  const messages: Record<string, [string, string]> = {
    seller_missing: ['Seller information for this item is incomplete. Contact the store to correct it.', 'بيانات البائع لهذا المنتج غير مكتملة. تواصل مع المتجر لتصحيحها.'],
    seller_unavailable: ['The seller for this item is unavailable. Contact the store.', 'البائع لهذا المنتج غير متاح حاليًا. تواصل مع المتجر.'],
    insufficient_stock: ['The requested quantity is not available. Reduce the quantity or remove the item.', 'الكمية المطلوبة غير متاحة. قلل الكمية أو احذف المنتج من السلة.'],
    out_of_stock: ['This item is out of stock. Remove it or choose another item.', 'المنتج نفد من المخزون. احذفه أو اختر منتجًا آخر.'],
    product_unavailable: ['This item cannot currently be purchased. Remove it or contact the store.', 'المنتج غير متاح للشراء حاليًا. احذفه أو تواصل مع المتجر.'],
    invalid_variation: ['Choose an available size, color or product option from the product page.', 'اختر مقاسًا أو لونًا أو خيارًا متاحًا من صفحة المنتج.'],
    variation_required: ['Choose the product options before adding it to the cart again.', 'اختر خيارات المنتج قبل إضافته للسلة مرة أخرى.'],
    purchase_quantity_limit: ['This quantity does not meet the product purchase limits. Update it in your cart.', 'الكمية لا توافق حدود شراء المنتج. عدّلها في السلة.'],
    sold_individually: ['Only one unit of this item can be purchased per order.', 'يمكن شراء قطعة واحدة فقط من هذا المنتج في الطلب.'],
    price_changed: ['The price has changed. Review the current price before continuing.', 'سعر المنتج اتغير. راجع السعر الحالي قبل المتابعة.'],
  }
  return messages[issue.code]?.[ar ? 1 : 0] ?? checkoutMessage(issue.message, ar)
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
  const [paymobOption, setPaymobOption] = useState<PaymobOptionId | null>(null)
  const [installmentPlan, setInstallmentPlan] = useState<SelectedInstallmentPlan | null>(null)
  const [orderNotes, setOrderNotes] = useState('')
  const [busy, setBusy] = useState(false)
  const [attemptSaved, setAttemptSaved] = useState(false)
  const [existingOrder, setExistingOrder] = useState<OrderDetail | null>(null)
  const [error, setError] = useState('')
  const [quoteRequestId, setQuoteRequestId] = useState<string | null>(null)
  const [recoveryReady, setRecoveryReady] = useState(false)
  const addressEdited = useRef(false)

  const applyQuote = useCallback((result: CheckoutResponse, preferredShippingId: string | null = null) => {
    setCheckout(result.data)
    setQuote(result.data)
    setQuoteRequestId(result.meta.request_id ?? null)
    const options = availablePaymobOptions(result.data)
    const paymentData = result.data as CheckoutWithPaymentOptions
    setInstallmentPlan(null)
    setPaymobOption(paymentData.selected_paymob_option && options.includes(paymentData.selected_paymob_option) ? paymentData.selected_paymob_option : null)
    const selected = result.data.selected_shipping_method?.id ?? preferredShippingId
    setShippingId(result.data.shipping_methods.some((method) => method.available && method.id === selected) ? selected : null)
    if ('selected_payment_method' in paymentData) {
      setPaymentMethod(result.data.payment_methods.find((method) => method.available && method.id === paymentData.selected_payment_method)?.id ?? null)
    } else {
      setPaymentMethod((current) => result.data.payment_methods.find((method) => method.available && method.id === current)?.id
        ?? result.data.payment_methods.find((method) => method.available && method.id === 'paymob')?.id
        ?? result.data.payment_methods.find((method) => method.available)?.id ?? null)
    }
  }, [])

  useEffect(() => {
    let active = true
    async function load() {
      setRecoveryReady(false)
      try {
        const result = await createEldokanApi(locale).checkout.get({ lang: locale })
        if (active) {
          setCheckout(result.data)
          setQuote(null)
          if (!addressEdited.current && result.data.address) {
            setAddress(addressInput(result.data.address))
            const saved = result.data.saved_addresses.items.find((item) => item.id === result.data.address?.id)
            setSavedAddressId(saved?.id ?? null)
            if (result.data.shipping_calculable) applyQuote(result)
          }
        }
      } catch (cause) {
        if (active) setError(cause instanceof Error ? checkoutMessage(cause.message, ar) : (ar ? 'خدمة إتمام الشراء غير متاحة حاليًا.' : 'Checkout is unavailable.'))
      }
      try {
        const response = await fetch('/api/orders/recovery', { cache: 'no-store', headers: { 'x-eldokan-locale': locale } })
        if (response.status === 404) {
          if (active) { setRecoveryReady(true); setExistingOrder(null); setAttemptSaved(false) }
          return
        }
        if (!response.ok) throw new Error('Could not check existing order recovery. Refresh the page before starting a purchase.')
        const recovery = await response.json() as { state?: string; success?: boolean; data?: OrderDetail }
        if (!active) return
        setRecoveryReady(true)
        if (recovery.state === 'attempt_pending') {
          setAttemptSaved(true)
          setError(ar ? 'هناك محاولة شراء سابقة لم يتأكد مصيرها. استعد نفس المحاولة قبل إنشاء طلب جديد.' : 'A previous checkout attempt is unresolved. Recover that same attempt before starting a new order.')
        } else if (recovery.success && recovery.data) {
          setExistingOrder(recovery.data)
        }
      } catch {
        if (active) setError(ar ? 'تعذر التحقق من الطلب السابق. أعد تحميل الصفحة قبل بدء عملية شراء.' : 'Could not check previous checkout state. Reload the page before starting a purchase.')
      }
    }
    queueMicrotask(() => { if (active) void load() })
    return () => { active = false }
  }, [locale, ar, applyQuote])

  function setField<K extends keyof AddressFields>(key: K, value: AddressFields[K]) {
    if (busy || attemptSaved || existingOrder) return
    addressEdited.current = true
    if (key === 'phone' && typeof value === 'string') {
      value = value.replace(/[٠-٩۰-۹]/g, (digit) => String(digit.charCodeAt(0) - (digit >= '۰' ? 0x06f0 : 0x0660))) as AddressFields[K]
    }
    setAddress((current) => ({ ...current, [key]: value }))
    setSavedAddressId(null)
    setQuote(null)
  }

  async function selectPayment(choice: CheckoutPaymentChoiceId) {
    if (busy || attemptSaved || existingOrder || !quote || !checkoutPaymentChoices(quote).includes(choice)) return
    const method = choice === 'cod' ? 'cod' : 'paymob'
    const option = choice === 'card' || choice === 'bank_installments' ? choice : null
    if (!('paymob_options' in quote)) { setPaymentMethod(method); setPaymobOption(option); return }
    setBusy(true); setError('')
    try {
      const input = {
        ...(savedAddressId ? { address_id: savedAddressId } : { address: addressInput(address) }),
        ...(shippingId ? { shipping_method_id: shippingId as `shp_${string}` } : {}),
        payment_method: method,
        ...(option ? { paymob_option_id: option } : {}),
      }
      applyQuote(await createEldokanApi(locale).checkout.quote(input, { lang: locale }))
    } catch (cause) {
      setQuote(null); setPaymentMethod(null); setPaymobOption(null)
      setError(checkoutError(cause, ar ? 'تعذر تحديث طريقة الدفع.' : 'Could not update the payment method.', ar))
    } finally { setBusy(false) }
  }

  function paymentQuoteFields() {
    if (!checkout || !('paymob_options' in checkout) || !paymentMethod) return {}
    return { payment_method: paymentMethod, ...(paymentMethod === 'paymob' && paymobOption ? { paymob_option_id: paymobOption } : {}) }
  }

  async function reviewQuote(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy || !recoveryReady || attemptSaved || existingOrder || !checkout?.cart.valid) return
    setBusy(true); setError('')
    setQuote(null); setQuoteRequestId(null)
    try {
      const input: QuoteInput = { ...(savedAddressId ? { address_id: savedAddressId } : { address: addressInput(address) }), ...paymentQuoteFields() }
      const result = await createEldokanApi(locale).checkout.quote(input, { lang: locale })
      const nextShippingId = result.data.selected_shipping_method?.id ?? null
      if (result.data.shipping_required && nextShippingId) {
        const selectedInput = { ...input, shipping_method_id: nextShippingId as `shp_${string}` }
        const selected = await createEldokanApi(locale).checkout.quote(selectedInput, { lang: locale })
        applyQuote(selected, nextShippingId)
      } else applyQuote(result)
    } catch (cause) { setError(checkoutError(cause, ar ? 'تعذر مراجعة الطلب.' : 'Could not review this order.', ar)) }
    finally { setBusy(false) }
  }

  async function changeShipping(id: string) {
    if (busy || attemptSaved || existingOrder) return
    const selectedId = id || null
    setShippingId(selectedId)
    setBusy(true); setError('')
    try {
      const input: QuoteInput = savedAddressId
        ? { address_id: savedAddressId, ...(selectedId ? { shipping_method_id: selectedId as `shp_${string}` } : {}) }
        : { address: addressInput(address), ...(selectedId ? { shipping_method_id: selectedId as `shp_${string}` } : {}) }
      const result = await createEldokanApi(locale).checkout.quote({ ...input, ...paymentQuoteFields() }, { lang: locale })
      applyQuote(result, selectedId)
    } catch (cause) {
      setQuote(null)
      setError(checkoutError(cause, ar ? 'تعذر تحديث تكلفة الشحن.' : 'Could not update the quote.', ar))
    }
    finally { setBusy(false) }
  }

  async function placeOrder() {
    const widget = (quote as CheckoutWithPaymentOptions | null)?.installment_widget
    if (paymobOption === 'bank_installments' && (!widget || !installmentPlan || installmentPlan.quoteKey !== installmentQuoteKey(widget))) return
    if (busy || !recoveryReady || attemptSaved || existingOrder || !quote || !quote.ready || !quote.cart.valid || !quote.totals.calculable || !quote.totals.total || !paymentMethod || !quote.payment_methods.some((method) => method.available && method.id === paymentMethod)
      || (quote.shipping_required && !quote.shipping_methods.some((method) => method.available && method.id === shippingId))) return
    if (!checkoutPaymentChoices(quote).includes(paymentMethod === 'cod' ? 'cod' : paymobOption ?? 'paymob')) return
    setBusy(true); setError('')
    const selection = {
      shipping_method_id: quote.shipping_required ? shippingId as `shp_${string}` : null,
      payment_method: paymentMethod,
      ...(paymentMethod === 'paymob' && paymobOption ? { paymob_option_id: paymobOption } : {}),
      ...(paymentMethod === 'paymob' && paymobOption === 'bank_installments' && installmentPlan ? { installment_plan_id: installmentPlan.id } : {}),
      ...(paymentMethod === 'paymob' && paymobOption === 'bank_installments' && installmentPlan ? { installment_display: { tenure: installmentPlan.tenure, monthly_amount: Math.round(installmentPlan.amount) } } : {}),
      ...(orderNotes.trim() ? { order_notes: orderNotes.trim() } : {}),
    } as const
    const purchase = (savedAddressId ? { address_id: savedAddressId, ...selection } : { address: addressInput(address), ...selection }) as PurchaseInput
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
      const response = await fetch('/api/orders/recovery', { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale }, body: JSON.stringify({
        action: 'place',
        ...(paymobOption === 'bank_installments' && installmentPlan ? {
          installment_plan_id: installmentPlan.id,
          installment_display: { tenure: installmentPlan.tenure, monthly_amount: Math.round(installmentPlan.amount) },
        } : {}),
      }) })
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

  async function startNewCheckout() {
    if (busy || !existingOrder || !canStartNewCheckout(existingOrder)) return
    setBusy(true); setError('')
    try {
      const response = await fetch('/api/orders/recovery', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'x-eldokan-locale': locale },
        body: JSON.stringify({ action: 'clear', expected_order_id: existingOrder.id }),
      })
      const result = await response.json() as { cleared?: boolean; code?: string; request_id?: string }
      if (!response.ok || !result.cleared) throw new Error([ar ? 'تعذر تأكيد حالة الطلب السابق. افتحه وراجع حالته.' : 'Could not confirm the previous order. Open it and check its status.', result.code, result.request_id].filter(Boolean).join(' · '))
      window.location.reload()
    } catch (cause) { setError(cause instanceof Error ? cause.message : (ar ? 'تعذر بدء شراء جديد حاليًا.' : 'Could not start a new checkout.')) }
    finally { setBusy(false) }
  }

  const label = 'block text-sm font-medium text-foreground'
  const input = 'mt-1 w-full rounded-lg border border-input bg-card px-3 py-2.5 text-foreground'
  if (!checkout && !error) return <p className="rounded-xl border bg-card p-8 text-center">{ar ? 'جاري تحميل خيارات الشراء…' : 'Loading checkout…'}</p>
  if (existingOrder) return <section dir={ar ? 'rtl' : 'ltr'} className="mx-auto max-w-3xl space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8">
    <h2 className="text-xl font-bold">{ar ? 'عندك طلب سابق محفوظ' : 'You have a saved order'}</h2>
    <p className="text-sm text-muted-foreground">{orderStatusLabel(existingOrder.status, locale)} · {paymentStatusLabel(existingOrder.payment_status, locale)}</p>
    <Link href={`/orders/${existingOrder.id}`} className="block rounded-xl border px-5 py-3 text-center font-semibold">{ar ? 'عرض الطلب والفاتورة' : 'View order and invoice'}</Link>
    {canStartNewCheckout(existingOrder) ? <>
      <p className="text-sm text-muted-foreground">{ar ? 'تقدر تعمل طلب جديد بالمنتجات الموجودة في سلتك، حتى لو الطلب السابق لسه مش مدفوع. الطلب السابق هيفضل محفوظ وتقدر ترجع تكمل دفعه من صفحته.' : 'You can start a new order with your current cart even if the previous order is unpaid. Your previous order remains saved, and you can resume its payment from its page.'}</p>
      <button type="button" disabled={busy} onClick={() => void startNewCheckout()} className="w-full rounded-xl bg-[#f5b400] px-5 py-3.5 font-bold text-primary-foreground disabled:opacity-50">{busy ? (ar ? 'جارٍ تجهيز الشراء…' : 'Preparing checkout…') : (ar ? 'ابدأ طلب جديد بالسلة الحالية' : 'Start a new order with this cart')}</button>
    </> : <p className="rounded-lg bg-shop-soft p-3 text-sm text-shop-accent">{ar ? 'تعذر التحقق من مرجع الطلب السابق. حدّث الصفحة وحاول مرة أخرى.' : 'Could not verify the saved order reference. Refresh the page and try again.'}</p>}
    {error && <p role="alert" className="text-sm text-danger-foreground">{error}</p>}
  </section>

  return <form onSubmit={reviewQuote} className="mx-auto grid max-w-6xl items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" dir={ar ? 'rtl' : 'ltr'}>
    <div className="min-w-0 space-y-6 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8">
    <h2 className="text-xl font-bold">{ar ? 'عنوان التوصيل' : 'Delivery address'}</h2>
    {checkout?.saved_addresses.items.length ? <label className={label}>{ar ? 'عنوان محفوظ' : 'Saved address'}
      <select disabled={busy || attemptSaved} className={input} value={savedAddressId ?? ''} onChange={(event) => {
        if (busy || attemptSaved) return
        addressEdited.current = true
        const saved = checkout.saved_addresses.items.find((item) => item.id === event.target.value)
        if (saved) {
          setSavedAddressId(saved.id)
          setAddress({ first_name: saved.first_name, last_name: saved.last_name, phone: saved.phone, email: saved.email, country: 'EG', state: saved.state, city: saved.city, street_address: saved.street_address, address_extra: saved.address_extra })
          setQuote(null)
        } else { setSavedAddressId(null); setQuote(null) }
      }}><option value="">{ar ? 'أدخل عنوانًا جديدًا' : 'Enter a new address'}</option>{checkout.saved_addresses.items.map((saved) => <option key={saved.id} value={saved.id}>{saved.first_name} {saved.last_name} · {saved.city}</option>)}</select>
    </label> : null}
    <fieldset disabled={busy || attemptSaved} aria-label={ar ? 'بيانات عنوان التوصيل' : 'Delivery address fields'} className="grid min-w-0 gap-4 sm:grid-cols-2">
      {([['first_name', ar ? 'الاسم الأول' : 'First name'], ['last_name', ar ? 'اسم العائلة' : 'Last name'], ['phone', ar ? 'رقم الهاتف' : 'Phone number'], ['email', ar ? 'البريد الإلكتروني' : 'Email'], ['city', ar ? 'المدينة أو المركز' : 'City or district'], ['street_address', ar ? 'العنوان بالتفصيل' : 'Street address'], ['address_extra', ar ? 'علامة مميزة (اختياري)' : 'Nearby landmark (optional)']] as const).map(([key, title]) => <label key={key} className={`${label} ${key === 'street_address' || key === 'address_extra' ? 'sm:col-span-2' : ''}`}>{title}<input required={key !== 'address_extra'} name={key} maxLength={{ first_name: 100, last_name: 100, phone: 32, email: 254, city: 150, street_address: 500, address_extra: 500 }[key]} minLength={key === 'phone' ? 6 : undefined} dir={key === 'phone' || key === 'email' ? 'ltr' : undefined} type={key === 'email' ? 'email' : key === 'phone' ? 'tel' : 'text'} autoComplete={{ first_name: 'given-name', last_name: 'family-name', phone: 'tel', email: 'email', city: 'address-level2', street_address: 'street-address', address_extra: 'address-line2' }[key]} value={address[key] ?? ''} onChange={(event) => setField(key, event.target.value)} className={input} placeholder={key === 'address_extra' && ar ? 'مثال: بجوار المدرسة أو المسجد' : undefined} /></label>)}
      <label className={label}>{ar ? 'المحافظة' : 'Governorate'}<select required value={address.state} onChange={(event) => setField('state', event.target.value)} className={input}><option value="">{ar ? 'اختر المحافظة' : 'Choose governorate'}</option>{getEgyptGovernorateOptions(checkout?.address_requirements.state_codes ?? []).map((state) => <option key={state} value={state}>{getEgyptGovernorateLabel(state, locale)}</option>)}</select></label>
    </fieldset>
    <label className={label}>{ar ? 'ملاحظات الطلب (اختياري)' : 'Order notes (optional)'}<textarea disabled={busy || attemptSaved} maxLength={500} value={orderNotes} onChange={(event) => setOrderNotes(event.target.value)} className={`${input} min-h-24`} placeholder={ar ? 'تعليمات إضافية للتوصيل' : 'Any extra delivery instructions'} /></label>
    {!quote && checkout?.issues.map((issue, index) => <p key={`${issue.code}-${index}`} className="rounded-lg bg-shop-soft p-3 text-sm text-shop-accent">{checkoutMessage(issue.message, ar)}</p>)}
    {quote && <section className="space-y-4 border-t pt-5">
      <h3 className="font-bold">{ar ? 'خيارات الشحن والدفع' : 'Shipping and payment'}</h3>
      {quote.shipping_required && !quote.shipping_methods.some((item) => item.available) && <p role="alert" className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{ar ? 'لا توجد طريقة شحن متاحة لكل المنتجات لهذا العنوان. جرّب تحديث الخيارات أو تواصل مع المتجر.' : 'Shipping is unavailable for this cart and address. Refresh the options or contact the store.'}</p>}
      {quote.shipping_required && quote.shipping_methods.some((item) => item.available) && <label className={label}>{ar ? 'طريقة الشحن' : 'Shipping method'}<select required disabled={busy || attemptSaved || !!existingOrder} value={shippingId ?? ''} onChange={(event) => void changeShipping(event.target.value)} className={input}><option value="">{ar ? 'اختر طريقة الشحن' : 'Choose shipping'}</option>{quote.shipping_methods.filter((item) => item.available).map((item) => <option key={item.id} value={item.id}>{ar ? (item.type === 'pickup' ? 'استلام من المتجر' : 'توصيل للمنزل') : item.name} · {formatMoney(item.amount, locale)}</option>)}</select></label>}
      <CheckoutPaymentMethods checkout={quote} selected={paymentMethod === 'cod' ? 'cod' : paymentMethod === 'paymob' ? paymobOption ?? 'paymob' : null} disabled={busy || attemptSaved || !!existingOrder} onSelect={selectPayment} />
      {paymobOption === 'bank_installments' && <CheckoutInstallmentPlans key={`${quoteRequestId}:${locale}`} config={(quote as CheckoutWithPaymentOptions).installment_widget} selected={installmentPlan} disabled={busy || attemptSaved || !!existingOrder} onSelect={setInstallmentPlan} />}
      {!quote.ready && <p role="alert" className="rounded-lg bg-shop-soft p-3 text-sm">{quote.issues.map((issue) => checkoutMessage(issue.message, ar)).join(' · ') || (ar ? 'راجع البيانات قبل المتابعة.' : 'Review the checkout details before continuing.')}</p>}
      {quoteRequestId && !quote.ready && <p className="text-xs text-muted-foreground">{ar ? 'رقم مرجع المراجعة' : 'Quote reference'}: <span dir="ltr">{quoteRequestId}</span></p>}
      <div className="flex justify-between gap-3 rounded-xl bg-shop-soft p-4 font-bold lg:hidden"><span>{ar ? 'الإجمالي' : 'Total'}</span><Money value={quote.totals.total} /></div>
      <button type="button" disabled={(paymobOption === 'bank_installments' && (!(quote as CheckoutWithPaymentOptions).installment_widget || !installmentPlan)) || busy || attemptSaved || !!existingOrder || !quote.ready || !quote.cart.valid || !quote.totals.calculable || !quote.totals.total || !paymentMethod || (quote.shipping_required && !shippingId)} onClick={() => void placeOrder()} className="w-full rounded-xl bg-[#f5b400] text-primary-foreground px-5 py-3.5 font-bold disabled:opacity-50">{busy ? (ar ? 'جارٍ تأكيد الطلب…' : 'Processing…') : (paymentMethod === 'paymob' ? (ar ? 'تأكيد الطلب والانتقال للدفع' : 'Confirm order and pay') : (ar ? 'تأكيد الطلب' : 'Confirm order'))}</button>
    </section>}
      {error && <p role="alert" className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{error}</p>}
      {attemptSaved && error && <button type="button" disabled={busy} onClick={() => void recoverPlacement()} className="w-full rounded-xl border px-5 py-3 font-semibold disabled:opacity-50">{ar ? 'استعادة نفس محاولة الطلب' : 'Recover the same checkout attempt'}</button>}
    <button type="submit" disabled={busy || !recoveryReady || !checkout?.cart.valid || attemptSaved || !!existingOrder} className="w-full rounded-xl border px-5 py-3.5 font-bold disabled:opacity-50">{busy ? (ar ? 'جارٍ مراجعة الإجمالي…' : 'Reviewing…') : quote ? (ar ? 'تحديث خيارات الشحن والدفع' : 'Refresh shipping and payment') : (ar ? 'مراجعة الإجمالي' : 'Review total')}</button>
    {!recoveryReady && error && <button type="button" onClick={() => window.location.reload()} className="w-full rounded-xl border px-5 py-3 font-semibold">{ar ? 'إعادة تحميل حالة الشراء' : 'Reload checkout state'}</button>}
    <p className="text-xs leading-6 text-muted-foreground">{ar ? 'راجع معلومات الشراء والسياسات: ' : 'Review shopping information and policies: '}<Link href="/policies/buyer-terms" className="underline">{ar ? 'شروط العملاء' : 'Buyer terms'}</Link> · <Link href="/policies/shipping" className="underline">{ar ? 'الشحن' : 'Shipping'}</Link> · <Link href="/policies/returns" className="underline">{ar ? 'الإرجاع' : 'Returns'}</Link> · <Link href="/policies/privacy" className="underline">{ar ? 'الخصوصية' : 'Privacy'}</Link></p>
    </div>
    <aside className="order-first min-w-0 space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm lg:sticky lg:top-48 lg:order-last">
    {checkout?.cart.items.length ? <section className="space-y-3 rounded-xl bg-background p-4" aria-label={ar ? 'محتويات السلة' : 'Cart summary'}>
      <h2 className="font-bold">{ar ? 'مراجعة المنتجات' : 'Review items'}</h2>
      <ul className="divide-y">{checkout.cart.items.map((item) => <li key={item.id} className="space-y-2 py-3 text-sm">
        <div className="flex justify-between gap-4"><span>{item.name ?? (ar ? 'منتج غير متاح' : 'Unavailable item')} × {item.quantity}</span><span className="shrink-0"><Money value={item.line_subtotal ?? item.unit_price} /></span></div>
        {item.issues.map((issue, index) => <p key={`${issue.code}-${index}`} className={issue.blocking ? 'text-danger-foreground' : 'text-muted-foreground'}>{cartIssueMessage(issue, ar)}</p>)}
      </li>)}</ul>
      {!checkout.cart.valid && <div role="alert" className="space-y-2 rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground"><p>{ar ? 'تعذر متابعة الشراء. راجع السبب الموضح تحت كل منتج.' : 'Checkout is blocked. Review the reason shown under each item.'}</p><Link href="/cart" className="inline-flex rounded-lg border border-current px-3 py-2 font-semibold underline underline-offset-4">{ar ? 'مراجعة وتعديل السلة' : 'Review and edit cart'}</Link></div>}
    </section> : null}
      {quote ? <>      <div className="space-y-2 rounded-xl bg-background p-4 text-sm">
        {([[ar ? 'المجموع الفرعي' : 'Subtotal', quote.totals.subtotal], [ar ? 'الشحن' : 'Shipping', quote.totals.shipping], [ar ? 'الضريبة' : 'Tax', quote.totals.tax], [ar ? 'الخصم' : 'Discount', quote.totals.discount], [ar ? 'الرسوم' : 'Fees', quote.totals.fees]] as const).filter(([, amount]) => amount).map(([title, amount]) => <p key={title} className="flex justify-between gap-4"><span>{title}</span><span><Money value={amount} /></span></p>)}
        <p className="flex justify-between gap-4 border-t pt-2 text-lg font-bold"><span>{ar ? 'الإجمالي' : 'Total'}</span><span>{quote.totals.total ? <Money value={quote.totals.total} /> : (ar ? 'بانتظار حساب الشحن' : 'Waiting for shipping quote')}</span></p>
      </div>
</> : <p className="px-4 text-sm text-muted-foreground">{ar ? 'أكمل العنوان لعرض الشحن والإجمالي النهائي.' : 'Complete your address to see shipping and the final total.'}</p>}
      <Link href="/cart" className="inline-flex min-h-11 items-center px-4 text-sm font-semibold underline underline-offset-4">{ar ? 'تعديل السلة' : 'Edit your cart'}</Link>
    </aside>
  </form>
}
