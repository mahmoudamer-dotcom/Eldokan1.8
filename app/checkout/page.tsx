import { redirect } from 'next/navigation'

/** Checkout always starts from the cart; a bare /checkout link should not 404. */
export default function CheckoutIndex() {
  redirect('/checkout/cart')
}
