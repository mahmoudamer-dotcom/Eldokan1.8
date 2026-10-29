import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('Favorites', 'المفضلة', { robots: { index: false, follow: false } })

export default function WishlistLayout({ children }: LayoutProps<'/wishlist'>) {
  return children
}
