import type { CheckoutAddressInput } from '@eldokan/customer-api-client'

// The last delivery address used on this device, so returning guests do not
// type it again. It stays in this browser only and is never sent anywhere
// until the customer reviews an order.
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
    return address.first_name && address.phone ? address as unknown as CheckoutAddressInput : null
  } catch { return null }
}

export function saveCheckoutAddress(address: CheckoutAddressInput) {
  try {
    window.localStorage.setItem(KEY, JSON.stringify(Object.fromEntries(FIELDS.map((field) => [field, address[field] ?? '']))))
  } catch { /* Storage unavailable: the customer types the address next time. */ }
}
