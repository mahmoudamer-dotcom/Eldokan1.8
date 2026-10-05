import type { ProductCard as ApiProductCard, ProductDetail as ApiProductDetail, ProductListResponse } from '@eldokan/customer-api-client'
import type { ProductDetailData } from '@/types/product'
import type { StoreProduct } from '@/components/productCard/ProductCard'

type ApiMoney = { formatted: string; amount: number; currency?: string; decimals?: number } | null

function money(value: ApiMoney) {
  return value ? { formatted: value.formatted, amount: value.amount, currency: value.currency, decimals: value.decimals } : null
}

export function toStoreProduct(product: ApiProductCard | ApiProductDetail): StoreProduct {
  const regular = money(product.pricing.regular_price) ?? money(product.pricing.price) ?? money(product.pricing.min_price)
  const sale = money(product.pricing.sale_price)
  return {
    id: product.id,
    name: product.name,
    sku: 'sku' in product ? product.sku ?? undefined : undefined,
    brand: product.brand ?? undefined,
    image: product.image ?? undefined,
    images: 'images' in product ? product.images : undefined,
    seller: product.seller,
    stock: product.stock,
    average_rating: product.average_rating,
    rating_count: product.rating_count,
    pricing: {
      on_sale: product.pricing.on_sale,
      regular_price: regular,
      sale_price: sale,
    },
  }
}

export function toProductDetail(product: ApiProductDetail): ProductDetailData {
  const regular = money(product.pricing.regular_price) ?? money(product.pricing.price) ?? money(product.pricing.min_price)
  const sale = money(product.pricing.sale_price) ?? regular
  return {
    id: product.id,
    name: product.name,
    sku: product.sku ?? undefined,
    brand: product.brand ? { name: product.brand.name } : undefined,
    seller: product.seller,
    average_rating: product.average_rating,
    rating_count: product.rating_count,
    categories: product.categories,
    images: product.images,
    pricing: {
      on_sale: product.pricing.on_sale,
      regular_price: regular ?? { formatted: 'Price unavailable', amount: 0 },
      sale_price: sale ?? { formatted: 'Price unavailable', amount: 0 },
    },
    stock: product.stock,
    attributes: product.attributes.map((attribute) => ({
      name: attribute.name,
      options: attribute.options.map((option) => ({ name: option.name })),
    })),
    description_html: product.description_html,
    short_description_html: product.short_description_html,
  }
}

export function adaptProductList(response: ProductListResponse) {
  return { ...response, data: response.data.map(toStoreProduct) }
}
