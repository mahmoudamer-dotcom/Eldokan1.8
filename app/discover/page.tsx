import type { Metadata } from 'next'
import Link from 'next/link'
import SelectionJourney from '@/components/discovery/SelectionJourney'
import { getLocale } from '@/lib/server-locale'

export const metadata: Metadata = { title: 'Shopping chat | Eldokan', description: 'Choose products through a guided conversation with Eldokan.' }
export default async function DiscoverPage({ searchParams }: { searchParams: Promise<{ category?: string | string[]; q?: string | string[] }> }) {
  const params = await searchParams
  const categorySlug = (Array.isArray(params.category) ? params.category[0] : params.category)?.trim().slice(0, 200)
  const keywords = (Array.isArray(params.q) ? params.q[0] : params.q)?.trim().slice(0, 100)
  const ar = await getLocale() === 'ar'
  return <main dir={ar ? 'rtl' : 'ltr'} className="mx-auto w-full max-w-4xl px-4 py-12"><Link href="/" className="text-sm underline">{ar ? 'ارجع للتسوق' : 'Back to shopping'}</Link><h1 className="mt-6 text-3xl font-bold">{ar ? 'اختار منتجاتك في محادثة مع الدكان' : 'Choose products in a conversation with Eldokan'}</h1><p className="my-5 text-sm leading-7 text-muted-foreground">{ar ? 'حدد احتياجك وميزانيتك في الشات، وشوف منتجات من المتجر. تقدر تقفل المحادثة وتكمّل التصفح وترجع لها في أي وقت.' : 'Share your needs and budget in chat and see actual catalog products. Close the conversation to browse and return whenever you need.'}</p><SelectionJourney categorySlug={categorySlug} keywords={keywords} autoOpen /></main>
}