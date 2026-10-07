'use client'

import Link from 'next/link'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function ErrorPage({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto my-12 w-full max-w-xl rounded-2xl border bg-card p-8 text-center"><h1 className="text-2xl font-bold">{ar ? 'تعذر تحميل الصفحة حاليًا' : 'This page could not load'}</h1><p className="mt-3 text-sm leading-6 text-muted-foreground">{ar ? 'حاول مرة أخرى. لو كنت بتكمل طلب، افتح حالة الطلب قبل بدء عملية شراء جديدة.' : 'Please try again. If you were checking out, open your order status before starting another purchase.'}</p>{error.digest && <p className="mt-3 text-xs text-muted-foreground">{ar ? 'مرجع الخطأ' : 'Error reference'}: {error.digest}</p>}<div className="mt-6 flex flex-wrap justify-center gap-3"><button onClick={retry} className="rounded-xl bg-[#f5b400] text-primary-foreground px-5 py-3 font-semibold">{ar ? 'حاول مرة أخرى' : 'Try again'}</button><Link href="/" className="rounded-xl border px-5 py-3">{ar ? 'الرئيسية' : 'Home'}</Link><Link href="/checkout/result" className="rounded-xl border px-5 py-3">{ar ? 'حالة الطلب' : 'Order status'}</Link></div></main>
}
