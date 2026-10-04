import { notFound } from 'next/navigation'
import CheckoutForm from '@/components/checkout/CheckoutForm'
import { getLocale } from '@/lib/server-locale'
import { ProductsDetails } from '@/services/productdetails'

export default async function CheckoutPage({ params }: { params: Promise<{ productId: string }> }) {
  const { productId } = await params
  const locale = await getLocale()
  const response = await ProductsDetails(productId, locale)
  const product = response.data
  if (!product?.id) notFound()
  const amount = product.pricing.on_sale ? product.pricing.sale_price.amount : product.pricing.regular_price.amount

  return <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:py-14">
    <h1 className="mb-7 text-3xl font-bold">{locale === 'ar' ? 'إتمام الشراء' : 'Checkout'}</h1>
    <CheckoutForm locale={locale} product={{
      id: String(product.id), name: product.name, sku: product.sku,
      imageUrl: product.images[0]?.url, amount,
      formattedAmount: product.pricing.on_sale ? product.pricing.sale_price.formatted : product.pricing.regular_price.formatted,
      unavailable: product.stock?.status === 'out_of_stock',
    }} googleMapsApiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY} />
  </main>
}
