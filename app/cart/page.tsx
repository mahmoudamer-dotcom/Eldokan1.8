import CartPage from '@/components/cart/CartPage'
import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('Shopping cart', 'سلة التسوق', { robots: { index: false, follow: false } })

export default function Page() {
  return <CartPage />
}
