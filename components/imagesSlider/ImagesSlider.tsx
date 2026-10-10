'use client'

import Image from 'next/image'
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel'
import { useLocale } from '@/components/i18n/LocaleProvider'

type ProductImage = { url: string }

export default function ImagesSlider({
  data,
  selectedIndex,
  onSelectIndex,
}: {
  data: { images: ProductImage[] }
  selectedIndex: number
  onSelectIndex: (index: number) => void
}) {
  const { t } = useLocale()
  return (
    <Carousel opts={{ align: 'start', active: data.images.length > 1 }} className="mx-auto mt-3 w-full px-2">
      <CarouselContent className="-ms-2">
        {data.images.map((item, index) => (
          <CarouselItem key={`${item.url}-${index}`} className="basis-1/4 ps-2 sm:basis-1/5">
            <button
              type="button"
              onClick={() => onSelectIndex(index)}
              aria-label={`${t('Show product image')} ${index + 1}`}
              aria-pressed={selectedIndex === index}
              className={`w-full overflow-hidden product-photo-canvas rounded-lg border p-1 transition ${selectedIndex === index ? 'border-[#d99500] ring-2 ring-[#f5b400]/40' : 'border-border hover:border-gray-400'}`}
            >
              <Image src={item.url} width={160} height={160} sizes="(max-width: 640px) 22vw, (max-width: 1024px) 18vw, 9vw" alt={`${t('Product view')} ${index + 1}`} className="aspect-square w-full rounded-lg object-contain" />
            </button>
          </CarouselItem>
        ))}
      </CarouselContent>
      {data.images.length > 5 && <><CarouselPrevious /><CarouselNext /></>}
    </Carousel>
  )
}
