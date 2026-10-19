import { createEldokanApi } from '@/lib/eldokan-api'
import type { Locale } from '@/lib/i18n'
import { toProductDetail } from '@/lib/catalog-adapters'
import { retryApiRead } from '@/lib/retry-api-read'
import { cache } from 'react'
import { EldokanClientError } from '@eldokan/customer-api-client'

export const ProductsDetails = cache(async (id: string, locale: Locale = 'en') => {
  const productId = /^\d+$/.test(id) ? `prd_${id}` : id
  try {
    const api = createEldokanApi(locale)
    const response = await retryApiRead(() => api.products.get(productId as `prd_${number}`))
    return { ...response, data: toProductDetail(response.data) }
  } catch (cause) {
    return { data: null, unavailable: !(cause instanceof EldokanClientError && cause.status === 404) }
  }
})
