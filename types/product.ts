import type { Attribute, Variation } from '@eldokan/customer-api-client'

export type ProductImageData = {
  url: string
}

export type ProductDetailData = {
  id?: string | number
  name: string
  type?: 'simple' | 'variable' | 'grouped' | 'external' | 'other'
  sku?: string
  brand?: { name?: string }
  seller?: {
    id?: string
    name?: string
    slug?: string
    rating?: number | string | null
    rating_count?: number | string | null
  } | null
  average_rating?: number
  rating_count?: number
  categories: Array<{ name: string; slug?: string }>
  images: ProductImageData[]
  pricing: {
    on_sale: boolean
    regular_price: { formatted: string; amount: number; currency?: string; decimals?: number }
    sale_price: { formatted: string; amount: number; currency?: string; decimals?: number }
  }
  stock?: { status?: string; quantity?: number | null; backorders_allowed?: boolean }
  attributes: Attribute[]
  variations: Variation[]
  description_html: string
  short_description_html: string
}
