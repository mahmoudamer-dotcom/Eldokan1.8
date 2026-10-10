import type { MetadataRoute } from 'next'
import { getStorefrontUrl } from '@/lib/site-url'
import { createEldokanApi } from '@/lib/eldokan-api'
import { POLICIES } from '@/lib/legal-policies'

export const revalidate = 3600
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const url = getStorefrontUrl()
  if (!url || process.env.STOREFRONT_ALLOW_INDEXING !== 'true') return []
  const api = createEldokanApi('en')
  const catalog = await api.commerce.sitemap(1)
  const paths = ['/', '/help', '/policies', ...POLICIES.map(policy => `/policies/${policy.slug}`)]
  catalog.data.categories.forEach(category => paths.push(`/category/${encodeURIComponent(category.slug)}`))
  return [...new Set(paths)].map((path) => ({ url: new URL(path, url).toString() }))
}
