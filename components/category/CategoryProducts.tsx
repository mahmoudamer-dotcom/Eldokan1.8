'use client'

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import type { FilterAttribute } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { toStoreProduct } from '@/lib/catalog-adapters'
import ProductCard, { type StoreProduct } from '@/components/productCard/ProductCard'
import { useLocale } from '@/components/i18n/LocaleProvider'
import T from '@/components/i18n/T'

function priceOf(product: StoreProduct) {
  const amount = product.pricing?.on_sale
    ? product.pricing.sale_price?.amount ?? product.pricing.regular_price?.amount
    : product.pricing?.regular_price?.amount
  return typeof amount === 'number' && Number.isFinite(amount) ? amount : null
}

export default function CategoryProducts({
  category,
  products: initialProducts,
  initialTotalPages,
  filters,
}: {
  category: string
  products: StoreProduct[]
  initialTotalPages: number
  filters: FilterAttribute[]
}) {
  const { locale, t } = useLocale()
  const [products, setProducts] = useState(initialProducts)
  const [selected, setSelected] = useState<Record<string, string[]>>({})
  const [sort, setSort] = useState('')
  const [appliedSort, setAppliedSort] = useState('')
  const [minPrice, setMinPrice] = useState('')
  const [maxPrice, setMaxPrice] = useState('')
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(initialTotalPages)
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [requestError, setRequestError] = useState(false)
  const [retryToken, setRetryToken] = useState(0)
  const sentinelRef = useRef<HTMLDivElement>(null)
  const requestId = useRef(0)
  const loadingMoreRef = useRef(false)
  const initialRender = useRef(true)

  const filterKey = JSON.stringify({ selected, sort, minPrice, maxPrice })
  const attributes = useMemo(() => filters.flatMap((filter) => {
    const optionIds = selected[filter.id] ?? []
    return optionIds.length ? [{ attributeId: filter.id, optionIds }] : []
  }), [filters, selected])

  // A changed filter or sort starts a fresh result set from page one.
  useEffect(() => {
    if (initialRender.current) {
      initialRender.current = false
      return
    }
    const currentRequest = ++requestId.current
    setLoading(true)
    setRequestError(false)
    const timer = window.setTimeout(async () => {
      try {
        const response = await createEldokanApi(locale).products.list({
          category,
          page: 1,
          perPage: 24,
          stockStatus: 'in_stock',
          attributes: attributes.length ? attributes : undefined,
          minPrice: minPrice ? Number(minPrice) : undefined,
          maxPrice: maxPrice ? Number(maxPrice) : undefined,
          sort: sort === 'price-asc' ? 'price_asc' : sort === 'price-desc' ? 'price_desc' : undefined,
        })
        if (currentRequest === requestId.current) {
          setProducts(response.data.map(toStoreProduct))
          setPage(1)
          setTotalPages(Math.max(1, response.meta.total_pages))
          setAppliedSort(sort)
        }
      } catch {
        if (currentRequest === requestId.current) setRequestError(true)
      } finally {
        if (currentRequest === requestId.current) setLoading(false)
      }
    }, 200)
    return () => window.clearTimeout(timer)
  }, [filterKey, attributes, category, locale, minPrice, maxPrice, sort, retryToken])

  const loadNextPage = useCallback(async () => {
    if (loading || loadingMoreRef.current || page >= totalPages) return
    loadingMoreRef.current = true
    setRequestError(false)
    setLoadingMore(true)
    const nextPage = page + 1
    const currentRequest = requestId.current
    try {
      const response = await createEldokanApi(locale).products.list({
        category,
        page: nextPage,
        perPage: 24,
        stockStatus: 'in_stock',
        attributes: attributes.length ? attributes : undefined,
        minPrice: minPrice ? Number(minPrice) : undefined,
        maxPrice: maxPrice ? Number(maxPrice) : undefined,
        sort: sort === 'price-asc' ? 'price_asc' : sort === 'price-desc' ? 'price_desc' : undefined,
      })
      if (currentRequest === requestId.current) {
        setProducts((current) => {
          const seen = new Set(current.map((product) => String(product.id)))
          return [...current, ...response.data.map(toStoreProduct).filter((product) => !seen.has(String(product.id)))]
        })
        setPage(nextPage)
        setTotalPages(Math.max(1, response.meta.total_pages))
      }
    } catch {
      if (currentRequest === requestId.current) setRequestError(true)
    } finally {
      loadingMoreRef.current = false
      setLoadingMore(false)
    }
  }, [loading, page, totalPages, locale, category, attributes, minPrice, maxPrice, sort])

  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel || typeof IntersectionObserver === 'undefined') return
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) void loadNextPage()
    }, { rootMargin: '500px 0px' })
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [loadNextPage])

  const displayedProducts = useMemo(() => {
    if (appliedSort !== 'price-asc' && appliedSort !== 'price-desc') return products
    return [...products].sort((a, b) => {
      const first = priceOf(a)
      const second = priceOf(b)
      if (first === null) return second === null ? 0 : 1
      if (second === null) return -1
      return appliedSort === 'price-asc' ? first - second : second - first
    })
  }, [products, appliedSort])

  function toggleOption(attributeId: string, optionId: string) {
    setSelected((current) => {
      const values = current[attributeId] ?? []
      return {
        ...current,
        [attributeId]: values.includes(optionId)
          ? values.filter((value) => value !== optionId)
          : [...values, optionId],
      }
    })
  }

  function clearFilters() {
    setSelected({})
    setMinPrice('')
    setMaxPrice('')
    setSort('')
  }

  const sidebar = <aside className="h-fit rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 lg:sticky lg:top-36" aria-label="Product filters">
    <div className="mb-5 flex items-center justify-between gap-2">
      <h2 className="text-lg font-bold text-gray-900"><T text="Filters" /></h2>
      <button type="button" onClick={clearFilters} className="text-xs font-semibold text-amber-800 underline underline-offset-4"><T text="Clear filters" /></button>
    </div>
    <label className="mb-5 grid gap-2 text-sm font-semibold text-gray-800"><T text="Sort by price" />
      <select value={sort} onChange={(event) => setSort(event.target.value)} className="h-10 rounded-lg border border-gray-300 bg-white px-3 font-normal">
        <option value=""><T text="Recommended" /></option>
        <option value="price-asc"><T text="Price: low to high" /></option>
        <option value="price-desc"><T text="Price: high to low" /></option>
      </select>
    </label>
    <fieldset className="mb-5 border-t border-gray-100 pt-4">
      <legend className="mb-2 text-sm font-semibold text-gray-800"><T text="Price range" /></legend>
      <div className="flex gap-2">
        <input type="number" min="0" value={minPrice} onChange={(event) => setMinPrice(event.target.value)} placeholder={t('Min')} aria-label={t('Minimum price')} className="h-10 min-w-0 w-1/2 rounded-lg border border-gray-300 px-3 text-sm" />
        <input type="number" min="0" value={maxPrice} onChange={(event) => setMaxPrice(event.target.value)} placeholder={t('Max')} aria-label={t('Maximum price')} className="h-10 min-w-0 w-1/2 rounded-lg border border-gray-300 px-3 text-sm" />
      </div>
    </fieldset>
    {filters.map((filter) => <fieldset key={filter.id} className="border-t border-gray-100 py-4 last:pb-0">
      <legend className="mb-2 text-sm font-semibold text-gray-800">{filter.name}</legend>
      <div className="max-h-52 space-y-2 overflow-y-auto">
        {filter.options.filter((option) => option.id).map((option) => <label key={option.id} className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
          <input type="checkbox" checked={(selected[filter.id] ?? []).includes(option.id!)} onChange={() => toggleOption(filter.id, option.id!)} className="size-4 accent-amber-600" />
          <span>{option.name}</span>
        </label>)}
      </div>
    </fieldset>)}
  </aside>

  const hasMore = page < totalPages

  return <>
    <div className="mt-6 flex items-center justify-between gap-3 lg:hidden">
      <button type="button" onClick={() => setMobileOpen((open) => !open)} aria-expanded={mobileOpen} className="inline-flex h-10 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-800 shadow-sm">
        <SlidersHorizontal className="size-4" /><T text="Filters" />
      </button>
      <p className="text-sm text-gray-500">{loading ? <T text="Updating products" /> : <>{displayedProducts.length} <T text="products" /></>}</p>
    </div>
    {mobileOpen && <div className="mt-4 lg:hidden">{sidebar}</div>}
    <div dir={locale === 'ar' ? 'rtl' : 'ltr'} className="mt-6 grid items-start gap-5 lg:grid-cols-[250px_minmax(0,1fr)]">
      <div className="hidden lg:block">{sidebar}</div>
      <section aria-label="Category products" aria-busy={loading || loadingMore}>
        <div className="mb-4 hidden items-center justify-between lg:flex">
          <p className="text-sm text-gray-500">{displayedProducts.length} <T text="products" /></p>
          {loading && <span className="text-sm text-gray-500"><T text="Updating products" /></span>}
        </div>
        {displayedProducts.length > 0 ? <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 xl:grid-cols-4">
          {displayedProducts.map((product, index) => <ProductCard key={product.id ?? index} product={product} />)}
        </div> : !loading && <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center"><h2 className="text-lg font-semibold text-gray-900"><T text={requestError ? 'Unable to load products' : 'No products match these filters'} /></h2>{requestError ? <button type="button" onClick={() => setRetryToken((token) => token + 1)} className="mt-3 text-sm font-semibold text-amber-800 underline underline-offset-4"><T text="Try again" /></button> : <button type="button" onClick={clearFilters} className="mt-3 text-sm font-semibold text-amber-800 underline underline-offset-4"><T text="Clear filters" /></button>}</div>}
        <div ref={sentinelRef} aria-hidden="true" className="h-8" />
        {(loadingMore || (loading && displayedProducts.length > 0)) && <p role="status" className="py-3 text-center text-sm text-gray-500"><T text="Loading more products" /></p>}
        {requestError && displayedProducts.length > 0 && <p role="status" className="py-3 text-center text-sm text-gray-500"><T text="Unable to load more products" /></p>}
        {requestError && hasMore && <div className="text-center"><button type="button" onClick={() => void loadNextPage()} className="text-sm font-semibold text-amber-800 underline underline-offset-4"><T text="Try again" /></button></div>}
      </section>
    </div>
  </>
}
