import CartCheckout from '@/components/cart/CartCheckout'
import { getLocale } from '@/lib/server-locale'

export default async function CartCheckoutPage() {
  const locale = await getLocale()
  return <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:py-14">
    <h1 className="mb-7 text-3xl font-bold">{locale === 'ar' ? 'إتمام شراء السلة' : 'Cart checkout'}</h1>
    <CartCheckout locale={locale} googleMapsApiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY} />
  </main>
}
