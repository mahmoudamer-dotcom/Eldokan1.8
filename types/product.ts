export type ProductImageData = {
  url: string
}

export type ProductDetailData = {
  id?: string | number
  name: string
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
    regular_price: { formatted: string; amount: number }
    sale_price: { formatted: string; amount: number }
  }
  stock?: { status?: string }
  attributes: Array<{
    name: string
    options: Array<{ name: string }>
  }>
  description_html: string
  short_description_html: string
}
