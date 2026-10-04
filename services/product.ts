import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import { adaptProductList } from '@/lib/catalog-adapters'
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
    return adaptProductList(response)
  } catch {
    return { data: [], meta: { total_pages: 1 } }
  }
}

export async function SearchProducts(search: string, locale?: 'ar' | 'en') {
  try {
    const language = locale ?? await getLocale()
    const api = createEldokanApi(language)
    const response = await retryApiRead(() => api.products.list({ search: search.trim(), perPage: 48 }))
    return adaptProductList(response)
  } catch {
    return { data: [] }
  }
}

async function getAllProducts(options: Omit<ProductOptions, 'page' | 'perPage'> = {}) {
  const firstPage = await Products(undefined, { ...options, page: 1, perPage: 48 })
  const remainingPages = await Promise.all(
    Array.from({ length: Math.max(0, firstPage.meta.total_pages - 1) }, (_, index) =>
      Products(undefined, { ...options, page: index + 2, perPage: 48 }),
    ),
  )
  return [firstPage.data, ...remainingPages.map((page) => page.data)].flat()
}

export function ProductsByBrand(brand: string) {
  return getAllProducts({ brand })
}

export function ProductsByPriceRange(minPrice?: number, maxPrice?: number) {
  return getAllProducts({ minPrice, maxPrice })
}
