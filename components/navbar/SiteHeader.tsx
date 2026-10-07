import Category from '@/components/category/Category'
import Navbar from '@/components/navbar/Navbar'

export default function SiteHeader() {
  return (
    <header className="lg:sticky lg:top-0 z-40 w-full bg-card shadow-sm mb-4">
      <Navbar />
      <Category />
    </header>
  )
}
