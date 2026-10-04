import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import { toStoreProduct } from '@/lib/catalog-adapters'
import type { HomeSection } from '@/types/home'
import { retryApiRead } from '@/lib/retry-api-read'

export async function HomeApi() {
  try {
    const api = createEldokanApi(await getLocale())
    const response = await retryApiRead(() => api.home.get())
    const sections = response.data.sections.map((section) => section.type === 'product_carousel'
      ? { ...section, items: section.items.map(toStoreProduct) }
      : section)
    return { ...response, data: { ...response.data, sections } } as unknown as { data: { sections: HomeSection[] } }
  } catch {
    return { data: { sections: [] } }
  }
}
