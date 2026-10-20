'use client'

import Link from 'next/link'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { CONSUMER_RIGHTS_URL } from '@/lib/legal-policies'

export default function Return() {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  return <section dir={ar ? 'rtl' : 'ltr'} className="space-y-5 text-sm leading-8 text-foreground">
    <h2 className="text-2xl font-bold text-foreground">{ar ? 'الإرجاع والاستبدال' : 'Returns & exchanges'}</h2>
    <ul className="list-disc space-y-1 ps-5">
      <li>{ar ? 'غيّرت رأيك؟ رجّع أو استبدل خلال 14 يوم من الاستلام، والمنتج مش مستخدم وفي علبته.' : 'Changed your mind? Return or exchange within 14 days of delivery, unused and in its original packaging.'}</li>
      <li>{ar ? 'فيه عيب أو متضرر أو غلط؟ رجّعه خلال 30 يوم، والشحن علينا.' : 'Defective, damaged or wrong? Return within 30 days, with free return shipping.'}</li>
      <li>{ar ? 'فلوسك بترجع خلال 7 أيام عمل بعد فحص المرتجع.' : 'Refunds are issued within 7 business days after the return is inspected.'}</li>
    </ul>
    <p>{ar ? 'بعض المنتجات زي مستحضرات التجميل المفتوحة والملابس الداخلية بترجع بس لو فيها عيب. كلمنا برقم الطلب وسبب الإرجاع، وما تبعتش المنتج قبل ما نرتب معاك الاستلام.' : 'Some items, such as opened cosmetics and underwear, can be returned only if defective. Contact us with your order number and reason, and wait for us to arrange pickup before sending anything.'}</p>
    <div className="flex flex-wrap gap-5"><Link href="/policies/returns" className="font-semibold underline">{ar ? 'سياسة الإرجاع كاملة' : 'Full returns policy'}</Link><Link href="/help" className="underline">{ar ? 'طلب إرجاع أو مساعدة' : 'Return request & help'}</Link><a href={CONSUMER_RIGHTS_URL} target="_blank" rel="noopener noreferrer" className="underline">{ar ? 'المصدر: جهاز حماية المستهلك' : 'Source: Consumer Protection Agency'}</a></div>
  </section>
}