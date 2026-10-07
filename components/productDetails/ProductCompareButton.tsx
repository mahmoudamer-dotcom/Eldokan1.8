'use client'

import { GitCompareArrows } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useProductComparison } from '@/lib/use-product-comparison'
import { useComparisonDrawer } from './ComparisonProvider'

export default function ProductCompareButton({ productId }: { productId: string }) {
  const { locale } = useLocale()
  const ids = useProductComparison()
  const show = useComparisonDrawer()
  const selected = ids.includes(productId)
  return <div className="mt-3 text-xs"><button type="button" aria-haspopup="dialog" onClick={() => show(productId)} className={`inline-flex items-center gap-1 rounded-md border px-2 py-1.5 ${selected ? 'border-amber-400 bg-shop-soft text-shop-accent' : 'border-border text-muted-foreground'}`}><GitCompareArrows className="size-3.5" aria-hidden="true" />{locale === 'ar' ? 'المقارنة' : 'Compare'}{selected ? ` (${ids.length})` : ''}</button></div>
}