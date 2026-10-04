import { createEldokanApi } from '@/lib/eldokan-api'
import type { Locale } from '@/lib/i18n'
import { toProductDetail } from '@/lib/catalog-adapters'
import { retryApiRead } from '@/lib/retry-api-read'

export async function ProductsDetails(id: string, locale: Locale = 'en') {
  const productId = /^\d+$/.test(id) ? `prd_${id}` : id
  try {
    const api = createEldokanApi(locale)
    const response = await retryApiRead(() => api.products.get(productId as `prd_${number}`))
    if (response?.data?.id || locale === 'en') return { ...response, data: toProductDetail(response.data) }
    const fallbackApi = createEldokanApi('en')
    const fallback = await retryApiRead(() => fallbackApi.products.get(productId as `prd_${number}`))
    return { ...fallback, data: toProductDetail(fallback.data) }
  } catch {
    if (locale === 'ar') {
      try {
        const api = createEldokanApi('en')
        const fallback = await retryApiRead(() => api.products.get(productId as `prd_${number}`))
        return { ...fallback, data: toProductDetail(fallback.data) }
      } catch {
        return { data: null }
      }
    }
    return { data: null }
  }
}
