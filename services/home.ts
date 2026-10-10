import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import { toStoreProduct } from '@/lib/catalog-adapters'
import type { HomeCategoryProductGroup } from '@/components/home/HomeCategoryShelves'
import type { HomeSection } from '@/types/home'
import { retryApiRead } from '@/lib/retry-api-read'

export async function HomeApi() {
  try {
    const api = createEldokanApi(await getLocale())
    const response = await retryApiRead(() => api.home.get())
    const sections = response.data.sections.map((section) => section.type === 'product_carousel'
      ? { ...section, items: section.items.map(toStoreProduct) }
      : section)
    // Optional shelf data must not hide the existing home sections if a group is malformed.
    const categoryShelves = Array.isArray(response.data.category_shelves)
      ? response.data.category_shelves.flatMap(group => {
        if (!group?.category || !Array.isArray(group.products)) return []
        try {
          return [{ category: group.category, products: group.products.map(toStoreProduct) }]
        } catch {
          return []
        }
      })
      : undefined
    return { ...response, data: { ...response.data, sections, categoryShelves } } as unknown as { data: { sections: HomeSection[]; categoryShelves?: HomeCategoryProductGroup[] } }
  } catch {
    return { data: { sections: [], categoryShelves: undefined } }
  }
}
