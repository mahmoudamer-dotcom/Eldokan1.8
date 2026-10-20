import Image from 'next/image'
import Link from 'next/link'
import { ArrowLeft, Lock } from 'lucide-react'
import { getLocale } from '@/lib/server-locale'

/** The only header on checkout: logo, "secure checkout" and a way back to the cart. */
export default async function CheckoutHeader() {
  const ar = (await getLocale()) === 'ar'
  return <header className="border-b border-border bg-card" dir={ar ? 'rtl' : 'ltr'}>
    <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4">
      <Link href="/cart" className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground">
        <ArrowLeft aria-hidden="true" className="size-4 rtl:rotate-180" />{ar ? 'رجوع للسلة' : 'Back to cart'}
      </Link>
      <Image src="/image/Eldokan-logo.webp" alt={ar ? 'الدكان' : 'ElDokan'} width={110} height={56} priority className="brand-logo-canvas h-10 w-auto rounded-md object-contain" />
      <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400"><Lock aria-hidden="true" className="size-4" />{ar ? 'دفع آمن' : 'Secure checkout'}</span>
    </div>
  </header>
}
