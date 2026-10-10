import type { MetadataRoute } from 'next'
import { getStorefrontUrl } from '@/lib/site-url'
import { createEldokanApi } from '@/lib/eldokan-api'

export const revalidate = 3600
export default async function robots(): Promise<MetadataRoute.Robots> {
  const url = getStorefrontUrl()
  if (!url || process.env.STOREFRONT_ALLOW_INDEXING !== 'true') return { rules: { userAgent: '*', disallow: '/' } }
  const products = await createEldokanApi('en').commerce.sitemap(1)
  return { rules: { userAgent: '*', allow: '/', disallow: ['/api/', '/account', '/cart', '/checkout/', '/orders', '/wishlist', '/login', '/register', '/search', '/forgot-password', '/reset-password'] }, sitemap: [new URL('/sitemap.xml', url).toString(), ...Array.from({ length: products.data.total_pages }, (_, index) => new URL(`/product/sitemap/${index + 1}.xml`, url).toString())] }
}
