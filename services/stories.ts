import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import { toStoreProduct } from '@/lib/catalog-adapters'
import type { ManagedStory } from '@/components/stories/Story'
import { retryApiRead } from '@/lib/retry-api-read'

export async function fetchStoreStories(): Promise<ManagedStory[] | null> {
  try {
    const locale = await getLocale()
    let response = await retryApiRead(() => createEldokanApi(locale).stories.list())
    // Older companion versions can omit stories when a product has no language mapping.
    // Preserve the same published stories until the WordPress companion is updated.
    if (locale === 'ar' && response.data.items.length === 0) {
      const canonical = await retryApiRead(() => createEldokanApi('en').stories.list()).catch(() => null)
      if (canonical?.data.items.length) response = canonical
    }
    return response.data.items.map(story => ({
      id: story.id, title: story.title, image: story.image, product: toStoreProduct(story.product),
    }))
  } catch {
    // Keep existing product stories until the companion plugin is available.
    return null
  }
}
