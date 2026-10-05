import Link from 'next/link'
import T from '@/components/i18n/T'

type Props = { productId: string; quantity?: number; className?: string }

export default function BuyNowButton({ productId, quantity = 1, className }: Props) {
  return <Link href={`/checkout/${encodeURIComponent(productId)}?quantity=${quantity}`} className={className}>
    <T text="Buy now" />
  </Link>
}
