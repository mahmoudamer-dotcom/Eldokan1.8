import { Suspense, cache } from 'react'
import HomeBrandSection from '@/components/home/HomeBrandSection'
import HomeCategoryShelves from '@/components/home/HomeCategoryShelves'
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
import { fetchStoreStories } from '@/services/stories'
import { fetchUsers } from '@/services/category'
import { Products } from '@/services/product'
import type { HomeBrand, HomeCategory, HomeHeroSlide, HomeProduct, HomePromoBanner, HomeSection } from '@/types/home'
import { mapLimited } from '@/lib/map-limited'

function isInStock(product: { stock?: { status?: string } }) {
  return product.stock?.status === 'in_stock'
}

export default async function Home() {
  const managedStories = fetchStoreStories()
  const response = await HomeApi()
  const sections: HomeSection[] = Array.isArray(response?.data?.sections)
    ? response.data.sections.filter((section: HomeSection) => section.enabled).sort((a: HomeSection, b: HomeSection) => a.order - b.order)
    : []

  const hasProductSections = sections.some((section) => section.type === 'product_carousel')
  const firstProductSectionId = sections.find((section) => section.type === 'product_carousel')?.id
  const categorySection = sections.find((section) => section.type === 'category_grid')
  const homeCategories = (categorySection?.items ?? []) as HomeCategory[]
  const isProductSection = (section: HomeSection) => section.type === 'product_carousel'
  const matchesSection = (section: HomeSection, terms: string[]) => {
    const label = `${section.id} ${section.title ?? ''}`.toLocaleLowerCase()
    return terms.some((term) => label.includes(term))
  }
  const heroSections = sections.filter((section) => section.type === 'hero_slider')
  const promoSections = sections.filter((section) => section.type === 'banner_grid')
  const dealSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['deal', 'offer', 'discount']))
  const featuredSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['featured']) && !dealSections.includes(section))
  const bestSellerSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['best']) && !dealSections.includes(section) && !featuredSections.includes(section))
  const arrivalSections = sections.filter((section) => isProductSection(section) && matchesSection(section, ['new', 'arrival']) && !dealSections.includes(section) && !featuredSections.includes(section) && !bestSellerSections.includes(section))
  const placedProductIds = new Set([...dealSections, ...featuredSections, ...bestSellerSections, ...arrivalSections].map((section) => section.id))
  const otherProductSections = sections.filter((section) => isProductSection(section) && !placedProductIds.has(section.id))
  const brandSections = sections.filter((section) => section.type === 'brand_grid')
  const fallbackBrands = brandSections.flatMap((section) => section.items as HomeBrand[])
  const homeProducts = [
    ...sections.filter(isProductSection).flatMap((section) => (section.items as HomeProduct[]).filter(isInStock)),
  ]
  const categoryProductGroups = mapLimited(homeCategories, 4, loadCategoryProducts)
  const catalogProducts = categoryProductGroups.then(groups => [...new Map([...homeProducts, ...groups.flatMap(group => group.products)].map(product => [String(product.id), product])).values()])
  return (
    <div className="flex min-h-screen w-full flex-col">
      <main className="container mx-auto w-full flex-1 space-y-10 px-4 pb-14 pt-5 sm:space-y-12 sm:pt-7">
        <Suspense fallback={<div className="h-24 animate-pulse rounded-2xl bg-muted" aria-hidden="true" />}><CatalogStories products={catalogProducts} managedStories={managedStories} /></Suspense>
        {heroSections.length > 0
          ? heroSections.map((section) => <Slider key={section.id} slides={section.items as HomeHeroSlide[]} />)
          : <Slider />}

        <Suspense fallback={<ShelfPlaceholder />}><CatalogOffers sections={dealSections} products={catalogProducts} /></Suspense>

       

        {promoSections.map((section) => (
          <BannerGrid key={section.id} banners={section.items as HomePromoBanner[]} title={section.title} />
        ))}

        {(featuredSections.length > 0 || bestSellerSections.length > 0) && <div className="space-y-10">
          {featuredSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}
          {bestSellerSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}
        </div>}

        {arrivalSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}

        <Suspense fallback={<ShelfPlaceholder />}><CategoryShelves groups={categoryProductGroups} /></Suspense>

    
          
          <Suspense fallback={<ShelfPlaceholder />}><CatalogSellers products={catalogProducts} /></Suspense>
      

        {otherProductSections.map((section) => <HomeProductCarousel key={section.id} section={section} firstProductSectionId={firstProductSectionId} />)}
        <Suspense fallback={<ShelfPlaceholder />}><BrandShelf fallbackBrands={fallbackBrands} title={brandSections[0]?.title || 'Featured Brands'} /></Suspense>

        {!hasProductSections && homeCategories.length === 0 && <Suspense fallback={<ShelfPlaceholder />}><FallbackShelves /></Suspense>}
         <RecentlyViewed />
        <HomeQuickSearches terms={[
          ...homeCategories.map((category) => category.name),
          ...brandSections.flatMap((section) => (section.items as { name: string }[]).map((brand) => brand.name)),
          ...homeProducts.map((product) => product.brand?.name ?? ''),
        ]} />
      </main>
    </div>
  )
}

function ShelfPlaceholder() {
  return <div aria-hidden="true" className="space-y-4"><div className="h-6 w-40 animate-pulse rounded bg-muted" /><div className="grid grid-cols-2 gap-3 sm:grid-cols-4">{Array.from({ length: 4 }, (_, index) => <div key={index} className="h-64 animate-pulse rounded-2xl bg-muted" />)}</div></div>
}
async function BrandShelf({ fallbackBrands, title }: { fallbackBrands: HomeBrand[]; title: string }) {
  const response = await Brands()
  return <HomeBrandSection brands={Array.isArray(response) && response.length ? response as HomeBrand[] : fallbackBrands} title={title} />
}
async function FallbackShelves() {
  const response = await fetchUsers()
  const groups = await mapLimited(Array.isArray(response.data) ? response.data : [], 4, async (category) => {
    const products = await Products(category.slug ?? category.name)
    return { categoryName: category.name, products: (Array.isArray(products.data) ? products.data : []).filter(isInStock) }
  })
  return groups.map(({ categoryName, products }, index) => <HomeFallbackCategorySection key={categoryName} categoryName={categoryName} products={products} isFirst={index === 0} />)
}

const loadCategoryProducts = cache(async (category: HomeCategory): Promise<HomeCategoryProductGroup> => {
  const response = await Products(category.slug ?? category.name)
  return { category, products: (Array.isArray(response.data) ? response.data : []).filter(isInStock) }
})
async function CategoryShelves({ groups }: { groups: Promise<HomeCategoryProductGroup[]> }) {
  return <HomeCategoryShelves groups={await groups} />
}
async function CatalogStories({ products, managedStories }: { products: Promise<HomeProduct[]>; managedStories: ReturnType<typeof fetchStoreStories> }) {
  const stories = await managedStories
  return <Story products={stories === null ? await products : []} managedStories={stories} />
}
async function CatalogOffers({ products, sections }: { products: Promise<HomeProduct[]>; sections: HomeSection[] }) {
  return <HomeDailyOffers sections={sections} products={await products} />
}
async function CatalogSellers({ products }: { products: Promise<HomeProduct[]> }) {
  return <HomeSellerSpotlight products={await products} />
}
