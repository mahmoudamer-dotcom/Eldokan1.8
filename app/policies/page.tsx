import Link from 'next/link'
import type { Metadata } from 'next'
import { getLocale } from '@/lib/server-locale'
import { BUSINESS, POLICIES } from '@/lib/legal-policies'

export const metadata: Metadata = { title: 'Policies | Eldokan', robots: { index: false, follow: true } }

export default async function PoliciesPage() {
  const locale = await getLocale()
  const ar = locale === 'ar'
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto w-full max-w-6xl px-4 py-12">
    <h1 className="text-3xl font-bold">{ar ? 'شروط وسياسات الدكان' : 'Eldokan terms & policies'}</h1>
    <p className="mt-4 rounded-xl bg-shop-soft p-4 text-sm leading-7 text-shop-accent">{ar ? 'مسودات للمراجعة قبل الإطلاق. البنود التجارية المقترحة تحتاج اعتمادًا وتطبيقًا في النظام قبل سريانها، ولا تنتقص من حقوق المستهلك القانونية.' : 'Review drafts before launch. Proposed commercial terms require approval and implementation before taking effect and do not limit statutory consumer rights.'}</p>
    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{POLICIES.map(policy => <Link key={policy.slug} href={`/policies/${policy.slug}`} className="rounded-2xl border border-border bg-card p-6 transition hover:border-amber-500 focus-visible:outline-2 focus-visible:outline-amber-600">
      <span className="text-xs font-semibold text-shop-accent">{policy.audience === 'seller' ? (ar ? 'للبائعين والتجار' : 'For sellers') : policy.audience === 'buyer' ? (ar ? 'للعملاء' : 'For customers') : (ar ? 'للجميع' : 'For everyone')}</span>
      <h2 className="mt-2 text-lg font-bold">{policy.title[locale]}</h2><p className="mt-2 text-sm leading-6 text-muted-foreground">{policy.summary[locale]}</p>
    </Link>)}</div>
    <section className="mt-8 rounded-2xl border p-6"><h2 className="font-bold">{BUSINESS.name[locale]}</h2><p className="mt-2 text-sm">{BUSINESS.address[locale]}</p><a className="mt-3 inline-block text-sm underline" href={`mailto:${BUSINESS.email}`}>{BUSINESS.email}</a><Link href="/help" className="ms-5 text-sm underline">{ar ? 'التواصل والشكاوى' : 'Support & complaints'}</Link></section>
  </main>
}
