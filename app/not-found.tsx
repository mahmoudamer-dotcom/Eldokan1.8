import Link from 'next/link'
import { getLocale } from '@/lib/server-locale'

/**
 * The store sign: a yellow shopping bag holding a question mark beside the message, a product search
 * and two ways out. The site header above already carries the Eldokan logo.
 */
export default async function NotFound() {
  const ar = await getLocale() === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto flex w-full max-w-6xl flex-1 flex-col-reverse items-center justify-center gap-8 px-4 py-10 sm:px-6 md:flex-row md:justify-between md:gap-12 md:py-16 lg:px-8">
    <div className="flex w-full max-w-xl flex-col items-center gap-4 text-center md:items-start md:text-start">
      <span className="rounded-full bg-foreground px-3.5 py-0.5 text-sm font-extrabold text-background">{text('Error 404', 'خطأ 404')}</span>
      <h1 className="text-3xl font-black leading-tight text-balance sm:text-4xl lg:text-5xl">{text('This page isn’t in the store', 'الصفحة دي مش موجودة في الدكان')}</h1>
      <p className="text-base leading-8 text-muted-foreground sm:text-lg">{text('The link may have changed or the product sold out. Try again, or search for what you need.', 'يمكن الرابط اتغيّر أو المنتج خلص. حاول مرة تانية، أو دوّر على اللي محتاجه من هنا.')}</p>
      <form action="/search" role="search" className="mt-2 flex w-full items-center rounded-full border border-border bg-card p-1.5">
        <label htmlFor="not-found-search" className="sr-only">{text('Search for a product', 'ابحث عن منتج')}</label>
        <input id="not-found-search" name="q" type="search" placeholder={text('Search for a product…', 'ابحث عن منتج…')} className="min-h-11 min-w-0 flex-1 bg-transparent px-4 text-base outline-none placeholder:text-muted-foreground" />
        <button type="submit" className="min-h-11 rounded-full bg-[#222222] px-6 text-sm font-extrabold text-white transition hover:bg-black">{text('Search', 'بحث')}</button>
      </form>
      <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
        {/* A plain link to the current address reloads it: the "try again". */}
        <a href="" className="inline-flex min-h-12 items-center justify-center rounded-full bg-[#f5b400] px-7 font-extrabold text-[#1d1d1b] transition hover:brightness-95">{text('Try again', 'حاول مرة تانية')}</a>
        <Link href="/" className="inline-flex min-h-12 items-center justify-center rounded-full border border-foreground px-7 font-bold transition hover:bg-foreground hover:text-background">{text('Back to home', 'ارجع للرئيسية')}</Link>
      </div>
    </div>
    <div aria-hidden="true" className="relative grid w-44 shrink-0 place-items-center sm:w-56 md:w-80 lg:w-96">
      <svg viewBox="0 0 380 420" className="w-full">
        <path d="M120 120 C120 60 150 30 190 30 C230 30 260 60 260 120" fill="none" stroke="currentColor" strokeWidth="14" strokeLinecap="round" />
        <rect x="40" y="110" width="300" height="290" rx="36" fill="#f5b400" />
        <circle cx="120" cy="150" r="9" fill="#1d1d1b" />
        <circle cx="260" cy="150" r="9" fill="#1d1d1b" />
      </svg>
      <span className="absolute inset-x-0 top-[38%] text-center text-[6.5rem] font-black leading-none text-[#1d1d1b] sm:text-[8rem] md:text-[11rem] lg:text-[13rem]">{ar ? '؟' : '?'}</span>
    </div>
  </main>
}
