'use client'

import { useState } from 'react'
import Link from 'next/link'
import Image from 'next/image'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useCart } from './CartProvider'

export default function CartPage() {
  const { locale } = useLocale()
  const { cart, ready, busy, error, updateQuantity, removeItem, refresh } = useCart()
  const ar = locale === 'ar'
  const t = (en: string, arabic: string) => ar ? arabic : en
  const subtotalMinor = cart?.items.reduce((sum, item) => sum + (item.line_subtotal?.amount ?? 0), 0) ?? 0
  const currency = cart?.items.find((item) => item.line_subtotal)?.line_subtotal?.currency ?? 'EGP'
  const decimals = cart?.items.find((item) => item.line_subtotal)?.line_subtotal?.decimals ?? 2
  const subtotal = (subtotalMinor / (10 ** decimals)).toLocaleString(ar ? 'ar-EG' : 'en', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })

  return <main className="mx-auto w-full max-w-6xl flex-1 px-3 py-7 sm:px-4 sm:py-14" dir={ar ? 'rtl' : 'ltr'}>
    <h1 className="mb-7 text-3xl font-bold">{t('Shopping cart', 'سلة التسوق')}</h1>
    {!ready ? <p className="rounded-xl border border-border bg-card p-8 text-center text-muted-foreground">{t('Loading your cart…', 'جارٍ تحميل السلة…')}</p>
      : error && !cart ? <section className="rounded-xl border border-danger-foreground/30 bg-card p-6"><p role="alert" className="text-danger-foreground">{error}</p><button type="button" onClick={() => void refresh()} className="mt-4 rounded-lg border px-4 py-2 font-semibold">{t('Try again', 'حاول مرة أخرى')}</button></section>
        : !cart?.items.length ? <section className="rounded-2xl border border-dashed border-input bg-card p-10 text-center">
          <p className="text-muted-foreground">{t('Your cart is empty.', 'سلة التسوق فارغة.')}</p>
          <Link href="/" className="mt-5 inline-flex rounded-lg bg-[#f5b400] px-5 py-3 font-semibold text-primary-foreground">{t('Continue shopping', 'تابع التسوق')}</Link>
        </section> : <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <section className="divide-y divide-border rounded-2xl border border-border bg-card px-3 shadow-sm sm:px-5">
            {cart.items.map((item) => <article key={item.id} className="flex flex-wrap items-center gap-3 py-4 sm:gap-4 sm:py-5">
              <CartItemImage key={item.id} src={item.image?.url} alt={item.image?.alt || item.name} />
              <div className="min-w-0 flex-1 basis-[calc(100%-5rem)] sm:basis-[calc(100%-6rem)]">
                <Link href={`/product/${item.product_id}`} className="break-words font-semibold hover:text-shop-accent">{item.name}</Link>
                {item.selected_attributes.length > 0 && <p className="mt-1 text-sm text-muted-foreground">{item.selected_attributes.map((attribute) => `${attribute.attribute_slug}: ${attribute.option_slug ?? ''}`).join(' · ')}</p>}
                <p className="mt-1 text-sm text-muted-foreground">{item.unit_price?.formatted ?? t('Price unavailable', 'السعر غير متاح')}</p>
                {!item.valid && <ul className="mt-2 list-inside list-disc text-sm text-danger-foreground">{item.issues.map((issue, index) => <li key={`${issue.code}-${index}`}>{issue.message}</li>)}</ul>}
              </div>
              <div className="flex items-center gap-2">
                <button type="button" aria-label={t('Decrease quantity', 'تقليل الكمية')} disabled={busy || item.quantity <= 1} onClick={() => void updateQuantity(item.id as `cit_${string}`, item.quantity - 1).catch(() => {})} className="grid size-11 place-items-center rounded-lg border disabled:opacity-50">−</button>
                <span className="min-w-8 text-center font-semibold">{item.quantity}</span>
                <button type="button" aria-label={t('Increase quantity', 'زيادة الكمية')} disabled={busy || item.quantity >= 999 || (item.stock.quantity !== null && item.quantity >= item.stock.quantity)} onClick={() => void updateQuantity(item.id as `cit_${string}`, item.quantity + 1).catch(() => {})} className="grid size-11 place-items-center rounded-lg border disabled:opacity-50">+</button>
              </div>
              <p className="min-w-24 text-end font-bold">{item.line_subtotal?.formatted ?? '—'}</p>
              <button type="button" disabled={busy} onClick={() => void removeItem(item.id as `cit_${string}`).catch(() => {})} className="text-sm font-semibold text-danger-foreground hover:underline disabled:opacity-50">{t('Remove', 'حذف')}</button>
            </article>)}
            {error && <p role="alert" className="pb-4 text-sm text-danger-foreground">{error}</p>}
          </section>
          <aside className="rounded-2xl border border-border bg-card p-5 shadow-sm lg:sticky lg:top-48">
            <h2 className="text-lg font-bold">{t('Order summary', 'ملخص الطلب')}</h2>
            <div className="mt-4 flex justify-between border-t border-border pt-4 text-sm"><span>{t('Items', 'المنتجات')} ({cart.count})</span><span>{subtotal} {currency}</span></div>
            <p className="mt-4 text-xs text-muted-foreground">{t('Shipping and the final total are calculated at checkout after entering your address.', 'الشحن والإجمالي النهائي بيتحسبوا عند إتمام الشراء بعد إدخال عنوانك.')}</p>
            {!cart.valid && <p className="mt-4 rounded-lg bg-shop-soft p-3 text-sm text-shop-accent">{t('Resolve the item availability issues before continuing.', 'عالج مشاكل توفر المنتجات قبل المتابعة.')}</p>}
            <Link aria-disabled={!cart.valid} href={cart.valid ? '/checkout/cart' : '#'} onClick={(event) => { if (!cart.valid) event.preventDefault() }} className={`mt-5 flex justify-center rounded-xl bg-[#f5b400] px-5 py-3 font-bold text-primary-foreground ${!cart.valid ? 'pointer-events-none opacity-50' : 'hover:bg-[#e4a600]'}`}>{t('Continue to checkout', 'المتابعة لإتمام الشراء')}</Link>
          </aside>
        </div>}
  </main>
}

function CartItemImage({ src, alt }: { src?: string; alt: string }) {
  const [failedSrc, setFailedSrc] = useState('')
  const normalizedSrc = src?.replace(/^https?:\/\/(www\.)?eldokan\.com\//i, 'https://www.eldokan.com/')
  return normalizedSrc && failedSrc !== normalizedSrc
    ? <Image src={normalizedSrc} alt={alt} width={80} height={80} onError={() => setFailedSrc(normalizedSrc)} className="size-16 shrink-0 rounded-lg bg-background object-contain sm:size-20" />
    : <div className="size-16 shrink-0 rounded-lg bg-muted sm:size-20" role="img" aria-label={alt} />
}
