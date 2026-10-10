'use client'
import Money from '@/components/i18n/Money'

import type { OrderDetail } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { getEgyptGovernorateLabel } from '@/lib/egypt-governorates'
import { paymentStatusLabel } from '@/lib/order-status'

export default function OrderInvoice({ order }: { order: OrderDetail }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const paid = order.payment_status === 'paid'
  const savedPlan = (order as OrderDetail & { installment_plan?: { id: string; provider: string; selected_quote?: { tenure: number; monthly_amount: number; currency: string; decimals: number; provider_confirmed: false } } | null }).installment_plan
  const installment = order.payment_method === 'paymob' && savedPlan?.provider === 'paymob' && /^[1-9][0-9]{0,9}$/.test(savedPlan.id) ? savedPlan : null
  const quote = installment?.selected_quote
  const planQuote = quote && Number.isInteger(quote.tenure) && quote.tenure > 0 && quote.tenure <= 120 && Number.isSafeInteger(quote.monthly_amount) && quote.monthly_amount > 0 && quote.currency === 'EGP' && quote.decimals === 2 ? quote : null
  const formatPlanAmount = (amount: number) => new Intl.NumberFormat(ar ? 'ar-EG' : 'en-EG', { style: 'currency', currency: 'EGP' }).format(amount / 100)
  const date = new Intl.DateTimeFormat(ar ? 'ar-EG' : 'en-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(order.created_at))
  const method = installment ? text('Bank installments via Paymob', 'تقسيط بنكي عبر Paymob') : order.payment_method === 'paymob' ? 'Paymob' : order.payment_method === 'cod' ? text('Cash on delivery', 'الدفع عند الاستلام') : text('Unavailable', 'غير متاح')

  return <>
    <section dir={ar ? 'rtl' : 'ltr'} className="invoice-print-root mt-6 space-y-5 rounded-xl border border-border p-5">
      <header className="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
        <div><p className="text-sm text-muted-foreground">{text('ElDokan', 'الدكان')}</p><h2 className="text-xl font-bold">{paid ? text('Paid order invoice', 'فاتورة الطلب المدفوع') : text('Order summary', 'ملخص الطلب')}</h2></div>
        <div className="min-w-0 text-sm"><p className="break-all"><strong>{text('Order reference', 'رقم الطلب')}:</strong> {order.id}</p><p><strong>{text('Date', 'التاريخ')}:</strong> {date}</p></div>
      </header>
      <div className="grid gap-3 text-sm sm:grid-cols-2">
        <div className="min-w-0"><h3 className="mb-1 font-bold">{text('Customer', 'بيانات العميل')}</h3><p>{order.address.first_name} {order.address.last_name}</p><p dir="ltr" className={ar ? 'text-right' : ''}>{order.address.phone}</p><p className="break-all">{order.address.email}</p></div>
        <div><h3 className="mb-1 font-bold">{text('Delivery address', 'عنوان التوصيل')}</h3><p>{order.address.street_address}{order.address.address_extra ? ` · ${order.address.address_extra}` : ''}</p><p>{order.address.city} · {getEgyptGovernorateLabel(order.address.state, locale)} · {text('Egypt', 'مصر')}</p><p>{text('Payment', 'الدفع')}: {method}</p><p>{text('Status', 'الحالة')}: {paymentStatusLabel(order.payment_status, locale)}</p></div>
      </div>
      <div className="overflow-x-auto"><table className="w-full border-collapse text-sm">
        <caption className="sr-only">{text('Order items and amounts', 'منتجات الطلب وقيمتها')}</caption>
        <thead><tr className="border-y"><th scope="col" className="py-2 text-start">{text('Item', 'المنتج')}</th><th scope="col" className="py-2 text-end">{text('Qty', 'الكمية')}</th><th scope="col" className="py-2 text-end">{text('Subtotal', 'الإجمالي الفرعي')}</th><th scope="col" className="py-2 text-end">{text('Total', 'الإجمالي')}</th></tr></thead>
        <tbody>{order.lines.map((line, index) => <tr key={`${line.product_id}-${index}`} className="border-b"><td className="py-2">{line.name}</td><td className="py-2 text-end">{line.quantity}</td><td className="py-2 text-end"><Money value={line.subtotal} /></td><td className="py-2 text-end"><Money value={line.total} /></td></tr>)}</tbody>
      </table></div>
      <dl className="ms-auto max-w-sm space-y-1 text-sm">
        <div className="flex justify-between gap-4"><dt>{text('Subtotal', 'المجموع الفرعي')}</dt><dd><Money value={order.subtotal} /></dd></div>
        <div className="flex justify-between gap-4"><dt>{text('Discount', 'الخصم')}</dt><dd><Money value={order.discount} /></dd></div>
        <div className="flex justify-between gap-4"><dt>{text('Shipping', 'الشحن')}</dt><dd><Money value={order.shipping_total} /></dd></div>
        <div className="flex justify-between gap-4"><dt>{text('Tax', 'الضريبة')}</dt><dd><Money value={order.tax} /></dd></div>
        {order.fees.map((fee, index) => <div key={`${fee.name}-${index}`} className="flex justify-between gap-4"><dt>{fee.name}</dt><dd><Money value={fee.total} /></dd></div>)}
        <div className="flex justify-between gap-4 border-t pt-2 text-base font-bold"><dt>{paid ? text('Paid total', 'إجمالي المدفوع') : text('Order total', 'إجمالي الطلب')}</dt><dd><Money value={order.total} /></dd></div>
      </dl>
      {installment && <aside className="space-y-1 rounded-lg bg-shop-soft p-3 text-sm">
        <p className="font-semibold">{text('Selected installment plan', 'خطة التقسيط المختارة')}: {installment.id}</p>
        {planQuote && <dl className="space-y-2 py-2">
          <div className="flex justify-between gap-4"><dt>{text('Selected period', 'المدة المختارة')}</dt><dd>{planQuote.tenure} {text('months', 'شهر')}</dd></div>
          <div className="flex justify-between gap-4"><dt>{text('Monthly installment estimate', 'قيمة القسط الشهرية التقديرية')}</dt><dd>{formatPlanAmount(planQuote.monthly_amount)}</dd></div>
          <div className="flex justify-between gap-4"><dt>{text('Repayment estimate', 'إجمالي الأقساط التقديري')}</dt><dd>{formatPlanAmount(planQuote.monthly_amount * planQuote.tenure)}</dd></div>
        </dl>}
        <p className="leading-6 text-muted-foreground">{text('Installment figures show the plan selected at checkout. Your bank and Paymob confirm the final schedule and financing charges. The purchase total above remains the store invoice amount.', 'أرقام التقسيط توضح الخطة المختارة وقت الشراء. البنك وPaymob يؤكدان الجدول النهائي ورسوم التمويل. إجمالي الشراء بالأعلى هو قيمة فاتورة المتجر.')}</p>
      </aside>}
      {order.order_notes && <p className="text-sm"><strong>{text('Order notes', 'ملاحظات الطلب')}:</strong> {order.order_notes}</p>}
    </section>
    {paid && <button type="button" onClick={() => window.print()} className="print:hidden mt-4 rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-primary-foreground">{text('Print / save invoice', 'طباعة / حفظ الفاتورة')}</button>}
  </>
}
