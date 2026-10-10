import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import type { Brand, ProductCard } from '@eldokan/customer-api-client'
import { retryApiRead } from '@/lib/retry-api-read'
import { fetchCatalogBrands } from '@/lib/catalog-brands'
import { cache } from 'react'

export const Brands = cache(async () => {
  try {
    return await fetchCatalogBrands(await getLocale())
  } catch {
    return []
  }
})

export async function PopularBrands(limit = 8) {
  try {
    const api = createEldokanApi(await getLocale())
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
  } catch {
    return { brands: [], source: 'featured' as const }
  }
}
