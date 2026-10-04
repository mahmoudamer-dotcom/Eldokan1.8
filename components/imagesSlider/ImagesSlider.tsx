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
    <Carousel opts={{ align: 'start' }} className="mx-auto mt-3 w-full px-2">
      <CarouselContent className="-ml-2">
        {data.images.map((item, index) => (
          <CarouselItem key={`${item.url}-${index}`} className="basis-1/4 pl-2 sm:basis-1/5">
            <button
              type="button"
              onClick={() => onSelectIndex(index)}
              aria-label={`${t('Show product image')} ${index + 1}`}
              aria-pressed={selectedIndex === index}
              className={`w-full overflow-hidden rounded-lg border bg-white p-1 transition ${selectedIndex === index ? 'border-[#d99500] ring-2 ring-[#f5b400]/40' : 'border-gray-200 hover:border-gray-400'}`}
            >
              <Image src={item.url} width={160} height={160} sizes="80px" alt={`${t('Product view')} ${index + 1}`} className="aspect-square w-full object-contain" />
            </button>
          </CarouselItem>
        ))}
      </CarouselContent>
      {data.images.length > 5 && <><CarouselPrevious /><CarouselNext /></>}
    </Carousel>
  )
}
