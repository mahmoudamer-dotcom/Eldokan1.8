import { Star } from 'lucide-react'
import Link from 'next/link'
import ProductImageCarousel from './ProductImageCarousel'
import T from '@/components/i18n/T'
import WishlistButton from '@/components/wishlist/WishlistButton'
import AddToCartButton from '@/components/cart/AddToCartButton'

export type StoreProduct = {
  id?: string | number
  sku?: string
  name: string
  title?: string
  brand?: { id?: string; name?: string; slug?: string }
  image?: { url?: string; alt?: string }
  images?: Array<string | { url?: string; alt?: string }>
  seller?: { id?: string; name?: string } | null
  stock?: { status?: string; quantity?: number | null; backorders_allowed?: boolean }
  average_rating?: number
  rating_count?: number
  pricing?: {
    on_sale?: boolean
    sale_price?: { formatted?: string; amount?: number } | null
    regular_price?: { formatted?: string; amount?: number } | null
  }
}

export default function ProductCard({ product }: { product: StoreProduct }) {
  const name = product.name ?? product.title ?? 'Product'
  const images = [product.image, ...(product.images ?? [])]
    .map((image) => typeof image === 'string' ? { url: image } : image)
    .filter((image): image is { url: string; alt?: string } => Boolean(image?.url))
    .filter((image, index, all) => all.findIndex((candidate) => candidate.url === image.url) === index)
  const salePrice = product.pricing?.sale_price
  const regularPrice = product.pricing?.regular_price
  const hasDiscount = Boolean(
    product.pricing?.on_sale &&
      salePrice?.amount !== undefined &&
      regularPrice?.amount &&
      regularPrice.amount > salePrice.amount,
  )
  const discount = hasDiscount
    ? Math.round(((regularPrice!.amount! - salePrice!.amount!) / regularPrice!.amount!) * 100)
    : null

  const card = (
    <article className="group flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-lg">
      <div className="relative aspect-square overflow-hidden bg-gray-50 p-0">
        <ProductImageCarousel productId={product.id} productName={name} initialImages={images} />
        <WishlistButton productId={product.id} className="absolute right-3 top-3 z-10" />
        <AddToCartButton compact product={product.id ? {
          id: product.id,
          name,
          imageUrl: images[0]?.url,
          unitPrice: product.pricing?.on_sale
            ? product.pricing.sale_price?.amount ?? product.pricing.regular_price?.amount ?? 0
            : product.pricing?.regular_price?.amount ?? 0,
          stockQuantity: product.stock?.quantity,
          available: product.stock?.status !== 'out_of_stock',
        } : undefined} />
        {discount !== null && (
          <span className="absolute left-3 top-3 rounded-md bg-[#f5b400] px-2 py-1 text-xs font-bold text-gray-900">
            -{discount}%
          </span>
        )}
      </div>
      <div className="flex flex-1 flex-col p-3 sm:p-4">
        {(product.brand?.name || product.seller?.name) && <p className="mb-1 truncate text-xs font-medium text-gray-500">{product.brand?.name || product.seller?.name}</p>}
        {product.brand?.name && product.seller?.name && <p className="mb-1 truncate text-[11px] text-gray-400"><T text="Sold by" /> {product.seller.name}</p>}
        <h2 className="line-clamp-2 min-h-10 text-sm leading-5 text-gray-800 sm:text-[15px]"><Link href={`/product/${product.id}`} className="transition hover:text-[#a66d00]"><T text={name} /></Link></h2>
        {Boolean(product.average_rating && product.rating_count) && (
          <p className="mt-1.5 flex items-center gap-1 text-xs text-gray-500">
            <Star className="size-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />
            <span className="font-semibold text-gray-700">{product.average_rating!.toFixed(1)}</span>
            <span>({product.rating_count} <T text="reviews" />)</span>
          </p>
        )}
        {product.stock?.status && (
          <p className={`mt-1.5 flex items-center gap-1.5 text-[11px] font-medium ${product.stock.status === 'in_stock' ? 'text-emerald-700' : 'text-gray-500'}`}>
            <span className={`size-1.5 rounded-full ${product.stock.status === 'in_stock' ? 'bg-emerald-500' : 'bg-gray-400'}`} aria-hidden="true" />
            <T text={product.stock.status === 'in_stock' ? 'In stock' : 'Currently unavailable'} />
          </p>
        )}
        <div className="mt-auto flex flex-wrap items-baseline gap-x-2 gap-y-1 pt-3">
          {product.pricing?.on_sale && salePrice?.formatted ? (
            <>
              <span className="font-bold text-gray-900">{salePrice.formatted}</span>
              {regularPrice?.formatted && <span className="text-xs text-gray-400 line-through">{regularPrice.formatted}</span>}
            </>
          ) : (
            <span className="font-bold text-gray-900">{regularPrice?.formatted ?? <T text="Price unavailable" />}</span>
          )}
        </div>
      </div>
    </article>
  )

  return card
}
