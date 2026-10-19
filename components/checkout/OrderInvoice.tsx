'use client'
import Money from '@/components/i18n/Money'
import Image from 'next/image'
import { useEffect, useState, type ReactNode } from 'react'
import QRCode from 'qrcode'

import type { OrderDetail } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { getEgyptGovernorateLabel } from '@/lib/egypt-governorates'
import { orderNumber, paymentStatusLabel } from '@/lib/order-status'
import { BUSINESS } from '@/lib/legal-policies'

/** A scannable link printed on the invoice. Generated locally; nothing leaves the browser. */
function InvoiceQr({ path, title, caption }: { path: string; title: string; caption: string }) {
  const [svg, setSvg] = useState('')
  useEffect(() => {
    let active = true
    QRCode.toString(new URL(path, window.location.origin).toString(), { type: 'svg', margin: 0, errorCorrectionLevel: 'M' })
      .then((markup) => { if (active) setSvg(markup) }, () => {})
    return () => { active = false }
  }, [path])
  return <figure className="flex min-w-0 items-center gap-4">
    <div role="img" aria-label={title} className="size-32 shrink-0 rounded-lg border border-neutral-200 bg-white p-2 [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: svg }} />
    <figcaption className="min-w-0 text-sm leading-6 text-neutral-500"><strong className="mb-1 block text-base text-neutral-900">{title}</strong>{caption}</figcaption>
  </figure>
}

