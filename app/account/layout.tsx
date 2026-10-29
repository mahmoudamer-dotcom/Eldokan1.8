import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('My account', 'حسابي', { robots: { index: false, follow: false } })

export default function AccountLayout({ children }: LayoutProps<'/account'>) {
  return children
}
