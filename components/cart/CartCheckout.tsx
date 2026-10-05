'use client'

import Link from 'next/link'
import { useCart } from './CartProvider'
import CheckoutForm from '@/components/checkout/CheckoutForm'

export default function CartCheckout({ locale, googleMapsApiKey }: { locale: 'ar' | 'en'; googleMapsApiKey?: string }) {
  const { cart, ready, error, busy, updateQuantity } = useCart()
  const ar = locale === 'ar'
  if (!ready) return <p className="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-600">{ar ? 'جارٍ تحميل السلة…' : 'Loading your cart…'}</p>
  if (error && !cart) return <section className="rounded-xl border border-red-200 bg-white p-6 text-red-700" role="alert">{error}</section>
  if (!cart?.items.length) return <section className="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
    <p className="text-gray-600">{ar ? 'السلة فارغة.' : 'Your cart is empty.'}</p>
    <Link href="/cart" className="mt-5 inline-flex rounded-lg border px-5 py-3 font-semibold">{ar ? 'العودة للسلة' : 'Back to cart'}</Link>
  </section>

  const decimals = cart.items.find((item) => item.line_subtotal)?.line_subtotal?.decimals ?? 2
  const currency = cart.items.find((item) => item.line_subtotal)?.line_subtotal?.currency ?? 'EGP'
  const totalMinor = cart.items.reduce((sum, item) => sum + (item.line_subtotal?.amount ?? 0), 0)
  const totalFormatted = `${(totalMinor / (10 ** decimals)).toLocaleString(ar ? 'ar-EG' : 'en', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })} ${currency}`
  const expectedAmountCents = Math.round(totalMinor * 100 / (10 ** decimals))
  const lines = cart.items.map((item) => ({
    productId: item.product_id,
    cartItemId: item.id,
    stockQuantity: item.stock.quantity,
    name: item.name,
    imageUrl: item.image?.url,
    quantity: item.quantity,
    formattedAmount: item.line_subtotal?.formatted ?? '—',
  }))

  return <>
    {!cart.valid && <div role="alert" className="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
      <p className="font-semibold">{ar ? 'بعض المنتجات تحتاج مراجعة قبل الدفع.' : 'Some items need attention before payment.'}</p>
      <Link href="/cart" className="mt-2 inline-flex font-semibold underline">{ar ? 'مراجعة السلة' : 'Review cart'}</Link>
    </div>}
    <CheckoutForm locale={locale} items={lines} totalFormatted={totalFormatted} expectedAmountCents={expectedAmountCents} cartCheckout cartValid={cart.valid} cartBusy={busy} onCartQuantityChange={(itemId, quantity) => updateQuantity(itemId as `cit_${string}`, quantity)} googleMapsApiKey={googleMapsApiKey} />
  </>
}
