import type { ProductDetailData } from '@/types/product'
import { getStorefrontUrl } from '@/lib/site-url'

export default function ProductStructuredData({ product }: { product: ProductDetailData }) {
  const origin = getStorefrontUrl()
  if (!origin || !product.id) return null
  const url = new URL(`/product/${product.id}`, origin).toString()
  const price = product.pricing.on_sale ? product.pricing.sale_price : product.pricing.regular_price
  const money = price && Number.isFinite(price.amount) && Number.isInteger(price.decimals) && price.decimals! >= 0 && price.decimals! <= 6 ? price.amount / 10 ** price.decimals! : null
  const availability = product.stock?.status === 'in_stock' ? 'InStock' : product.stock?.status === 'out_of_stock' ? 'OutOfStock' : product.stock?.status === 'on_backorder' ? 'BackOrder' : null
  const json = {
    '@context': 'https://schema.org', '@type': 'Product', name: product.name, url,
    image: product.images.map(image => image.url), sku: product.sku || undefined,
    description: (product.short_description_html || product.description_html).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 1500),
    brand: product.brand?.name ? { '@type': 'Brand', name: product.brand.name } : undefined,
    offers: money !== null && money >= 0 && price.currency && product.type === 'simple' ? { '@type': 'Offer', price: money, priceCurrency: price.currency, url, ...(availability ? { availability: `https://schema.org/${availability}` } : {}), seller: product.seller?.name ? { '@type': 'Organization', name: product.seller.name } : undefined } : undefined,
    aggregateRating: product.rating_count && product.average_rating && product.average_rating >= 1 && product.average_rating <= 5 ? { '@type': 'AggregateRating', ratingValue: product.average_rating, ratingCount: product.rating_count } : undefined,
  }
  const category = product.categories[0]
  const crumbs = [{ '@type': 'ListItem', position: 1, name: 'ElDokan', item: origin.toString() }, ...(category ? [{ '@type': 'ListItem', position: 2, name: category.name, item: new URL(`/category/${encodeURIComponent(category.slug || category.name)}`, origin).toString() }] : []), { '@type': 'ListItem', position: category ? 3 : 2, name: product.name, item: url }]
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify([json, { '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: crumbs }]).replace(/</g, '\\u003c') }} />
}
