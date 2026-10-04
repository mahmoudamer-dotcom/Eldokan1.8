'use client'

import { useLocale } from '@/components/i18n/LocaleProvider'

function Block({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`animate-pulse rounded-xl bg-gray-200 ${className}`} />
}

function ProductCardSkeleton() {
  return (
    <div className="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-2.5 sm:p-3">
      <Block className="aspect-square rounded-xl bg-gray-100" />
      <Block className="mt-3 h-3 w-1/3" />
      <Block className="mt-2 h-4 w-11/12" />
      <Block className="mt-1.5 h-4 w-2/3" />
      <Block className="mt-4 h-5 w-1/2" />
    </div>
  )
}

export default function HomeLoading() {
  const { t } = useLocale()
  return (
    <main className="container mx-auto w-full flex-1 space-y-9 px-4 pb-12 pt-7" aria-label={t('Loading the home page')} role="status">
      <Block className="h-64 rounded-3xl bg-amber-100 sm:h-80" />

      <section aria-hidden="true" className="space-y-4">
        <div className="flex items-end justify-between">
          <div className="w-full max-w-xs">
            <Block className="h-3 w-28 bg-amber-100" />
            <Block className="mt-2 h-7 w-48" />
          </div>
          <Block className="h-4 w-16" />
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-5">
          {Array.from({ length: 5 }, (_, index) => <ProductCardSkeleton key={index} />)}
        </div>
      </section>

      <section aria-hidden="true" className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Block className="h-[250px] rounded-2xl bg-amber-100 sm:col-span-2 sm:h-[340px]" />
        <Block className="h-[250px] rounded-2xl sm:col-span-2 sm:h-[340px]" />
      </section>

      <section aria-hidden="true" className="space-y-4">
        <Block className="h-7 w-56" />
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-5">
          {Array.from({ length: 5 }, (_, index) => <ProductCardSkeleton key={index} />)}
        </div>
      </section>
    </main>
  )
}

