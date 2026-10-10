"use client";
import T from '@/components/i18n/T'
import { useState } from "react";
import ImagesSlider from "../imagesSlider/ImagesSlider";
import ImageProduct from "../imagesProduct/ImageProduct";
import type { ProductImageData } from "@/types/product";

export default function ParentImage({ data }: { data: { images: ProductImageData[] } }) {
  const [selectedIndex, setSelectedIndex] = useState(0);

  if (!data.images?.length) {
    return (
      <div className="flex aspect-square items-center justify-center rounded-xl bg-background text-sm text-muted-foreground">
        <T text="Product images unavailable" />
      </div>
    );
  }

  return (
    <>
      <ImageProduct
        data={data}
        selectedIndex={selectedIndex}
        onSelectIndex={setSelectedIndex}/>
      <ImagesSlider
        data={data}
        selectedIndex={selectedIndex}
        onSelectIndex={setSelectedIndex}/>
        
    </>
  );
}
