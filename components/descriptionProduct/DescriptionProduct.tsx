'use client'

import { useState } from 'react'
import Return from '@/components/ReturnsRefunds/Return'
import type { ProductDetailData } from '@/types/product'
import { useLocale } from '@/components/i18n/LocaleProvider'

const tabs = ['Description', 'Product specifications', 'Returns & refunds'] as const
type ProductTab = (typeof tabs)[number]

export default function DescriptionProduct({ data }: { data: ProductDetailData }) {
  const { t } = useLocale()
  const [activeTab, setActiveTab] = useState<ProductTab>('Description')
  const content = activeTab === 'Description'
    ? data.description_html ?? ''
    : data.short_description_html ?? ''

  return (
    <section className="mt-8 overflow-hidden rounded-2xl border border-gray-200 bg-white">
      <div role="tablist" aria-label={t('Product information')} className="flex gap-2 overflow-x-auto border-b border-gray-200 px-4 pt-3 sm:px-6">
        {tabs.map((tab) => (
          <button
            key={tab}
            type="button"
            role="tab"
            aria-selected={activeTab === tab}
            onClick={() => setActiveTab(tab)}
            className={`shrink-0 border-b-2 px-3 py-3 text-sm font-semibold transition ${activeTab === tab ? 'border-[#d99500] text-gray-950' : 'border-transparent text-gray-500 hover:text-gray-900'}`}
          >
            {t(tab)}
          </button>
        ))}
      </div>
      <div role="tabpanel" className="p-5 sm:p-7">
        {activeTab === 'Returns & refunds' ? (
          <Return />
        ) : (
          <div
            className="prose max-w-none text-gray-700 [&_h1]:mb-4 [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-gray-900 [&_h2]:mb-3 [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-gray-800 [&_img]:mx-auto [&_img]:my-6 [&_img]:max-w-full [&_img]:rounded-xl [&_li]:my-1 [&_p]:my-3 [&_table]:my-6 [&_table]:w-full [&_td]:border [&_td]:border-gray-200 [&_td]:p-3 [&_th]:border [&_th]:border-gray-200 [&_th]:bg-gray-50 [&_th]:p-3"
            dangerouslySetInnerHTML={{ __html: content }}
          />
        )}
      </div>
    </section>
  )
}
