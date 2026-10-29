'use client'

import { Share2 } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { toast } from '@/lib/toast'

export default function ProductShare({ productId, name }: { productId: string; name: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  async function share() {
    const url = new URL(`/product/${encodeURIComponent(productId)}`, window.location.origin).toString()
    try {
      if (navigator.share) await navigator.share({ title: name, url })
      else { await navigator.clipboard.writeText(url); toast(ar ? 'تم نسخ رابط المنتج' : 'Product link copied') }
    } catch (cause) {
      if (cause instanceof DOMException && cause.name === 'AbortError') return
      toast(ar ? 'تعذر المشاركة. انسخ الرابط من شريط العنوان.' : 'Could not share. Copy the link from the address bar.', 'error')
    }
  }
  return <div className="mt-4 flex flex-wrap items-center gap-3 text-sm"><button type="button" onClick={() => void share()} className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-muted-foreground hover:bg-background"><Share2 className="size-4" aria-hidden="true" />{ar ? 'مشاركة المنتج' : 'Share product'}</button></div>
}
