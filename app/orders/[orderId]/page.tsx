import AccountOrderDetail from '@/components/checkout/AccountOrderDetail'
import { getLocale } from '@/lib/server-locale'

export default async function AccountOrderPage({ params }: { params: Promise<{ orderId: string }> }) {
  const [{ orderId }, locale] = await Promise.all([params, getLocale()])
  return <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-10 sm:py-14" dir={locale === 'ar' ? 'rtl' : 'ltr'}>
    <AccountOrderDetail orderId={orderId} />
  </main>
}
