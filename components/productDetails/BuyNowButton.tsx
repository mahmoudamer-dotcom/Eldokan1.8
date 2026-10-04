import Link from 'next/link'
import T from '@/components/i18n/T'

type Props = { productId: string; className?: string }

export default function BuyNowButton({ productId, className }: Props) {
  return <Link href={`/checkout/${encodeURIComponent(productId)}`} className={className}>
    <T text="Buy now" />
  </Link>
}
