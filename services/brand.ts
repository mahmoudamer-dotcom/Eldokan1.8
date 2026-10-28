import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import type { Brand, ProductCard } from '@eldokan/customer-api-client'
import { retryApiRead } from '@/lib/retry-api-read'
import { fetchCatalogBrands } from '@/lib/catalog-brands'
import { cache } from 'react'
import { unstable_cache } from 'next/cache'

export const Brands = cache(async () => {
  try {
    return await fetchCatalogBrands(await getLocale())
  } catch {
    return []
  }
})

/**
 * Footer brands on every page. Read through a 5-minute shared cache: the home answer itself is
 * never cached (WordPress edits must show at once), and without this every page would wait on it.
 */
export async function PopularBrands(limit = 8) {
  try {
    return await loadPopularBrands(limit)
  } catch {
    // A failed read is not cached, so the next page asks again.
    return { brands: [], source: 'featured' as const }
  }
}

const loadPopularBrands = unstable_cache(async (limit: number) => {
  const api = createEldokanApi()
  const response = await retryApiRead(() => api.home.get())
  const sections = response.data.sections.filter((section) => section.enabled)
  const bestSellerSection = sections.find((section) => section.type === 'product_carousel' && section.id === 'best_sellers')
  const bestSellers = (bestSellerSection?.items ?? []) as ProductCard[]

  const counts = new Map<string, { brand: NonNullable<(typeof bestSellers)[number]['brand']>; count: number }>()
  for (const product of bestSellers) {
    if (!product.brand) continue
    const key = product.brand.slug || product.brand.id
    const current = counts.get(key)
    counts.set(key, { brand: product.brand, count: (current?.count ?? 0) + 1 })
  }

  if (counts.size > 0) {
    const brands = [...counts.values()]
      .filter(({ brand }) => Boolean(brand.image?.url?.trim()))
      .sort((a, b) => b.count - a.count || a.brand.name.localeCompare(b.brand.name))
      .slice(0, limit)
      .map(({ brand }) => brand)
    if (brands.length > 0) return { brands, source: 'best-sellers' as const }
  }

  const brandSection = sections.find((section) => section.type === 'brand_grid')
  const brands = ((brandSection?.items ?? []) as Brand[])
    .filter((brand) => Boolean(brand.image?.url?.trim()))
    .slice(0, limit)
  return { brands, source: 'featured' as const }
}, ['footer-popular-brands-v1'], { revalidate: 300 })
