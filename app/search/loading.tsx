'use client'

import { useLocale } from '@/components/i18n/LocaleProvider'

function Block({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`animate-pulse rounded-lg bg-muted ${className}`} />
}

export default function SearchLoading() {
  const { t } = useLocale()
  return (
    <main className="container mx-auto min-h-screen px-4 pb-12" aria-label={t('Loading search results')} role="status">
      <section className="py-8">
        <Block className="h-3 w-28 bg-shop-soft" />
        <Block className="mt-3 h-8 w-64 max-w-full" />
        <Block className="mt-2 h-4 w-36" />

        <div aria-hidden="true" className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
          {Array.from({ length: 10 }, (_, index) => (
            <div key={index} className="overflow-hidden rounded-2xl border border-border bg-card p-2.5 sm:p-3">
              <Block className="aspect-square rounded-xl bg-muted" />
              <Block className="mt-3 h-3 w-2/5" />
              <Block className="mt-2 h-4 w-full" />
              <Block className="mt-1.5 h-4 w-3/4" />
              <Block className="mt-4 h-5 w-1/2" />
            </div>
          ))}
        </div>
      </section>
    </main>
  )
}

