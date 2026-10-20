import type { CheckoutAddressInput } from '@eldokan/customer-api-client'

// The delivery details typed on this device, saved as the customer types (with
// or without an account), so a reload or a later visit does not lose them. They
// stay in this browser only and are sent nowhere until the customer reviews an order.
const KEY = 'eldokan:checkout-address'
const FIELDS = ['first_name', 'last_name', 'phone', 'email', 'state', 'city', 'street_address', 'address_extra'] as const

export function loadCheckoutAddress(): CheckoutAddressInput | null {
  try {
    const stored = JSON.parse(window.localStorage.getItem(KEY) ?? 'null') as Record<string, unknown> | null
    if (!stored || typeof stored !== 'object') return null
    const address = { country: 'EG' } as Record<string, string>
    for (const field of FIELDS) {
      const value = stored[field] ?? ''
      if (typeof value !== 'string' || value.length > 500) return null
      address[field] = value
    }
    // Keep partly filled forms too: anything typed is worth restoring.
    return FIELDS.some((field) => address[field].trim()) ? address as unknown as CheckoutAddressInput : null
  } catch { return null }
}

export function saveCheckoutAddress(address: CheckoutAddressInput) {
  try {
    window.localStorage.setItem(KEY, JSON.stringify(Object.fromEntries(FIELDS.map((field) => [field, address[field] ?? '']))))
  } catch { /* Storage unavailable: the customer types the address next time. */ }
}
