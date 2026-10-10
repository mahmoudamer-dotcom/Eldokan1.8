'use client'

import { Banknote, CreditCard, Landmark, LockKeyhole } from 'lucide-react'
import type { CheckoutResponse } from '@eldokan/customer-api-client'
import { checkoutPaymentChoices, type CheckoutPaymentChoiceId } from '@/lib/checkout-payment-options'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function CheckoutPaymentMethods({ checkout, selected, disabled, onSelect }: {
  checkout: CheckoutResponse['data']
  selected: CheckoutPaymentChoiceId | null
  disabled: boolean
  onSelect: (choice: CheckoutPaymentChoiceId) => void
}) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const choices = checkoutPaymentChoices(checkout)
  const details = {
    cod: { icon: Banknote, title: ar ? 'الدفع عند الاستلام' : 'Cash on delivery', description: ar ? 'أكد طلبك وادفع عند استلامه.' : 'Confirm your order and pay when it arrives.' },
    card: { icon: CreditCard, title: ar ? 'كارت بنكي' : 'Card payment', description: ar ? 'ادفع بالكارت من خلال صفحة Paymob الآمنة.' : 'Pay by card on the secure Paymob payment page.' },
    bank_installments: { icon: Landmark, title: ar ? 'تقسيط بنكي' : 'Bank installments', description: ar ? 'اختار البنك والخطة من الخيارات بالأسفل، وبعدها أدخل بيانات الكارت في صفحة Paymob الآمنة.' : 'Choose your bank and plan below, then enter card details on the secure Paymob page.' },
    paymob: { icon: CreditCard, title: ar ? 'الدفع أونلاين عبر Paymob' : 'Online payment with Paymob', description: ar ? 'اختر وسيلة الدفع من الخيارات المتاحة في صفحة Paymob.' : 'Choose from the payment methods available on the Paymob payment page.' },
  }
  return <fieldset disabled={disabled} className="space-y-3">
    <legend className="mb-3 text-base font-semibold">{ar ? 'اختار طريقة الدفع' : 'Choose how to pay'}</legend>
    <div className="grid gap-3 sm:grid-cols-2">
      {choices.map((choice) => {
        const item = details[choice]
        const Icon = item.icon
        const active = selected === choice
        return <label key={choice} className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors focus-within:ring-2 focus-within:ring-[#f5b400] ${active ? 'border-[#f5b400] bg-shop-soft' : 'border-border bg-background hover:bg-shop-soft'} ${disabled ? 'cursor-wait opacity-60' : ''}`}>
          <input type="radio" name="payment" value={choice} required checked={active} onChange={() => onSelect(choice)} className="mt-1 size-4 shrink-0 accent-[#f5b400]" />
          <span className="min-w-0 space-y-2"><span className="flex items-center gap-2 font-semibold"><Icon aria-hidden="true" className="size-5 shrink-0" />{item.title}</span><span className="block text-sm leading-6 text-muted-foreground">{item.description}</span></span>
        </label>
      })}
    </div>
    {choices.length === 1 && choices[0] === 'cod' && <p role="status" className="rounded-lg bg-shop-soft p-3 text-sm leading-6 text-muted-foreground">{ar ? 'الدفع أونلاين غير متاح لهذا الطلب حاليًا. يمكنك الدفع عند الاستلام أو التواصل مع المتجر بخصوص الدفع بالكارت والتقسيط.' : 'Online payment is currently unavailable for this order. You can pay on delivery or contact the store about card and installment payments.'}</p>}
    {selected && selected !== 'cod' && <p className="flex items-start gap-2 text-xs leading-5 text-muted-foreground"><LockKeyhole aria-hidden="true" className="mt-0.5 size-4 shrink-0" />{ar ? 'بيانات الكارت تُدخل في Paymob. ستعود للموقع لمتابعة طلبك بعد الدفع.' : 'Enter card details in Paymob. Return here to follow your order after payment.'}</p>}
    {!choices.length && <p role="alert" className="text-sm text-danger-foreground">{!checkout.payment_availability_calculable ? (ar ? 'ستظهر وسائل الدفع بعد توفر الشحن والإجمالي.' : 'Payment options will appear once shipping and the total are available.') : (ar ? 'لا توجد وسيلة دفع متاحة لهذا الطلب حاليًا. تواصل مع المتجر.' : 'No payment method is currently available for this order. Contact the store.')}</p>}
  </fieldset>
}
