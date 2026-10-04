'use client'
import { useEffect, useState } from 'react'
import {
  Carousel,
  type CarouselApi,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from '../ui/carousel'
import Image from 'next/image'
import { useLocale } from '@/components/i18n/LocaleProvider'

type ProductImage = { url: string }
type ProductData = { images: ProductImage[] }

export default function ImageProduct({
  data,
  selectedIndex,
  onSelectIndex,
}: {
  data: ProductData
  selectedIndex: number
  onSelectIndex: (index: number) => void
}) {
  const { t } = useLocale()
  const [api, setApi] = useState<CarouselApi>()

  useEffect(() => {
    api?.scrollTo(selectedIndex)
  }, [api, selectedIndex])

  useEffect(() => {
    if (!api) return

    const updateSelectedIndex = () => onSelectIndex(api.selectedScrollSnap())
    api.on('select', updateSelectedIndex)
    return () => {
      api.off('select', updateSelectedIndex)
    }
  }, [api, onSelectIndex])

  return (
    <Carousel
      className="w-full"
      setApi={setApi}
    >
      <CarouselContent>
        {data.images.map((item, index) => (
          <CarouselItem key={`${item.url}-${index}`}>
            <div className="flex aspect-square items-center justify-center rounded-xl bg-gray-50 p-5 sm:p-10">
              <Image
                src={item.url}
                width={700}
                height={700}
                alt={`${t('Product image')} ${index + 1}`}
                className="h-full w-full object-contain"
              />
            </div>
          </CarouselItem>
        ))}
      </CarouselContent>
      {data.images.length > 1 && (
        <>
          <CarouselPrevious className="absolute left-2" />
          <CarouselNext className="absolute right-2" />
        </>
      )}
    </Carousel>
  )
}
