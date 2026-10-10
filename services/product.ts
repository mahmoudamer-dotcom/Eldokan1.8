import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import { adaptProductList } from '@/lib/catalog-adapters'
import { mapLimited } from '@/lib/map-limited'
import { retryApiRead } from '@/lib/retry-api-read'

type ProductOptions = {
  page?: number
  perPage?: number
  search?: string
  brand?: string
  minPrice?: number
  maxPrice?: number
  stockStatus?: 'in_stock' | 'out_of_stock' | 'on_backorder'
  sort?: 'newest' | 'price_asc' | 'price_desc' | 'best_selling' | 'rating' | 'relevance'
}

export async function Products(category?: string, options: ProductOptions = {}) {
  try {
    const api = createEldokanApi(await getLocale())
    const response = await retryApiRead(() => api.products.list({ category, ...options, perPage: options.perPage ?? 24 }))
    return { ...adaptProductList(response), unavailable: false }
  } catch {
    return { data: [], meta: { total_pages: 1 }, unavailable: true }
  }
}

export async function SearchProducts(search: string, locale?: 'ar' | 'en') {
  try {
    const language = locale ?? await getLocale()
    const api = createEldokanApi(language)
    const response = await retryApiRead(() => api.products.list({ search: search.trim(), perPage: 48 }))
    return { ...adaptProductList(response), resolvedSearch: (response.meta as typeof response.meta & { resolved_search?: string | null }).resolved_search ?? null, unavailable: false }
  } catch {
    return { data: [], resolvedSearch: null, unavailable: true }
  }
}

async function getAllProducts(options: Omit<ProductOptions, 'page' | 'perPage'> = {}) {
  const firstPage = await Products(undefined, { ...options, page: 1, perPage: 48 })
  if (firstPage.unavailable) throw new Error('Catalog service temporarily unavailable')
  const remainingPages = await mapLimited(
    Array.from({ length: Math.max(0, firstPage.meta.total_pages - 1) }, (_, index) => index + 2), 4, page => Products(undefined, { ...options, page, perPage: 48 }),
  )
  if (remainingPages.some(page => page.unavailable)) throw new Error('Catalog service temporarily unavailable')
  return [firstPage.data, ...remainingPages.map((page) => page.data)].flat()
}

export function ProductsByBrand(brand: string) {
  return getAllProducts({ brand })
}

export function ProductsByPriceRange(minPrice?: number, maxPrice?: number) {
  return getAllProducts({ minPrice, maxPrice })
}
