import Link from 'next/link'
import OrderHistory from '@/components/checkout/OrderHistory'
import { getLocale } from '@/lib/server-locale'

export default async function AccountOrdersPage() {
  const locale = await getLocale()
  return <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-10" dir={locale === 'ar' ? 'rtl' : 'ltr'}>
    <Link href="/account" className="text-sm font-semibold underline">{locale === 'ar' ? 'العودة لحسابي' : 'Back to my account'}</Link>
    <h1 className="my-6 text-3xl font-bold">{locale === 'ar' ? 'طلباتي' : 'My orders'}</h1>
    <OrderHistory />
  </main>
}
