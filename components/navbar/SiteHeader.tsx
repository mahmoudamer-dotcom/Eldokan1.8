import Category from '@/components/category/Category'
import Navbar from '@/components/navbar/Navbar'

export default function SiteHeader() {
  return (
    <header className="sticky top-0 z-40 w-full bg-white shadow-sm mb-4">
      <Navbar />
      <Category />
    </header>
  )
}
