import { redirect } from 'next/navigation'
import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('Checkout', 'إتمام الشراء', { robots: { index: false, follow: false } })

/** Checkout always starts from the cart; a bare /checkout link should not 404. */
export default function CheckoutIndex() {
  redirect('/checkout/cart')
}
