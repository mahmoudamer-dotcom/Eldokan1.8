import HomeProductCarousel from '@/components/home/HomeProductCarousel'
import type { HomeProduct, HomeSection } from '@/types/home'

export default function HomeDailyOffers({
  sections,
  products,
}: {
  sections: HomeSection[]
  products: HomeProduct[]
}) {
  const populatedSections = sections.filter((section) =>
    (section.items as HomeProduct[]).some((product) => product.stock?.status === 'in_stock'),
  )

  if (populatedSections.length > 0) {
    return <>{populatedSections.map((section) => <HomeProductCarousel key={section.id} section={section} eyebrow="Today's offers" />)}</>
  }

  const saleProducts = products.filter((product) =>
    product.stock?.status === 'in_stock' && product.pricing?.on_sale && Boolean(product.pricing.sale_price?.formatted || product.pricing.sale_price?.amount !== undefined),
  )
  if (saleProducts.length === 0) return null

  const offersSection: HomeSection = {
    id: 'todays-api-offers',
    type: 'product_carousel',
    enabled: true,
    order: 0,
    title: "Today's offers",
    items: saleProducts,
  }

  return <HomeProductCarousel section={offersSection} eyebrow="Limited-time offers" />
}
