import { createEldokanApi } from '@/lib/eldokan-api'
import { toStoreProduct } from '@/lib/catalog-adapters'
import type { ManagedStory } from '@/components/stories/Story'
import { retryApiRead } from '@/lib/retry-api-read'

export async function fetchStoreStories(): Promise<ManagedStory[] | null> {
  try {
    const response = await retryApiRead(() => createEldokanApi().stories.list())
    return response.data.items.map(story => ({
      id: story.id, title: story.title, image: story.image, product: toStoreProduct(story.product),
    }))
  } catch {
    // Keep existing product stories until the companion plugin is available.
    return null
  }
}
