import Link from 'next/link'
import { getLocale } from '@/lib/server-locale'

export default async function NotFound() {
  const ar = await getLocale() === 'ar'
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto my-16 max-w-xl px-5 text-center"><p className="text-6xl font-bold text-amber-500">404</p><h1 className="mt-4 text-2xl font-bold">{ar ? 'الصفحة غير موجودة' : 'Page not found'}</h1><p className="mt-3 text-muted-foreground">{ar ? 'الرابط قديم أو المنتج لم يعد متاحًا. تقدر تكمل التسوق من الصفحة الرئيسية.' : 'This link may be outdated or the product may no longer be available. Continue browsing from the home page.'}</p><Link href="/" className="mt-6 inline-flex rounded-xl bg-[#f5b400] text-primary-foreground px-5 py-3 font-semibold">{ar ? 'تابع التسوق' : 'Continue shopping'}</Link></main>
}
