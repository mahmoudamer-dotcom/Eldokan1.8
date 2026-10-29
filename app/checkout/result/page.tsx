import OrderResult from '@/components/checkout/OrderResult'
import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('Order status', 'حالة الطلب', { robots: { index: false, follow: false } })

export default function CheckoutResultPage() {
  return <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-12 sm:py-16">
    <OrderResult />
  </main>
}
