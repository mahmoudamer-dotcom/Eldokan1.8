'use client'

import Link from 'next/link'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { CONSUMER_RIGHTS_URL } from '@/lib/legal-policies'

export default function Return() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  return <section dir={ar ? 'rtl' : 'ltr'} className="space-y-5 text-sm leading-8 text-foreground">
    <h2 className="text-2xl font-bold text-foreground">{ar ? 'الإرجاع والاستبدال' : 'Returns & exchanges'}</h2>
    <p>{ar ? 'وفق إرشادات جهاز حماية المستهلك المصري: حق عام في الاستبدال أو الاسترجاع خلال 14 يومًا مع الاستثناءات القانونية، و30 يومًا للسلع المعيبة. راجع الشروط المناسبة لنوع المنتج.' : 'Egyptian Consumer Protection Agency guidance provides a general 14-day return or exchange right with statutory exceptions, and 30 days for defective goods. Check the conditions applicable to the product.'}</p>
    <p>{ar ? 'تواصل مع الدعم برقم الطلب وسبب الإرجاع وصور العيب إن وجد. لا ترسل المنتج قبل تنسيق طريقة ومكان التسليم مع الدعم.' : 'Contact support with your order reference, return reason and photos of any defect. Arrange the return method and destination with support before sending the product.'}</p>
    <p className="rounded-xl bg-shop-soft p-4">{ar ? 'سياسة المتجر التفصيلية مسودة للمراجعة قبل الإطلاق. حقوقك القانونية لا تتأثر بهذه المسودة.' : 'The detailed store policy is a review draft before launch. Your statutory rights are unaffected.'}</p>
    <div className="flex flex-wrap gap-5"><Link href="/policies/returns" className="font-semibold underline">{ar ? 'سياسة الإرجاع كاملة' : 'Full returns policy'}</Link><Link href="/help" className="underline">{ar ? 'طلب إرجاع أو مساعدة' : 'Return request & help'}</Link><a href={CONSUMER_RIGHTS_URL} target="_blank" rel="noopener noreferrer" className="underline">{ar ? 'المصدر: جهاز حماية المستهلك' : 'Source: Consumer Protection Agency'}</a></div>
  </section>
}