/** A box as wide as its text; its facts sit on one line, separated by dashes, and wrap only when the line is full. */
function InfoCard({ title, children }: { title: string; children: ReactNode }) {
  const items = (Array.isArray(children) ? children : [children]).filter(Boolean)
  return <div className="max-w-full rounded-lg border border-neutral-200 px-4 py-3 text-sm leading-6">
    <p className="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">{title}</p>
    <p className="mt-1 flex flex-wrap items-center gap-x-2 text-neutral-700">
      {items.map((item, index) => <span key={index} className="contents">
        {index > 0 && <span aria-hidden="true" className="text-neutral-400">-</span>}
        <bdi dir="auto" className="max-w-full [overflow-wrap:anywhere]">{item}</bdi>
      </span>)}
    </p>
  </div>
}

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
  const method = installment ? text('Bank installments via Paymob', 'تقسيط بنكي عبر Paymob') : order.payment_method === 'paymob' ? text('Card via Paymob', 'بطاقة عبر Paymob') : order.payment_method === 'cod' ? text('Cash on delivery', 'الدفع عند الاستلام') : text('Unavailable', 'غير متاح')
  const label = 'text-[11px] font-semibold uppercase tracking-wider text-neutral-500'

  return <>
    {/* Printed as paper: fixed light colors in both site themes. */}
    <section dir={ar ? 'rtl' : 'ltr'} className="invoice-print-root mt-6 overflow-hidden rounded-xl border border-neutral-200 bg-white text-neutral-900 shadow-sm">
      <div className="h-1.5 bg-[#f5b400]" aria-hidden="true" />
      <div className="space-y-6 p-5 sm:p-8">
        <header className="flex flex-wrap items-start justify-between gap-5">
          <div className="space-y-2">
            <Image src="/image/Eldokan-logo.webp" alt={text('ElDokan', 'الدكان')} width={132} height={72} className="h-14 w-auto object-contain" />
            <p className="text-xs leading-5 text-neutral-500">{BUSINESS.address[locale]}<br /><bdi>{BUSINESS.email}</bdi> · <bdi dir="ltr">{BUSINESS.phone}</bdi></p>
          </div>
          <div className="text-end">
            <p className={label}>{paid ? text('Invoice', 'فاتورة') : text('Order summary', 'ملخص الطلب')}</p>
            <p className="mt-1 text-3xl font-extrabold tracking-tight"><bdi>{orderNumber(order)}</bdi></p>
            <p className="mt-1 text-sm text-neutral-600">{date}</p>
            <span className={`mt-2 inline-block rounded-full px-3 py-1 text-xs font-bold ${paid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'}`}>{paymentStatusLabel(order.payment_status, locale)}</span>
          </div>
        </header>

        <div className="flex flex-wrap items-start gap-3">
          <InfoCard title={text('Customer', 'العميل')}>
            <strong className="font-semibold">{order.address.first_name} {order.address.last_name}</strong>
            <span dir="ltr">{order.address.phone}</span>
            <span dir="ltr">{order.address.email}</span>
          </InfoCard>
          <InfoCard title={text('Deliver to', 'عنوان التوصيل')}>
            <strong className="font-semibold">{order.address.street_address}</strong>
            {order.address.address_extra && <span>{order.address.address_extra}</span>}
            <span>{order.address.city}</span>
            <span>{getEgyptGovernorateLabel(order.address.state, locale)}</span>
            <span>{text('Egypt', 'مصر')}</span>
          </InfoCard>
          <InfoCard title={text('Payment & delivery', 'الدفع والشحن')}>
            <strong className="font-semibold">{method}</strong>
            <span className={`rounded-full px-2 text-xs font-bold leading-5 ${paid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'}`}>{paymentStatusLabel(order.payment_status, locale)}</span>
            {order.shipping?.name && <span>{ar && order.shipping.type === 'door_delivery' ? 'توصيل للمنزل' : ar && order.shipping.type === 'pickup' ? 'استلام من الفرع' : order.shipping.name}</span>}
          </InfoCard>
        </div>

        <div className="overflow-x-auto rounded-lg border border-neutral-200"><table className="w-full border-collapse text-sm">
          <caption className="sr-only">{text('Order items and amounts', 'منتجات الطلب وقيمتها')}</caption>
          <thead className="bg-neutral-900 text-white"><tr><th scope="col" className="w-10 px-3 py-2.5 text-start font-semibold">#</th><th scope="col" className="px-3 py-2.5 text-start font-semibold">{text('Item', 'المنتج')}</th><th scope="col" className="px-3 py-2.5 text-center font-semibold">{text('Qty', 'الكمية')}</th><th scope="col" className="px-3 py-2.5 text-end font-semibold">{text('Total', 'الإجمالي')}</th></tr></thead>
          <tbody>{order.lines.map((line, index) => <tr key={`${line.product_id}-${index}`} className="border-t border-neutral-200 even:bg-neutral-50"><td className="px-3 py-3 text-neutral-500">{index + 1}</td><td className="px-3 py-3 font-medium">{line.name}</td><td className="px-3 py-3 text-center">{line.quantity}</td><td className="px-3 py-3 text-end"><Money value={line.total} /></td></tr>)}</tbody>
        </table></div>

        <div className="flex flex-wrap items-end justify-between gap-6">
          <div className="max-w-sm space-y-3 text-sm">
            {order.order_notes && <p><strong>{text('Order notes', 'ملاحظات الطلب')}:</strong> {order.order_notes}</p>}
            <p className="text-neutral-600">{text('Thank you for shopping with ElDokan.', 'شكرًا لتسوقك من الدكان.')}</p>
          </div>
          <dl className="w-full max-w-xs space-y-1.5 text-sm sm:ms-auto">
            <div className="flex justify-between gap-4"><dt className="text-neutral-600">{text('Subtotal', 'المجموع الفرعي')}</dt><dd><Money value={order.subtotal} /></dd></div>
            {order.discount.amount > 0 && <div className="flex justify-between gap-4"><dt className="text-neutral-600">{text('Discount', 'الخصم')}</dt><dd>−<Money value={order.discount} /></dd></div>}
            <div className="flex justify-between gap-4"><dt className="text-neutral-600">{text('Shipping', 'الشحن')}</dt><dd><Money value={order.shipping_total} /></dd></div>
            {order.tax.amount > 0 && <div className="flex justify-between gap-4"><dt className="text-neutral-600">{text('Tax', 'الضريبة')}</dt><dd><Money value={order.tax} /></dd></div>}
            {order.fees.map((fee, index) => <div key={`${fee.name}-${index}`} className="flex justify-between gap-4"><dt className="text-neutral-600">{fee.name}</dt><dd><Money value={fee.total} /></dd></div>)}
            <div className="mt-2 flex justify-between gap-4 rounded-lg bg-[#fff4cc] px-3 py-2.5 text-base font-extrabold"><dt>{paid ? text('Total paid', 'الإجمالي المدفوع') : text('Total due', 'الإجمالي المطلوب')}</dt><dd><Money value={order.total} /></dd></div>
          </dl>
        </div>

        {installment && <aside className="space-y-1 rounded-lg bg-neutral-50 p-3 text-sm">
          <p className="font-semibold">{text('Selected installment plan', 'خطة التقسيط المختارة')}: {installment.id}</p>
          {planQuote && <dl className="space-y-2 py-2">
            <div className="flex justify-between gap-4"><dt>{text('Selected period', 'المدة المختارة')}</dt><dd>{planQuote.tenure} {text('months', 'شهر')}</dd></div>
            <div className="flex justify-between gap-4"><dt>{text('Monthly installment estimate', 'قيمة القسط الشهرية التقديرية')}</dt><dd>{formatPlanAmount(planQuote.monthly_amount)}</dd></div>
            <div className="flex justify-between gap-4"><dt>{text('Repayment estimate', 'إجمالي الأقساط التقديري')}</dt><dd>{formatPlanAmount(planQuote.monthly_amount * planQuote.tenure)}</dd></div>
          </dl>}
          <p className="leading-6 text-neutral-600">{text('Installment figures show the plan selected at checkout. Your bank and Paymob confirm the final schedule and financing charges. The purchase total above remains the store invoice amount.', 'أرقام التقسيط توضح الخطة المختارة وقت الشراء. البنك وPaymob يؤكدان الجدول النهائي ورسوم التمويل. إجمالي الشراء بالأعلى هو قيمة فاتورة المتجر.')}</p>
        </aside>}

        <footer className="grid gap-4 border-t border-dashed border-neutral-300 pt-5 sm:grid-cols-2">
          <InvoiceQr path={`/orders/${order.id}`} title={text('Track this order', 'تابع طلبك')} caption={text('Scan to open this order, its status and invoice.', 'امسح الكود لفتح الطلب وحالته والفاتورة.')} />
          <InvoiceQr path="/policies/returns" title={text('Returns & refunds', 'سياسة الاسترجاع')} caption={text('Scan to read how to return an item and get a refund.', 'امسح الكود لمعرفة طريقة الاسترجاع واسترداد المبلغ.')} />
        </footer>
        <p className="break-all text-[10px] leading-4 text-neutral-400">{text('Reference', 'المرجع')}: {order.id}</p>
      </div>
    </section>
    {paid && <button type="button" onClick={() => window.print()} className="print:hidden mt-4 rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-primary-foreground">{text('Print / save invoice', 'طباعة / حفظ الفاتورة')}</button>}
  </>
}
