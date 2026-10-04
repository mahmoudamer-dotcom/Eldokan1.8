import { createEldokanApi } from '@/lib/eldokan-api'
import { Products } from '@/services/product'
import { getLocale } from '@/lib/server-locale'

const PRODUCTS_PER_PAGE = 48

export type SellerData = {
  id: string
  name: string
  slug?: string
  rating?: number | null
  rating_count?: number | null
}

export async function SellerDetails(id: string) {
  try {
    const result = await createEldokanApi(await getLocale()).sellers.get(id as `sel_${number}`)
    return result.data as SellerData
  } catch {
    return null
  }
}

export async function SellerProducts(sellerId: string) {
  const firstPage = await Products(undefined, { page: 1, perPage: PRODUCTS_PER_PAGE })
  const remainingPages = await Promise.all(
    Array.from({ length: Math.max(0, firstPage.meta.total_pages - 1) }, (_, index) =>
      Products(undefined, { page: index + 2, perPage: PRODUCTS_PER_PAGE }),
    ),
  )
  return [firstPage.data, ...remainingPages.map((page) => page.data)].flat()
    .filter((product) => String(product.seller?.id ?? '') === sellerId)
}
