'use client'

import Link from 'next/link'
import PurchaseJourney from '@/components/shopping/PurchaseJourney'
import { useCart } from './CartProvider'
import ContractCheckout from '@/components/checkout/ContractCheckout'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function CartCheckout() {
  const { locale } = useLocale()
  const { cart, ready, error } = useCart()
  const ar = locale === 'ar'
  if (!ready) return <p className="rounded-xl border border-border bg-card p-8 text-center text-muted-foreground">{ar ? 'جارٍ تحميل السلة…' : 'Loading your cart…'}</p>
  if (error && !cart) return <section className="rounded-xl border border-danger-foreground/30 bg-card p-6 text-danger-foreground" role="alert">{error}</section>
  if (!cart?.items.length) return <section className="rounded-xl border border-dashed border-input bg-card p-10 text-center">
    <p className="text-muted-foreground">{ar ? 'السلة فارغة.' : 'Your cart is empty.'}</p>
    <Link href="/cart" className="mt-5 inline-flex rounded-lg border px-5 py-3 font-semibold">{ar ? 'العودة للسلة' : 'Back to cart'}</Link>
    <Link href="/checkout/result" className="ms-3 mt-5 inline-flex rounded-lg border px-5 py-3 font-semibold">{ar ? 'متابعة آخر عملية شراء' : 'Recover last purchase'}</Link>
  </section>

  return <>
    <PurchaseJourney stage="checkout" />
    <h1 className="mb-7 text-3xl font-bold">{ar ? 'إتمام شراء السلة' : 'Cart checkout'}</h1>
    {!cart.valid && <div role="alert" className="mb-5 rounded-xl border border-shop-accent/40 bg-shop-soft p-4 text-sm text-shop-accent">
      <p className="font-semibold">{ar ? 'بعض المنتجات تحتاج مراجعة قبل الدفع.' : 'Some items need attention before payment.'}</p>
      <Link href="/cart" className="mt-2 inline-flex font-semibold underline">{ar ? 'مراجعة السلة' : 'Review cart'}</Link>
    </div>}
    <ContractCheckout />
  </>
}
