import Link from 'next/link'
import { notFound } from 'next/navigation'
import type { Metadata } from 'next'
import { getLocale } from '@/lib/server-locale'
import { BUSINESS, CONSUMER_RIGHTS_URL, getPolicy, MERCHANT_PROPOSAL, POLICIES } from '@/lib/legal-policies'

type Props = { params: Promise<{ slug: string }> }
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const policy = getPolicy((await params).slug)
  const locale = await getLocale()
  return { title: policy ? `${policy.title[locale]} | ${BUSINESS.name[locale]}` : 'Eldokan', robots: { index: false, follow: true } }
}
export default async function PolicyPage({ params }: Props) {
  const policy = getPolicy((await params).slug)
  if (!policy) notFound()
  const locale = await getLocale()
  const ar = locale === 'ar'
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto w-full max-w-6xl px-4 py-10">
    <Link href="/policies" className="text-sm underline">{ar ? 'كل الشروط والسياسات' : 'All terms & policies'}</Link>
    <h1 className="mt-5 text-3xl font-bold">{policy.title[locale]}</h1><p className="mt-3 text-muted-foreground">{policy.summary[locale]}</p>
    <div className="mt-6 rounded-xl bg-shop-soft p-4 text-sm leading-7 text-shop-accent"><strong>{ar ? 'مسودة غير معتمدة للنشر النهائي' : 'Draft pending final approval'}</strong><p>{ar ? 'هذه البنود مقترحة للمراجعة قبل الإطلاق ولا تحد من الحقوق القانونية. العمولة والتسوية وأي إجراءات تجارية لا تعمل بمجرد كتابة الشروط.' : 'These proposed terms require review before launch and do not limit legal rights. Publishing text does not configure commissions, settlement or business processes.'}</p><p className="text-xs">{ar ? 'إصدار المسودة: ' : 'Draft version: '}{MERCHANT_PROPOSAL.version}</p></div>
    <div className="mt-8 grid items-start gap-8 lg:grid-cols-[240px_minmax(0,1fr)]">
      <aside className="space-y-6 rounded-xl border p-5"><nav aria-label={ar ? 'محتويات الصفحة' : 'On this page'}><h2 className="font-bold">{ar ? 'محتويات الصفحة' : 'On this page'}</h2><ul className="mt-3 space-y-3 text-sm">{policy.sections.map(section => <li key={section.id}><a href={`#${section.id}`} className="hover:underline">{section.title[locale]}</a></li>)}</ul></nav><nav aria-label={ar ? 'سياسات أخرى' : 'Other policies'} className="border-t pt-4"><ul className="space-y-3 text-sm">{POLICIES.map(item => <li key={item.slug}><Link href={`/policies/${item.slug}`} aria-current={item.slug === policy.slug ? 'page' : undefined} className={item.slug === policy.slug ? 'font-bold text-shop-accent' : 'hover:underline'}>{item.title[locale]}</Link></li>)}</ul></nav></aside>
      <article className="space-y-7">{policy.sections.map(section => <section id={section.id} key={section.id} className="scroll-mt-32 rounded-xl border border-border bg-card p-6"><h2 className="text-xl font-bold">{section.title[locale]}</h2>{section.paragraphs.map((paragraph, index) => <p key={index} className="mt-3 text-sm leading-8 text-foreground">{paragraph[locale]}</p>)}</section>)}
        {policy.slug === 'returns' && <p className="text-sm"><a href={CONSUMER_RIGHTS_URL} target="_blank" rel="noopener noreferrer" className="underline">{ar ? 'المصدر: جهاز حماية المستهلك المصري — حقوق الاستبدال والاسترجاع والاستثناءات' : 'Source: Egyptian Consumer Protection Agency — return rights and exceptions'}</a></p>}
        <section className="rounded-xl bg-background p-6 text-sm leading-7"><h2 className="font-bold">{BUSINESS.name[locale]}</h2><address className="not-italic">{BUSINESS.address[locale]}<br /><a href={`mailto:${BUSINESS.email}`} className="underline">{BUSINESS.email}</a></address><Link href="/help" className="mt-2 inline-block underline">{ar ? 'المساعدة وطلبات الإرجاع والشكاوى' : 'Help, return requests & complaints'}</Link></section>
      </article>
    </div>
  </main>
}
