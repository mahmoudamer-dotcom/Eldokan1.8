import type { MetadataRoute } from 'next'
import { getStorefrontUrl } from '@/lib/site-url'

export default function sitemap(): MetadataRoute.Sitemap {
  const url = getStorefrontUrl()
  if (!url || process.env.STOREFRONT_ALLOW_INDEXING !== 'true') return []
  return ['/', '/help'].map((path) => ({ url: new URL(path, url).toString() }))
}
