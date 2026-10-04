'use client'

import { useParams } from 'next/navigation'
import { useLocale } from '@/components/i18n/LocaleProvider'

function Block({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`animate-pulse rounded-lg bg-gray-200 ${className}`} />
}

function MiniProductSkeleton() {
  return (
    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white p-2.5 sm:p-3">
      <Block className="aspect-square rounded-xl bg-gray-100" />
      <Block className="mt-3 h-3 w-2/5" />
      <Block className="mt-2 h-4 w-full" />
      <Block className="mt-1.5 h-4 w-3/4" />
      <Block className="mt-4 h-5 w-1/2" />
    </div>
  )
}

export default function ProductPageLoading() {
  const { id } = useParams<{ id: string }>()
  const { t } = useLocale()

  return (
    <main className="container mx-auto min-h-screen px-4 pb-12" aria-label={t('Loading product details')} role="status">
      <nav aria-label={t('Breadcrumb')} className="flex items-center gap-2 py-5 text-sm text-gray-400">
        <Block className="h-4 w-10" />
        <span aria-hidden="true">/</span>
        <Block className="h-4 w-24" />
        <span aria-hidden="true">/</span>
        <span className="font-medium text-gray-500">{t('Loading product')} {id ? `#${id}` : ''}</span>
      </nav>

      <section className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(20rem,0.95fr)] lg:gap-8">
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-5">
          <Block className="aspect-square rounded-xl bg-gray-100" />
          <div className="mt-4 flex gap-3 overflow-hidden">
            {Array.from({ length: 4 }, (_, index) => <Block key={index} className="size-16 shrink-0 rounded-lg bg-gray-100 sm:size-20" />)}
          </div>
        </div>

        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
          <Block className="h-4 w-28 bg-amber-100" />
          <Block className="mt-3 h-8 w-11/12" />
          <Block className="mt-2 h-8 w-2/3" />
          <Block className="mt-4 h-3 w-24" />
          <div className="mt-6 border-y border-gray-100 py-5">
            <Block className="h-9 w-40 bg-amber-100" />
            <Block className="mt-3 h-4 w-52" />
          </div>
          <Block className="mt-5 h-4 w-24 bg-emerald-100" />
          <Block className="mt-5 h-20 w-full rounded-xl" />
          <div className="mt-6 grid grid-cols-2 gap-3">
            <Block className="h-12 rounded-full bg-amber-100" />
            <Block className="h-12 rounded-full" />
          </div>
        </div>
      </section>

      <section className="mt-8 rounded-2xl border border-gray-200 bg-white p-5 sm:p-7" aria-hidden="true">
        <Block className="h-6 w-48" />
        <Block className="mt-4 h-4 w-full" />
        <Block className="mt-2 h-4 w-11/12" />
        <Block className="mt-2 h-4 w-4/5" />
      </section>

      <section className="mt-10" aria-hidden="true">
        <Block className="h-3 w-28 bg-amber-100" />
        <Block className="mt-2 h-7 w-52" />
        <div className="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white p-4">
          <Block className="h-10 w-full" />
          <Block className="mt-3 h-10 w-full" />
        </div>
      </section>

      <section className="mt-12" aria-hidden="true">
        <Block className="h-3 w-28 bg-amber-100" />
        <Block className="mt-2 h-7 w-56" />
        <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
          {Array.from({ length: 5 }, (_, index) => <MiniProductSkeleton key={index} />)}
        </div>
      </section>
    </main>
  )
}
