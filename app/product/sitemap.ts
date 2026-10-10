import type { MetadataRoute } from 'next'
import { createEldokanApi } from '@/lib/eldokan-api'
import { getStorefrontUrl } from '@/lib/site-url'

export const revalidate = 3600
export async function generateSitemaps() {
  if (!getStorefrontUrl() || process.env.STOREFRONT_ALLOW_INDEXING !== 'true') return []
  const page = await createEldokanApi('en').commerce.sitemap(1)
  return Array.from({ length: page.data.total_pages }, (_, index) => ({ id: index + 1 }))
}
export default async function sitemap({ id }: { id: Promise<string> }): Promise<MetadataRoute.Sitemap> {
  const origin = getStorefrontUrl()
  if (!origin || process.env.STOREFRONT_ALLOW_INDEXING !== 'true') return []
  const page = Number(await id)
  if (!Number.isInteger(page) || page < 1 || page > 10000) return []
  const response = await createEldokanApi('en').commerce.sitemap(page)
  return response.data.items.map(product => ({ url: new URL(`/product/${product.id}`, origin).toString(), ...(product.updated_at ? { lastModified: product.updated_at } : {}) }))
}
