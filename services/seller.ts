import { mapLimited } from '@/lib/map-limited'
import { createEldokanApi } from '@/lib/eldokan-api'
import { adaptProductList } from '@/lib/catalog-adapters'
import { getLocale } from '@/lib/server-locale'
import { EldokanClientError } from '@eldokan/customer-api-client'

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
  } catch (cause) {
    if (cause instanceof EldokanClientError && cause.status === 404) return null
    throw cause
  }
}

export async function SellerProducts(sellerId: string) {
  const firstPage = await SellerProductPage(sellerId)
  const remainingPages = await mapLimited(
    Array.from({ length: Math.max(0, firstPage.meta.total_pages - 1) }, (_, index) => index + 2), 4, page => SellerProductPage(sellerId, page),
  )
  return [firstPage.data, ...remainingPages.map((page) => page.data)].flat()
}

export async function SellerProductPage(sellerId: string, page = 1) {
  try {
    return { ...adaptProductList(await createEldokanApi(await getLocale()).sellers.products(sellerId as `sel_${number}`, { page, perPage: PRODUCTS_PER_PAGE })), unavailable: false }
  } catch { return { data: [], meta: { total_pages: 1, total: 0 }, unavailable: true } }
}
