import CartCheckout from '@/components/cart/CartCheckout'
import CheckoutHeader from '@/components/checkout/CheckoutHeader'

export default function CartCheckoutPage() {
  return <>
    <CheckoutHeader />
    <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:py-10">
      <CartCheckout />
    </main>
  </>
}
