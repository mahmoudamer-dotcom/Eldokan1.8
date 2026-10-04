import Footer from '@/components/footer/Footer'
import HomeBrandSection from '@/components/home/HomeBrandSection'
import HomeBudgetFilter from '@/components/home/HomeBudgetFilter'
import HomeCategoryShelves from '@/components/home/HomeCategoryShelves'
import HomeCategoryShortcuts from '@/components/home/HomeCategoryShortcuts'
import HomeDailyOffers from '@/components/home/HomeDailyOffers'
import type { HomeCategoryProductGroup } from '@/components/home/HomeCategoryShelves'
import HomeFallbackCategorySection from '@/components/home/HomeFallbackCategorySection'
import HomeProductCarousel from '@/components/home/HomeProductCarousel'
import HomeQuickSearches from '@/components/home/HomeQuickSearches'
import HomeSellerSpotlight from '@/components/home/HomeSellerSpotlight'
import RecentlyViewed from '@/components/home/RecentlyViewed'
import Story from '@/components/stories/Story'
import BannerGrid from '@/components/banner/BannerGrid'
import Slider from '@/components/slider/Slider'
import { Brands } from '@/services/brand'
import { HomeApi } from '@/services/home'
import { fetchUsers } from '@/services/category'
import { Products } from '@/services/product'
import type { HomeBrand, HomeCategory, HomeHeroSlide, HomeProduct, HomePromoBanner, HomeSection } from '@/types/home'
import { mapLimited } from '@/lib/map-limited'

function isInStock(product: { stock?: { status?: string } }) {
  return product.stock?.status === 'in_stock'
}

export default async function Home() {
  const [response, brandsResponse] = await Promise.all([HomeApi(), Brands()])
  const sections: HomeSection[] = Array.isArray(response?.data?.sections)
    ? response.data.sections.filter((section: HomeSection) => section.enabled).sort((a: HomeSection, b: HomeSection) => a.order - b.order)
    : []

  const hasProductSections = sections.some((section) => section.type === 'product_carousel')
  const firstProductSectionId = sections.find((section) => section.type === 'product_carousel')?.id
  const categorySection = sections.find((section) => section.type === 'category_grid')
  const homeCategories = (categorySection?.items ?? []) as HomeCategory[]
  const categoryProductGroups: HomeCategoryProductGroup[] = await mapLimited(
    homeCategories,
    4,
    async (category) => {
      const productResponse = await Products(category.slug ?? category.name)
      return {
        category,
        products: (Array.isArray(productResponse?.data) ? productResponse.data : []).filter(isInStock),
      }
    },
  )
  let fallbackGroups: Array<{ categoryName: string; products: HomeProduct[] }> = []

  if (!hasProductSections && homeCategories.length === 0) {
    const categoryResponse = await fetchUsers()
    const categories = Array.isArray(categoryResponse?.data) ? categoryResponse.data : []
    fallbackGroups = await mapLimited(
      categories as Array<{ name: string; slug?: string }>,
      4,
      async (category) => {
        const productResponse = await Products(category.slug ?? category.name)
        return {
          categoryName: category.name,
          products: (Array.isArray(productResponse?.data) ? productResponse.data : []).filter(isInStock),
        }
      },
    )
  }

  const isProductSection = (section: HomeSection) => section.type === 'product_carousel'
  const matchesSection = (section: HomeSection, terms: string[]) => {
    const label = `${section.id} ${section.title ?? ''}`.toLocaleLowerCase()
    return terms.some((term) => label.includes(term))
  }
  const heroSections = sections.filter((section) => section.type === 'hero_slider')
  const promoSections = sections.filter((section) => section.type === 'banner_grid')
  const dealSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['deal', 'offer', 'discount']))
  const featuredSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['featured']) && !matchesSection(section, ['deal']))
  const bestSellerSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['best']))
  const arrivalSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['new', 'arrival']))
  const placedProductIds = new Set([...featuredSections, ...bestSellerSections, ...arrivalSections].map((section) => section.id))
  const otherProductSections = sections.filter((section) => isProductSection(section) && !placedProductIds.has(section.id))
  const brandSections = sections.filter((section) => section.type === 'brand_grid')
  const apiBrands = Array.isArray(brandsResponse) ? brandsResponse as HomeBrand[] : []
  const fallbackBrands = brandSections.flatMap((section) => section.items as HomeBrand[])
  const brands = apiBrands.length > 0 ? apiBrands : fallbackBrands
  const homeProducts = [
    ...sections.filter(isProductSection).flatMap((section) => (section.items as HomeProduct[]).filter(isInStock)),
    ...categoryProductGroups.flatMap(({ products }) => products as HomeProduct[]),
  ]
  return (
    <div className="flex min-h-screen w-full flex-col">
      <main className="container mx-auto w-full flex-1 space-y-10 px-4 pb-14 pt-5 sm:space-y-12 sm:pt-7">
        <Story products={homeProducts} />
        {heroSections.length > 0
          ? heroSections.map((section) => <Slider key={section.id} slides={section.items as HomeHeroSlide[]} />)
          : <Slider />}

        <HomeDailyOffers sections={dealSections} products={homeProducts} />

        {/* <HomeCategoryShortcuts categories={homeCategories} /> */}
       

        {promoSections.map((section) => (
          <BannerGrid key={section.id} banners={section.items as HomePromoBanner[]} title={section.title} />
        ))}

        <div className="space-y-10 rounded-3xl bg-gray-50 px-4 py-7 sm:px-6 sm:py-9">
          {featuredSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}
          {bestSellerSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}
        </div>

        {arrivalSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}

        <HomeCategoryShelves groups={categoryProductGroups} />

    
          
          <HomeSellerSpotlight products={homeProducts} />
      

        {otherProductSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}
        <HomeBrandSection brands={brands} title={brandSections[0]?.title || 'Featured Brands'} />

        {!hasProductSections && fallbackGroups.map(({ categoryName, products }, index) => (
          <HomeFallbackCategorySection key={categoryName} categoryName={categoryName} products={products} isFirst={index === 0} />
        ))}
         <RecentlyViewed />
        <HomeQuickSearches terms={[
          ...homeCategories.map((category) => category.name),
          ...brandSections.flatMap((section) => (section.items as { name: string }[]).map((brand) => brand.name)),
          ...homeProducts.map((product) => product.brand?.name ?? ''),
        ]} />
      </main>
      <Footer />
    </div>
  )
}
