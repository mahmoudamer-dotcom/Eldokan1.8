'use client'

import { useId, useState } from 'react'
import Return from '@/components/ReturnsRefunds/Return'
import type { ProductDetailData } from '@/types/product'
import { useLocale } from '@/components/i18n/LocaleProvider'

const tabs = ['Description', 'Product specifications', 'Returns & refunds'] as const
type ProductTab = (typeof tabs)[number]

export default function DescriptionProduct({ data }: { data: ProductDetailData }) {
  const { t, locale } = useLocale()
  const [activeTab, setActiveTab] = useState<ProductTab>('Description')
  const tabId = useId()
  const content = activeTab === 'Description'
    ? data.description_html ?? ''
    : data.short_description_html ?? ''

  return (
    <section className="mt-8 overflow-hidden rounded-2xl border border-border bg-card">
      <div role="tablist" aria-label={t('Product information')} className="flex gap-2 overflow-x-auto border-b border-border px-4 pt-3 sm:px-6">
        {tabs.map((tab) => (
          <button
            key={tab}
            type="button"
            role="tab"
            id={`${tabId}-${tab}`}
            aria-controls={`${tabId}-panel`}
            tabIndex={activeTab === tab ? 0 : -1}
            aria-selected={activeTab === tab}
            onClick={() => setActiveTab(tab)}
            onKeyDown={(event) => {
              if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
              event.preventDefault()
              const current = tabs.indexOf(tab)
              const direction = event.key === 'ArrowRight' ? (locale === 'ar' ? -1 : 1) : (locale === 'ar' ? 1 : -1)
              const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + direction + tabs.length) % tabs.length
              setActiveTab(tabs[next])
              const buttons = event.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>('[role="tab"]')
              buttons?.[next]?.focus()
            }}
            className={`shrink-0 border-b-2 px-3 py-3 text-sm font-semibold transition ${activeTab === tab ? 'border-[#d99500] text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
          >
            {t(tab)}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`${tabId}-panel`} aria-labelledby={`${tabId}-${activeTab}`} tabIndex={0} className="min-w-0 p-3 sm:p-7">
        {activeTab === 'Returns & refunds' ? (
          <Return />
        ) : activeTab === 'Product specifications' ? (
          data.attributes.length ? <dl className="divide-y divide-border">{data.attributes.map((attribute, index) => <div key={`${attribute.name}-${index}`} className="grid gap-2 py-3 text-sm sm:grid-cols-[12rem_minmax(0,1fr)]"><dt className="font-semibold text-foreground">{attribute.name}</dt><dd className="break-words text-muted-foreground">{attribute.options.map((option) => option.name).join(locale === 'ar' ? '، ' : ', ') || '—'}</dd></div>)}{data.sku && <div className="grid gap-2 py-3 text-sm sm:grid-cols-[12rem_minmax(0,1fr)]"><dt className="font-semibold">SKU</dt><dd>{data.sku}</dd></div>}</dl>
            : <p className="py-6 text-center text-sm text-muted-foreground">{locale === 'ar' ? 'البائع لم يضف مواصفات تفصيلية لهذا المنتج بعد.' : 'The seller has not added detailed specifications yet.'}</p>
        ) : !content.trim() ? <p className="py-6 text-center text-sm text-muted-foreground">{locale === 'ar' ? 'لا يوجد وصف تفصيلي لهذا المنتج بعد.' : 'No detailed description is available yet.'}</p> : (
          <div
            className="product-description prose max-w-none text-foreground [&_h1]:mb-4 [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-foreground [&_h2]:mb-3 [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-foreground [&_img]:mx-auto [&_img]:my-6 [&_img]:max-w-full [&_img]:rounded-xl [&_li]:my-1 [&_p]:my-3 [&_table]:my-6 [&_table]:w-full [&_td]:border [&_td]:border-border [&_td]:p-3 [&_th]:border [&_th]:border-border [&_th]:bg-background [&_th]:p-3"
            dangerouslySetInnerHTML={{ __html: content }}
          />
        )}
      </div>
    </section>
  )
}
