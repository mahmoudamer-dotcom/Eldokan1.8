import type { MetadataRoute } from 'next'
import { getStorefrontUrl } from '@/lib/site-url'

export default function robots(): MetadataRoute.Robots {
  const url = getStorefrontUrl()
  if (!url || process.env.STOREFRONT_ALLOW_INDEXING !== 'true') return { rules: { userAgent: '*', disallow: '/' } }
  return { rules: { userAgent: '*', allow: '/', disallow: ['/api/', '/account', '/cart', '/checkout/', '/orders', '/wishlist', '/login', '/register', '/search'] }, sitemap: new URL('/sitemap.xml', url).toString() }
}
