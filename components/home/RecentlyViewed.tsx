'use client'

import { useEffect, useState } from 'react'
import ProductCard, { type StoreProduct } from '@/components/productCard/ProductCard'
import T from '@/components/i18n/T'

const STORAGE_KEY = 'eldokan.recently-viewed.v1'
const MAX_RECENT_PRODUCTS = 8

function isStoreProduct(value: unknown): value is StoreProduct {
  return Boolean(value && typeof value === 'object' && 'id' in value && 'name' in value)
}

export function ProductViewTracker({ product }: { product: StoreProduct }) {
  useEffect(() => {
    if (product.id == null || !product.name) return

    const entry: StoreProduct = {
      id: product.id,
      name: product.name,
      title: product.title,
      sku: product.sku,
      brand: product.brand,
      image: product.image,
      images: product.images,
      seller: product.seller,
      stock: product.stock,
      average_rating: product.average_rating,
      rating_count: product.rating_count,
      pricing: product.pricing,
    }

    try {
      const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]')
      const previous: StoreProduct[] = Array.isArray(saved) ? saved.filter(isStoreProduct) : []
      const next = [entry, ...previous.filter((item) => String(item.id) !== String(entry.id))].slice(0, MAX_RECENT_PRODUCTS)
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    } catch {
      // Keep product browsing available if browser storage is disabled.
    }
  }, [product])

  return null
}

export default function RecentlyViewed() {
  const [products, setProducts] = useState<StoreProduct[]>([])
  useEffect(() => {
    queueMicrotask(() => {
      try {
        const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]')
        if (Array.isArray(saved)) setProducts(saved.filter(isStoreProduct))
      } catch { setProducts([]) }
    })
  }, [])
  if (products.length === 0) return null
  return <section className="space-y-4" aria-labelledby="recently-viewed-title"><div><p className="text-xs font-semibold uppercase tracking-[0.16em] text-shop-accent"><T text="Pick up where you left off" /></p><h2 id="recently-viewed-title" className="mt-1 text-xl font-bold text-foreground sm:text-2xl"><T text="Recently viewed" /></h2></div><div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">{products.map(product => <ProductCard key={product.id} product={product} />)}</div></section>
}