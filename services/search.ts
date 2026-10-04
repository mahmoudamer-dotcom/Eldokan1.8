import { createEldokanApi } from '@/lib/eldokan-api'
import type { Locale } from '@/lib/i18n'
import { retryApiRead } from '@/lib/retry-api-read'

const searchCache = new Map<string, { data: unknown; savedAt: number }>()
const CACHE_TTL_MS = 30_000

export async function Search(productName: string, locale: Locale = 'en') {
  const searchTerm = productName.trim()
  const cacheKey = `${locale}:${searchTerm.toLocaleLowerCase()}`
  const cached = searchCache.get(cacheKey)
  if (cached && Date.now() - cached.savedAt < CACHE_TTL_MS) return { data: cached.data }
  if (searchTerm.length < 2) return { data: [] }

  try {
    const api = createEldokanApi(locale)
    const result = await retryApiRead(() => api.search.suggestions(searchTerm, { limit: 8 }))
    const data = result.data.map((suggestion) => ({
      id: suggestion.id,
      name: suggestion.name,
      slug: suggestion.slug,
      image: suggestion.image,
      pricing: {
        on_sale: false,
        sale_price: null,
        regular_price: suggestion.price,
      },
    }))
    searchCache.set(cacheKey, { data, savedAt: Date.now() })
    return { ...result, data }
  } catch {
    return { data: [] }
  }
}
