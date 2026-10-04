import Slider from '@/components/slider/Slider'
import type { HomePromoBanner } from '@/types/home'

export default function BannerGrid({ banners, title }: { banners: HomePromoBanner[]; title?: string }) {
  if (banners.length === 0) return null

  return <Slider slides={banners} eyebrow={title?.trim() || 'SPECIAL OFFERS'} />
}
