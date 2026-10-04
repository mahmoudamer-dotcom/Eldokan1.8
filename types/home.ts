export type HomeImage = string | { url?: string | null; alt?: string | null } | null

export type HomeHeroSlide = {
  id: string
  title?: string
  subtitle?: string
  desktop_image?: HomeImage
  mobile_image?: HomeImage
  cta?: {
    text?: string
    type?: string
    value?: string
  } | null
}

export type HomePromoBanner = {
  id: string
  kind?: string
  title?: string
  subtitle?: string
  desktop_image?: HomeImage
  mobile_image?: HomeImage
  cta?: {
    text?: string
    type?: string
    value?: string
  } | null
}

export type HomeCategory = {
  id: string
  name: string
  slug?: string
  description?: string
  count?: number
  image?: HomeImage
}

export type HomeBrand = {
  id: string
  name: string
  slug?: string
  image?: HomeImage
  logo?: HomeImage
  logo_url?: string | null
  image_url?: string | null
  thumbnail?: HomeImage
  thumbnail_url?: string | null
  src?: string | null
  source_url?: string | null
}

export type HomeProduct = {
  id?: string | number
  name: string
  title?: string
  brand?: { id?: string; name?: string; slug?: string }
  sku?: string
  image?: { url?: string; alt?: string }
  images?: Array<string | { url?: string; alt?: string }>
  seller?: {
    id?: string
    name?: string
    slug?: string
    rating?: number | null
    rating_count?: number | null
  } | null
  average_rating?: number
  rating_count?: number
  stock?: { status?: string; quantity?: number }
  pricing?: {
    on_sale?: boolean
    regular_price?: { formatted?: string; amount?: number } | null
    sale_price?: { formatted?: string; amount?: number } | null
  }
}

export type HomeSection = {
  id: string
  type: string
  enabled: boolean
  order: number
  title?: string
  items: Array<HomeHeroSlide | HomePromoBanner | HomeCategory | HomeBrand | HomeProduct>
}
