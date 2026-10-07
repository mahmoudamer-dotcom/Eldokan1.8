import { redirect } from 'next/navigation'

export default function LegacyProductCheckoutPage() {
  redirect('/checkout/cart')
}
