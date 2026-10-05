const STORAGE_KEY = 'eldokan:verified-order-references:v1'
const MAX_ORDERS = 25

export function rememberVerifiedOrder(reference: string) {
  if (typeof window === 'undefined' || !/^eldokan\.[A-Za-z0-9_-]{1,3000}\.[a-f0-9]{64}$/.test(reference)) return
  try {
    const existing = readVerifiedOrderReferences()
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify([reference, ...existing.filter((item) => item !== reference)].slice(0, MAX_ORDERS)))
  } catch {
    // Private browsing or storage policy can disable localStorage.
  }
}

export function readVerifiedOrderReferences(): string[] {
  if (typeof window === 'undefined') return []
  try {
    const value: unknown = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]')
    if (!Array.isArray(value)) return []
    return value.filter((item): item is string => typeof item === 'string' && /^eldokan\.[A-Za-z0-9_-]{1,3000}\.[a-f0-9]{64}$/.test(item)).slice(0, MAX_ORDERS)
  } catch {
    return []
  }
}
