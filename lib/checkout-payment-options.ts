import type { CheckoutResponse } from '@eldokan/customer-api-client'

export type PaymobOptionId = 'card' | 'bank_installments'
export type CheckoutPaymentChoiceId = 'cod' | 'paymob' | PaymobOptionId
export type InstallmentWidgetConfig = { public_key: string; integration_id: number; amount: number; currency: 'EGP' }
export type SelectedInstallmentPlan = { id: string; tenure: number; amount: number; quoteKey: string }
export function installmentQuoteKey(config: InstallmentWidgetConfig) {
  return `${config.integration_id}:${config.currency}:${config.amount}:${config.public_key}`
}
export type CheckoutWithPaymentOptions = CheckoutResponse['data'] & {
  installment_widget?: InstallmentWidgetConfig | null
  installment_widget_status?: 'ready' | 'quote_incomplete' | 'unavailable' | 'gateway_unavailable' | 'widget_disabled' | 'integration_mismatch' | 'public_key_unavailable' | 'below_minimum' | 'configuration_unavailable'
  paymob_options?: { id: PaymobOptionId; name: string; available: boolean }[]
  selected_payment_method?: 'cod' | 'paymob' | null
  selected_paymob_option?: PaymobOptionId | null
}

export function availablePaymobOptions(checkout: CheckoutResponse['data']) {
  const options = (checkout as CheckoutWithPaymentOptions).paymob_options
  if (!Array.isArray(options) || !checkout.payment_methods.some((method) => method.id === 'paymob' && method.available)) return []
  return [...new Set(options.filter((option) => option && option.available === true && ['card', 'bank_installments'].includes(option.id)).map((option) => option.id))]
}

export function checkoutPaymentChoices(checkout: CheckoutResponse['data']): CheckoutPaymentChoiceId[] {
  const choices: CheckoutPaymentChoiceId[] = []
  if (checkout.payment_methods.some((method) => method.id === 'cod' && method.available)) choices.push('cod')
  if (checkout.payment_methods.some((method) => method.id === 'paymob' && method.available)) {
    const options = availablePaymobOptions(checkout)
    choices.push(...(options.length ? options : ['paymob' as const]))
  }
  return choices
}
