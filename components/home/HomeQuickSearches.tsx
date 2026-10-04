import Link from 'next/link'
import { ArrowUpRight, Search } from 'lucide-react'
import T from '@/components/i18n/T'

export default function HomeQuickSearches({ terms }: { terms: string[] }) {
  const uniqueTerms = [...new Set(terms.map((term) => term.trim()).filter(Boolean))].slice(0, 8)
  if (uniqueTerms.length === 0) return null

  return (
    <section className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="quick-searches-title">
      <div className="mb-4 flex items-center gap-2">
        <Search className="size-4 text-[#a66d00]" aria-hidden="true" />
        <h2 id="quick-searches-title" className="font-bold text-gray-900"><T text="Explore Eldokan" /></h2>
      </div>
      <div className="flex flex-wrap gap-2">
        {uniqueTerms.map((term) => (
          <Link key={term} href={`/search?q=${encodeURIComponent(term)}`} className="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 transition hover:border-[#e1b84f] hover:bg-[#fff9e8]">
            {term}<ArrowUpRight className="size-3 text-gray-400" aria-hidden="true" />
          </Link>
        ))}
      </div>
    </section>
  )
}
