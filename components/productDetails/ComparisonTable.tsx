'use client'

import Image from 'next/image'
import Link from 'next/link'
import { useState } from 'react'
import type { ProductDetail } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'

function plainText(html: string) {
  return html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '')
    .replace(/<\/(p|li|div|h[1-6])>/gi, '\n').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&quot;/g, '"').trim()
}

export default function ComparisonTable({ products }: { products: ProductDetail[] }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const missing = text('Not provided', 'غير مذكور')
  const [differencesOnly, setDifferencesOnly] = useState(false)
  const attributes = new Map<string, string>()
  for (const product of products) for (const attribute of product.attributes) {
    attributes.set(attribute.id ?? attribute.slug.toLowerCase(), attribute.name)
  }
  const stock = (product: ProductDetail) => ({ in_stock: text('In stock', 'متوفر'), out_of_stock: text('Out of stock', 'غير متوفر'), on_backorder: text('On backorder', 'متاح بالحجز') })[product.stock.status]
  const groups = [
    { title: text('Overview', 'نظرة عامة'), rows: [
      { name: text('Current price', 'السعر الحالي'), values: products.map((p) => p.pricing.price?.formatted ?? missing) },
      { name: text('Regular price', 'السعر الأصلي'), values: products.map((p) => p.pricing.regular_price?.formatted ?? missing) },
      { name: text('Availability', 'التوفر'), values: products.map(stock) },
      { name: text('Brand', 'العلامة التجارية'), values: products.map((p) => p.brand?.name ?? missing) },
      { name: text('Seller', 'البائع'), values: products.map((p) => p.seller?.name ?? missing) },
      { name: text('Store rating', 'تقييم المتجر'), values: products.map((p) => p.rating_count > 0 ? `${p.average_rating.toFixed(1)} / 5 (${p.rating_count})` : text('No ratings', 'لا توجد تقييمات')) },
      { name: 'SKU', values: products.map((p) => p.sku ?? missing) },
      { name: text('Categories', 'التصنيفات'), values: products.map((p) => p.categories.map((c) => c.name).join(ar ? '، ' : ', ') || missing) },
    ] },
    { title: text('Specifications & available options', 'المواصفات والاختيارات المتاحة'), rows: [...attributes].map(([key, name]) => ({ name, values: products.map((p) => p.attributes.find((a) => (a.id ?? a.slug.toLowerCase()) === key)?.options.map((o) => o.name).join(ar ? '، ' : ', ') || missing) })) },
    { title: text('Delivery & warranty', 'التوصيل والضمان'), rows: [
      { name: text('Delivery information', 'معلومات التوصيل'), values: products.map((p) => p.delivery.label ?? missing) },
      { name: text('Estimated delivery (days)', 'مدة التوصيل المعلنة (أيام)'), values: products.map((p) => p.delivery.min_days != null || p.delivery.max_days != null ? `${p.delivery.min_days ?? '—'} – ${p.delivery.max_days ?? '—'}` : missing) },
      { name: text('Warranty', 'الضمان'), values: products.map((p) => p.warranty.label ?? missing) },
      { name: text('Warranty duration', 'مدة الضمان'), values: products.map((p) => p.warranty.duration != null ? `${p.warranty.duration} ${p.warranty.unit ?? ''}` : missing) },
      { name: text('Warranty type', 'نوع الضمان'), values: products.map((p) => p.warranty.type ?? missing) },
    ] },
    { title: text('Product details', 'تفاصيل المنتج'), rows: [
      { name: text('Summary', 'الملخص'), values: products.map((p) => plainText(p.short_description_html) || missing) },
      { name: text('Full description', 'الوصف الكامل'), values: products.map((p) => plainText(p.description_html) || missing) },
      { name: text('Available purchasable variations', 'عدد الاختيارات القابلة للشراء'), values: products.map((p) => p.type === 'variable' ? String(p.variations.filter((v) => v.purchasable).length) : text('Single product', 'منتج بدون اختيارات')) },
    ] },
  ]
  const differs = (values: string[]) => new Set(values.map((value) => value.trim().toLocaleLowerCase())).size > 1
  const visibleGroups = groups.map((group) => ({ ...group, rows: group.rows.filter((row) => !differencesOnly || differs(row.values)) })).filter((group) => group.rows.length)

  return <div className="space-y-4">
    <div className="flex flex-wrap items-center justify-between gap-3"><label className="flex cursor-pointer items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={differencesOnly} onChange={(event) => setDifferencesOnly(event.target.checked)} className="size-4 accent-amber-500" />{text('Show differences only', 'عرض الاختلافات فقط')}</label><p className="text-xs text-muted-foreground">{text('Missing information is not a product advantage.', 'المعلومة غير المذكورة لا تعني ميزة للمنتج.')}</p></div>
    <div className="overflow-auto rounded-xl border border-border bg-card"><table className="w-full min-w-[680px] border-collapse text-start text-sm"><caption className="sr-only">{text('Full product comparison', 'مقارنة تفاصيل المنتجات')}</caption>
      <thead><tr><th scope="col" className="sticky start-0 z-10 w-40 min-w-32 bg-background p-4 text-start">{text('Compare features', 'مقارنة المواصفات')}</th>{products.map((p) => <th scope="col" key={p.id} className="min-w-52 max-w-80 bg-card p-4 text-start">{(p.images[0]?.url ?? p.image?.url) && <Image src={p.images[0]?.url ?? p.image!.url} alt={p.name} width={120} height={120} className="mb-3 h-28 w-full object-contain" />}<Link href={`/product/${p.id}`} className="line-clamp-3 font-semibold hover:underline">{p.name}</Link><p className="mt-2 font-bold text-shop-accent">{p.pricing.price?.formatted ?? missing}</p></th>)}</tr></thead>
      {visibleGroups.map((group) => <tbody key={group.title}><tr><th colSpan={products.length + 1} className="border-y bg-shop-soft px-4 py-3 text-start font-bold text-shop-accent">{group.title}</th></tr>{group.rows.map((row) => <tr key={row.name} className={differs(row.values) ? 'border-t bg-shop-soft/30' : 'border-t'}><th scope="row" className="sticky start-0 z-10 bg-background p-4 text-start align-top font-medium">{row.name}</th>{row.values.map((value, index) => <td key={products[index].id} className="min-w-52 max-w-80 whitespace-pre-line break-words p-4 align-top leading-6 text-muted-foreground">{value}</td>)}</tr>)}</tbody>)}
      <tbody><tr className="border-t"><th scope="row" className="sticky start-0 bg-background p-4 text-start">{text('View details & choose options', 'عرض التفاصيل واختيار المواصفات')}</th>{products.map((p) => <td key={p.id} className="p-4"><Link href={`/product/${p.id}`} className="inline-flex rounded-lg bg-[#f5b400] text-primary-foreground px-4 py-2.5 font-semibold">{text('View product', 'عرض المنتج')}</Link></td>)}</tr></tbody>
    </table></div>
    {!visibleGroups.length && <p role="status" className="p-4 text-center text-sm text-muted-foreground">{text('No differences in the available details.', 'لا توجد اختلافات في التفاصيل المتاحة.')}</p>}
    <p className="text-xs leading-6 text-muted-foreground">{text('Information comes from the store. Delivery is confirmed for your address at checkout. For products with options, select a variation on the product page to confirm its price and availability.', 'البيانات من المتجر. التوصيل بيتأكد حسب عنوانك عند إتمام الشراء. للمنتجات ذات الاختيارات، اختار النسخة من صفحة المنتج لتأكيد سعرها وتوفرها.')}</p>
  </div>
}
