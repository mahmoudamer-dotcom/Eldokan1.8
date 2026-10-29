import CartCheckout from '@/components/cart/CartCheckout'
import CheckoutHeader from '@/components/checkout/CheckoutHeader'
import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('Checkout', 'إتمام الشراء', { robots: { index: false, follow: false } })

export default function CartCheckoutPage() {
  return <>
    <CheckoutHeader />
    <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:py-10">
      <CartCheckout />
    </main>
  </>
}
