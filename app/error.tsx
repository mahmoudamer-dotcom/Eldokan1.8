'use client'

import Link from 'next/link'
import { useLocale } from '@/components/i18n/LocaleProvider'
import StoreSignBag from '@/components/ui/StoreSignBag'

/** Any page that fails to load: the 404 store sign, with "try again" re-rendering the page. */
export default function ErrorPage({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto flex w-full max-w-6xl flex-1 flex-col-reverse items-center justify-center gap-8 px-4 py-10 sm:px-6 md:flex-row md:justify-between md:gap-12 md:py-16 lg:px-8">
    <title>{text('Something went wrong | Eldokan', 'حصلت مشكلة | الدكان')}</title>
    <div className="flex w-full max-w-xl flex-col items-center gap-4 text-center md:items-start md:text-start">
      <span className="rounded-full bg-foreground px-3.5 py-0.5 text-sm font-extrabold text-background">{text('Something went wrong', 'حصلت مشكلة')}</span>
      <h1 className="text-3xl font-black leading-tight text-balance sm:text-4xl lg:text-5xl">{text('This page didn’t load', 'الصفحة دي ما فتحتش')}</h1>
      <p className="text-base leading-8 text-muted-foreground sm:text-lg">{text('Try again in a moment. If you were checking out, open your order status before starting another purchase.', 'حاول مرة تانية كمان شوية. لو كنت بتكمل طلب، افتح حالة الطلب قبل ما تبدأ شراء جديد.')}</p>
      <div className="mt-2 flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
        <button type="button" onClick={() => retry()} className="inline-flex min-h-12 items-center justify-center rounded-full bg-[#f5b400] px-7 font-extrabold text-[#1d1d1b] transition hover:brightness-95">{text('Try again', 'حاول مرة تانية')}</button>
        <Link href="/" className="inline-flex min-h-12 items-center justify-center rounded-full border border-foreground px-7 font-bold transition hover:bg-foreground hover:text-background">{text('Back to home', 'ارجع للرئيسية')}</Link>
        <Link href="/checkout/result" className="inline-flex min-h-12 items-center justify-center rounded-full px-5 font-semibold underline underline-offset-4">{text('Order status', 'حالة الطلب')}</Link>
      </div>
      {error.digest && <p className="text-xs text-muted-foreground">{text('Error reference', 'مرجع الخطأ')}: <span dir="ltr">{error.digest}</span></p>}
    </div>
    <StoreSignBag />
  </main>
}
