import { createEldokanApi } from '@/lib/eldokan-api'
import { retryApiRead } from '@/lib/retry-api-read'
import type { Language } from '@eldokan/customer-api-client'

/** Read the entire brand catalog, including brands beyond the first API page. */
export async function fetchCatalogBrands(language: Language) {
  const api = createEldokanApi(language)
  const first = await retryApiRead(() => api.brands.list({ page: 1, perPage: 100 }))
  const brands = [...first.data]
  // Limit concurrent requests as the catalog grows without limiting its size.
  for (let page = 2; page <= first.meta.total_pages; page += 4) {
    const pages = await Promise.all(
      Array.from({ length: Math.min(4, first.meta.total_pages - page + 1) }, (_, offset) =>
        retryApiRead(() => api.brands.list({ page: page + offset, perPage: 100 })),
      ),
    )
    brands.push(...pages.flatMap((response) => response.data))
  }
  return brands
}
