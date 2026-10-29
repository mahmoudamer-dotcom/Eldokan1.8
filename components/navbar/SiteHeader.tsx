import Category from '@/components/category/Category'
import Navbar from '@/components/navbar/Navbar'

export default function SiteHeader() {
  return (
    <header className="sticky top-0 z-40 mb-4 w-full bg-card shadow-sm">
      <Navbar />
      <Category />
    </header>
  )
}
