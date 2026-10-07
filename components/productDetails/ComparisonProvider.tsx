'use client'

import Image from 'next/image'
import Link from 'next/link'
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import { GitCompareArrows, X, ArrowLeft, Maximize2 } from 'lucide-react'
import type { ProductDetail } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { useProductComparison } from '@/lib/use-product-comparison'
import { COMPARE_KEY, normalizeComparisonIds } from '@/lib/product-comparison'
import { createEldokanApi } from '@/lib/eldokan-api'
import ComparisonTable from './ComparisonTable'

const Context = createContext<((id?: string) => void) | null>(null)

export function ComparisonProvider({ children }: { children: React.ReactNode }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const ids = useProductComparison()
  const dialog = useRef<HTMLDialogElement>(null)
  const [open, setOpen] = useState(false)
  const [expanded, setExpanded] = useState(false)
  const [error, setError] = useState('')
  const [products, setProducts] = useState<Record<string, ProductDetail | null>>({})
  const [loadedKey, setLoadedKey] = useState('')
  const [reload, setReload] = useState(0)
  const selectionKey = `${locale}:${ids.join(',')}`

  function save(next: string[]) {
    try {
      localStorage.setItem(COMPARE_KEY, JSON.stringify(normalizeComparisonIds(next)))
      window.dispatchEvent(new Event('eldokan:compare-changed')); setError('')
    } catch { setError(text('Could not save your selection on this device.', 'تعذر حفظ الاختيار على هذا الجهاز.')) }
  }
  const show = (id?: string) => {
    setExpanded(false); setOpen(true); setError('')
    if (!id) return
    if (ids.includes(id)) return
    if (ids.length >= 4) { setError(text('Compare up to 4 products. Remove one first.', 'تقدر تقارن ٤ منتجات كحد أقصى. شيل منتج الأول.')); return }
    save([...ids, id])
  }
  const close = useCallback(() => { dialog.current?.close(); setOpen(false) }, [])

  useEffect(() => {
    if (!open) return
    const element = dialog.current
    element?.showModal()
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => { element?.close(); document.body.style.overflow = previousOverflow }
  }, [open])

  useEffect(() => {
    if (!open) return
    let active = true
    Promise.allSettled(ids.map((id) => createEldokanApi(locale).products.get(id as `prd_${number}`, { lang: locale }))).then((results) => {
      if (!active) return
      setProducts(Object.fromEntries(results.map((result, index) => [ids[index], result.status === 'fulfilled' ? result.value.data : null])))
      setLoadedKey(selectionKey)
    })
    return () => { active = false }
  }, [ids, locale, open, selectionKey, reload])

  const loaded = loadedKey === selectionKey
  const available = loaded ? ids.flatMap((id) => products[id] ? [products[id]!] : []) : []
  return <Context.Provider value={show}>{children}
    <dialog ref={dialog} aria-labelledby="comparison-drawer-title" dir={ar ? 'rtl' : 'ltr'} onCancel={() => setOpen(false)} onClick={(event) => {
      if (event.target !== event.currentTarget) return
      const bounds = event.currentTarget.getBoundingClientRect()
      if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close()
    }} className={`comparison-drawer fixed inset-y-0 m-0 h-dvh max-h-none w-full max-w-none border-0 bg-card p-0 text-foreground shadow-2xl backdrop:bg-black/50 ${ar ? 'left-0 right-auto' : 'left-auto right-0'} ${expanded ? 'sm:w-[95vw]' : 'sm:w-[480px]'}`}>
      <div className="flex h-full flex-col">
        <header className="flex shrink-0 items-center justify-between gap-3 border-b px-5 py-5"><div><h2 id="comparison-drawer-title" className="flex items-center gap-2 text-xl font-bold"><GitCompareArrows className="size-5 text-shop-accent" aria-hidden="true" />{text('Product comparison', 'مقارنة المنتجات')}</h2><p className="mt-1 text-xs text-muted-foreground">{ids.length}/4 · {text('Specifications, options, warranty & more', 'المواصفات والاختيارات والضمان والمزيد')}</p></div><button type="button" autoFocus onClick={close} aria-label={text('Close comparison', 'إغلاق المقارنة')} className="rounded-full border p-2.5 hover:bg-background"><X className="size-5" aria-hidden="true" /></button></header>
        <div className="min-h-0 flex-1 overflow-y-auto p-5">
          {error && <p role="alert" className="mb-4 rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{error}</p>}
          {expanded && loaded && available.length < ids.length && <p role="alert" className="mb-4 rounded-lg bg-shop-soft p-3 text-sm text-shop-accent">{text('Only loaded products are shown. Go back to retry the remaining products.', 'المقارنة تعرض المنتجات اللي اتحمّلت فقط. ارجع للقائمة لإعادة تحميل الباقي.')}</p>}
          {expanded ? <><button type="button" onClick={() => setExpanded(false)} className="mb-5 flex items-center gap-2 text-sm font-semibold"><ArrowLeft className="size-4" aria-hidden="true" />{text('Back to selected products', 'العودة للمنتجات المختارة')}</button>{available.length >= 2 ? <ComparisonTable products={available} /> : <p className="text-sm text-muted-foreground">{text('At least two loaded products are needed.', 'محتاجين منتجين محمّلين على الأقل للمقارنة.')}</p>}</> : <>
            <p className="mb-5 text-sm leading-6 text-muted-foreground">{text('Add products while browsing, then compare their full details side by side.', 'ضيف المنتجات وإنت بتتصفح، وبعدها قارن تفاصيلها كاملة جنب بعض.')}</p>
            <ul className="space-y-3">{ids.map((id) => {
              const product = loaded ? products[id] : undefined
              const image = product?.images[0]?.url ?? product?.image?.url
              return <li key={id} className="rounded-xl border p-4"><div className="flex items-start gap-3">{image ? <Image src={image} alt={product?.name ?? ''} width={80} height={80} className="size-16 shrink-0 rounded-lg bg-background object-contain" /> : <div className="size-16 shrink-0 animate-pulse rounded-lg bg-muted" />}<div className="min-w-0 flex-1"><Link href={`/product/${id}`} onClick={close} className="line-clamp-2 text-sm font-semibold hover:underline">{product?.name ?? (loaded ? id : text('Loading product…', 'جارٍ تحميل المنتج…'))}</Link>{product && <><p className="mt-2 font-bold text-shop-accent">{product.pricing.price?.formatted}</p><p className="mt-1 text-xs text-muted-foreground">{product.brand?.name ?? product.seller?.name}</p></>}{loaded && !product && <p className="mt-2 text-xs text-danger-foreground">{text('Could not load product details.', 'تعذر تحميل تفاصيل المنتج.')}</p>}</div><button type="button" onClick={() => save(ids.filter((item) => item !== id))} aria-label={`${text('Remove', 'إزالة')} ${product?.name ?? id}`} className="rounded p-1.5 text-muted-foreground hover:bg-danger-soft hover:text-danger-foreground"><X className="size-4" aria-hidden="true" /></button></div>{product && <dl className="mt-3 space-y-1 border-t pt-3 text-xs text-muted-foreground">{product.attributes.slice(0, 3).map((a, index) => <div key={`${a.slug}-${index}`} className="flex justify-between gap-3"><dt>{a.name}</dt><dd className="max-w-[65%] text-end">{a.options.map((option) => option.name).join(ar ? '، ' : ', ')}</dd></div>)}{product.warranty.label && <div className="flex justify-between gap-3"><dt>{text('Warranty', 'الضمان')}</dt><dd>{product.warranty.label}</dd></div>}</dl>}</li>
            })}</ul>
            {!ids.length && <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">{text('Select products using Compare on their cards.', 'اختار المنتجات من زر المقارنة على كروتها.')}</p>}
            {ids.length < 4 && <button type="button" onClick={close} className="mt-4 w-full rounded-xl border border-dashed px-4 py-4 text-sm font-semibold">{text('+ Continue browsing & add a product', '+ كمل التصفح وضيف منتج')}</button>}
            {loaded && available.length < ids.length && <button type="button" onClick={() => setReload((value) => value + 1)} className="mt-4 text-sm font-semibold underline">{text('Retry loading details', 'إعادة تحميل التفاصيل')}</button>}
          </>}
        </div>
        <footer className="shrink-0 space-y-3 border-t bg-card p-5"><button type="button" disabled={available.length < 2} onClick={() => setExpanded(true)} className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#f5b400] text-primary-foreground px-4 py-3.5 font-bold disabled:opacity-50"><Maximize2 className="size-4" aria-hidden="true" />{text('Compare full details', 'قارن التفاصيل كاملة')}</button>{ids.length < 2 && <p className="text-center text-xs text-muted-foreground">{text('Add at least one more product to compare.', 'ضيف منتج تاني على الأقل علشان تقارن.')}</p>}{ids.length >= 2 && <Link onClick={close} href={`/compare?ids=${encodeURIComponent(ids.join(','))}`} className="block text-center text-xs font-semibold text-muted-foreground underline">{text('Open comparison in a separate page', 'افتح المقارنة في صفحة مستقلة')}</Link>}</footer>
      </div>
    </dialog>
  </Context.Provider>
}

export function useComparisonDrawer() {
  const show = useContext(Context)
  if (!show) throw new Error('ComparisonProvider is required')
  return show
}
