import type { MetadataRoute } from 'next'
import { getStorefrontUrl } from '@/lib/site-url'
import { createEldokanApi } from '@/lib/eldokan-api'

export const revalidate = 3600
export default async function robots(): Promise<MetadataRoute.Robots> {
  const url = getStorefrontUrl()
  if (!url || process.env.STOREFRONT_ALLOW_INDEXING !== 'true') return { rules: { userAgent: '*', disallow: '/' } }
  // robots.txt must still be served when the catalog is briefly unreachable.
  const productPages = await createEldokanApi('en').commerce.sitemap(1).then((products) => products.data.total_pages, () => 0)
  return { rules: { userAgent: '*', allow: '/', disallow: ['/api/', '/account', '/cart', '/checkout/', '/orders', '/invoice', '/wishlist', '/login', '/register', '/search', '/forgot-password', '/reset-password'] }, sitemap: [new URL('/sitemap.xml', url).toString(), ...Array.from({ length: productPages }, (_, index) => new URL(`/product/sitemap/${index + 1}.xml`, url).toString())] }
}
