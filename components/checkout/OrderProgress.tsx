'use client'

import { Check, PackageCheck, Package, Truck, ClipboardCheck } from 'lucide-react'
import type { OrderStatus, OrderDetail } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { orderStatusLabel } from '@/lib/order-status'

export default function OrderProgress({ status, fulfillments = [] }: { status: OrderStatus; fulfillments?: OrderDetail['fulfillments'] }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  if (['cancelled', 'failed', 'refunded'].includes(status)) return <p role="status" className="my-5 rounded-xl bg-muted px-4 py-3 font-semibold">{orderStatusLabel(status, locale)}</p>
  const pickup = status === 'awaiting_pickup'
  const current = status === 'delivered' ? 3 : status === 'shipped' || pickup ? 2 : status === 'processing' ? 1 : 0
  const stages = [
    { name: ar ? 'تم تسجيل الطلب' : 'Order received', icon: ClipboardCheck },
    { name: ar ? 'تجهيز المنتجات' : 'Preparing your items', icon: Package },
    { name: pickup ? (ar ? 'جاهز للاستلام' : 'Ready for pickup') : (ar ? 'الشحن' : 'Shipping'), icon: Truck },
    { name: ar ? 'تم التوصيل' : 'Delivered', icon: PackageCheck },
  ]
  return <section aria-label={ar ? 'مراحل طلبك' : 'Your order progress'} className="my-6 rounded-2xl border border-border bg-background p-4 sm:p-5 print:hidden">
    <div className="mb-5 flex flex-wrap justify-between gap-2"><h2 className="font-bold">{ar ? 'طلبك وصل لفين؟' : 'Where is your order?'}</h2><span className="text-xs text-muted-foreground">{ar ? 'حسب آخر حالة مسجلة للطلب' : 'Based on the latest recorded order status'}</span></div>
    <ol className="grid grid-cols-2 gap-4 sm:grid-cols-4">
      {stages.map(({ name, icon: Icon }, index) => <li key={index} aria-current={index === current ? 'step' : undefined} className={`flex items-center gap-2 rounded-xl p-2 text-xs sm:flex-col sm:items-start ${index === current ? 'bg-shop-soft text-shop-accent' : index < current ? 'text-success-foreground' : 'text-muted-foreground'}`}>
        <span className={`grid size-8 shrink-0 place-items-center rounded-full ${index <= current ? 'bg-card' : 'bg-muted'}`}>{index < current ? <Check aria-hidden="true" className="size-4" /> : <Icon aria-hidden="true" className="size-4" />}</span><span className="font-semibold">{name}{index === current && <span className="sr-only"> {ar ? 'الحالة الحالية' : 'Current status'}</span>}</span>
      </li>)}
    </ol>
    {fulfillments.length > 0 && <div className="mt-5 space-y-2 border-t border-border pt-4">
      <h3 className="text-sm font-semibold">{ar ? 'متابعة المنتجات حسب البائع' : 'Item progress by seller'}</h3>
      {fulfillments.map((fulfillment, index) => <div key={`${fulfillment.seller.id}:${index}`} className="flex flex-wrap justify-between gap-2 text-sm"><span>{fulfillment.seller.name}</span><span className="font-semibold text-muted-foreground">{fulfillment.status === 'pending' ? (ar ? 'بانتظار التجهيز' : 'Awaiting preparation') : orderStatusLabel(fulfillment.status, locale)}</span></div>)}
    </div>}
  </section>
}
