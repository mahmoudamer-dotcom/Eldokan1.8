'use client'

import { useEffect, useId, useRef, useState } from 'react'
import Script from 'next/script'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { installmentQuoteKey, type InstallmentWidgetConfig, type SelectedInstallmentPlan } from '@/lib/checkout-payment-options'

type WidgetPlan = { id: string | number; tenure: number; amount: number }
type WidgetInstance = { destroy?: () => void }
declare global {
  interface Window {
    PaymobWidget?: new (options: {
      publicKey: string; elementId: string; amount: number; currency: string; integrationId: number
      theme: 'light' | 'dark'; customerCanSelect: boolean; onSubmit: (plan: WidgetPlan) => void
    }) => WidgetInstance
  }
}

export default function CheckoutInstallmentPlans({ config, selected, disabled, onSelect }: {
  config: InstallmentWidgetConfig | null | undefined
  selected: SelectedInstallmentPlan | null
  disabled: boolean
  onSelect: (plan: SelectedInstallmentPlan) => void
}) {
  const { locale, theme } = useLocale()
  const ar = locale === 'ar'
  const elementId = `installment-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`
  const host = useRef<HTMLDivElement>(null)
  const [ready, setReady] = useState(false)
  const [failed, setFailed] = useState(false)
  const selectionHandler = useRef(onSelect)
  useEffect(() => { selectionHandler.current = onSelect }, [onSelect])

  useEffect(() => {
    if (!ready || !config || !host.current || disabled) return
    const container = host.current
    let active = true
    let widget: WidgetInstance | undefined
    async function mount() {
      // A module load event can precede publication of the SDK's browser global.
      for (let count = 0; active && !window.PaymobWidget && count < 50; count++) {
        await new Promise((resolve) => window.setTimeout(resolve, 100))
      }
      if (!active) return
      if (!window.PaymobWidget || !config) { setFailed(true); return }
      try {
        widget = new window.PaymobWidget({
        publicKey: config.public_key, elementId, amount: config.amount, currency: config.currency,
        integrationId: config.integration_id, theme: theme === 'dark' ? 'dark' : 'light', customerCanSelect: true,
        onSubmit: (plan) => {
          if (!active || !/^[1-9][0-9]{0,9}$/.test(String(plan.id)) || !Number.isInteger(plan.tenure) || plan.tenure <= 0 || plan.tenure > 120 || !Number.isFinite(plan.amount) || plan.amount <= 0 || plan.amount > 1000000000000) return
          selectionHandler.current({ id: String(plan.id), tenure: plan.tenure, amount: plan.amount, quoteKey: installmentQuoteKey(config) })
        },
        })
      } catch { if (active) setFailed(true) }
    }
    void mount()
    const timer = window.setTimeout(() => { if (active && !container.childElementCount) setFailed(true) }, 15000)
    return () => {
      active = false
      window.clearTimeout(timer)
      try { widget?.destroy?.() } catch { /* SDK cleanup must not break checkout navigation. */ } finally { container.replaceChildren() }
    }
  }, [ready, config, elementId, theme, disabled])

  return <section aria-label={ar ? 'خطط التقسيط' : 'Installment plans'} className="space-y-3 rounded-xl border border-border bg-background p-4">
    <h3 className="font-semibold">{ar ? 'اختار البنك وخطة التقسيط' : 'Choose your bank and installment plan'}</h3>
    {!config ? <p role="status" className="text-sm text-muted-foreground">{ar ? 'خطط التقسيط غير متاحة لهذا الإجمالي أو إعدادات Paymob الحالية. اختار طريقة دفع أخرى أو تواصل مع المتجر.' : 'Installment plans are unavailable for this total or the current Paymob configuration. Choose another payment method or contact the store.'}</p> : <>
      <p className="text-sm leading-6 text-muted-foreground">{ar ? 'الخطط والرسوم المعروضة من Paymob حسب إجمالي طلبك. اختار الخطة من الأداة لإضافتها للطلب.' : 'Paymob shows eligible plans and fees for your order total. Select a plan in the widget to add it to your order.'}</p>
      <Script id="paymob-installment-widget" type="module" src="https://cdn.jsdelivr.net/npm/paymob-widget@latest/main.js" strategy="afterInteractive" onReady={() => setReady(true)} onError={() => setFailed(true)} />
      {!ready && !failed && <p role="status" className="text-sm text-muted-foreground">{ar ? 'جارٍ تحميل خطط Paymob…' : 'Loading Paymob plans…'}</p>}
      <div ref={host} id={elementId} inert={disabled} onClickCapture={(event) => {
        const button = event.target instanceof Element ? event.target.closest('button') : null
        if (button?.type === 'submit') event.preventDefault()
      }} onSubmit={(event) => { event.preventDefault(); event.stopPropagation() }} />
      {failed && <p role="alert" className="text-sm text-danger-foreground">{ar ? 'تعذر تحميل خطط التقسيط. حدّث الصفحة أو اختار طريقة دفع أخرى.' : 'Could not load installment plans. Reload the page or choose another payment method.'}</p>}
      {selected?.quoteKey === installmentQuoteKey(config) && <div role="status" className="rounded-lg bg-shop-soft p-3 text-sm font-medium">{ar ? 'الخطة المختارة:' : 'Selected plan:'} {selected.tenure} {ar ? 'شهر' : 'months'} · {new Intl.NumberFormat(ar ? 'ar-EG' : 'en-EG', { style: 'currency', currency: config.currency }).format(selected.amount / 100)} {ar ? '/ شهر' : '/ month'}</div>}
    </>}
    <p className="text-xs leading-5 text-muted-foreground">{ar ? 'تدخل بيانات الكارت في صفحة Paymob الآمنة بعد تأكيد الطلب. الموافقة النهائية على الخطة من البنك وPaymob.' : 'Enter card details on the secure Paymob page after confirming your order. Final eligibility is confirmed by your bank and Paymob.'}</p>
  </section>
}
