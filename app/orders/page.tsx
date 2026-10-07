import OrderHistory from '@/components/checkout/OrderHistory'
import { getLocale } from '@/lib/server-locale'

export default async function OrdersPage() {
  const locale = await getLocale()
  return <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-10 sm:py-14" dir={locale === 'ar' ? 'rtl' : 'ltr'}>
    <h1 className="mb-7 text-3xl font-bold">{locale === 'ar' ? 'طلباتي' : 'My orders'}</h1>
    <OrderHistory />
  </main>
}
