// Server-only capability storage. Never return these credentials to browser JavaScript.
export const GUEST_ORDER_HISTORY_COOKIE = 'eldokan_guest_order_history'
export type SavedGuestOrder = {
  order_id: `ord_${string}`
  guest_access: { credential: string; csrf_token: string }
}

export function readGuestOrderHistory(value: string | undefined): SavedGuestOrder[] {
  if (!value || value.length > 4000) return []
  try {
    const items: unknown = JSON.parse(Buffer.from(decodeURIComponent(value), 'base64url').toString('utf8'))
    if (!Array.isArray(items) || items.length > 3) return []
    return items.filter((item): item is SavedGuestOrder =>
      !!item && typeof item === 'object'
      && typeof item.order_id === 'string' && /^ord_[a-f0-9]{64}$/.test(item.order_id)
      && !!item.guest_access && typeof item.guest_access === 'object'
      && typeof item.guest_access.credential === 'string' && /^gok_[a-f0-9]{64}$/.test(item.guest_access.credential)
      && typeof item.guest_access.csrf_token === 'string' && item.guest_access.csrf_token.length > 0 && item.guest_access.csrf_token.length <= 256)
  } catch { return [] }
}

export function guestOrderHistoryCookie(previous: SavedGuestOrder[], order: SavedGuestOrder, secure: boolean) {
  const items = [...previous.filter((item) => item.order_id !== order.order_id), order].slice(-3)
  const encoded = Buffer.from(JSON.stringify(items)).toString('base64url')
  if (encoded.length > 3500) throw new Error('Guest order history exceeds cookie size limit.')
  return `${GUEST_ORDER_HISTORY_COOKIE}=${encodeURIComponent(encoded)}; Path=/; HttpOnly; SameSite=Lax; Max-Age=86400${secure ? '; Secure' : ''}`
}
