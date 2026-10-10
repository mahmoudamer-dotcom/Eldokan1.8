import type { ProductId } from '@eldokan/customer-api-client'
import CustomerReviews from '@/components/reviews/CustomerReviews'

export default function ProductReviews({ productId }: { productId: string }) {
  return <CustomerReviews key={productId} target={productId as ProductId} />
}
