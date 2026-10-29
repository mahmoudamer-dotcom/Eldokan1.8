import { redirect } from 'next/navigation'
import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('Checkout', 'إتمام الشراء', { robots: { index: false, follow: false } })

export default function LegacyProductCheckoutPage() {
  redirect('/checkout/cart')
}
