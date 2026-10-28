/** Fired after the store confirms an "add to cart", so the page can confirm it without leaving. */
export const CART_ADDED_EVENT = 'eldokan:cart-added'

export type CartAddedDetail = { name: string; imageUrl?: string; quantity: number }

export function announceCartAdd(detail: CartAddedDetail) {
  if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent<CartAddedDetail>(CART_ADDED_EVENT, { detail }))
}
