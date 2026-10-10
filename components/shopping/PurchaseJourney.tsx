'use client'

import Link from 'next/link'
import { ShoppingBag, MapPin, PackageCheck } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function PurchaseJourney({ stage }: { stage: 'cart' | 'checkout' | 'order' }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const current = ['cart', 'checkout', 'order'].indexOf(stage)
  const steps = [
    { title: ar ? 'سلتك' : 'Your cart', icon: ShoppingBag, href: '/cart' },
    { title: ar ? 'التوصيل والدفع' : 'Delivery & payment', icon: MapPin, href: '/checkout/cart' },
    { title: ar ? 'متابعة الطلب' : 'Track your order', icon: PackageCheck, href: '/account/orders' },
  ]
  return <nav aria-label={ar ? 'مراحل الشراء' : 'Purchase progress'} className="mb-6 rounded-2xl border border-border bg-card px-3 py-4 sm:px-6 print:hidden">
    <ol className="flex items-start">
      {steps.map(({ title, icon: Icon, href }, index) => <li key={href} className="relative flex flex-1 flex-col items-center gap-2 text-center text-xs sm:text-sm" aria-current={index === current ? 'step' : undefined}>
        {index < steps.length - 1 && <span aria-hidden="true" className={`absolute start-1/2 top-4 h-px w-full ${index < current ? 'bg-primary' : 'bg-border'}`} />}
        <span className={`relative grid size-9 place-items-center rounded-full ${index === current ? 'bg-primary text-primary-foreground ring-4 ring-shop-soft' : index < current ? 'bg-shop-soft text-shop-accent' : 'bg-muted text-muted-foreground'}`}><Icon aria-hidden="true" className="size-4" /></span>
        {stage !== 'order' && index < current ? <Link href={href} className="font-semibold underline underline-offset-4">{title}</Link> : <span className={index === current ? 'font-bold text-foreground' : 'text-muted-foreground'}>{title}</span>}
      </li>)}
    </ol>
  </nav>
}
