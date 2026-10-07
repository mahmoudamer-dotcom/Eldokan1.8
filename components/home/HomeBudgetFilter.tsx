import Link from 'next/link'
import { Wallet } from 'lucide-react'
import T from '@/components/i18n/T'

const ranges = [
  { label: 'Under 500 EGP', min: 0, max: 500 },
  { label: '500–1,000 EGP', min: 500, max: 1000 },
  { label: '1,000–3,000 EGP', min: 1000, max: 3000 },
  { label: '3,000+ EGP', min: 3000 },
]

export default function HomeBudgetFilter() {
  return (
    <section className="rounded-2xl border border-[#f0d88e] bg-shop-soft p-4 sm:p-5" aria-labelledby="budget-filter-title">
      <div className="mb-4 flex items-center gap-2">
        <Wallet className="size-4 text-shop-accent" aria-hidden="true" />
        <h2 id="budget-filter-title" className="font-bold text-foreground"><T text="Shop by budget" /></h2>
      </div>
      <div className="grid grid-cols-2 gap-2">
        {ranges.map((range) => {
          const params = new URLSearchParams({ min: String(range.min) })
          if (range.max !== undefined) params.set('max', String(range.max))

          return (
            <Link key={range.label} href={`/budget?${params.toString()}`} className="rounded-xl border border-[#eadba9] bg-card px-3 py-3 text-center text-xs font-semibold text-foreground transition hover:border-[#d9ae30] hover:bg-shop-soft">
              <T text={range.label} />
            </Link>
          )
        })}
      </div>
    </section>
  )
}
