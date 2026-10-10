import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import { toStoreProduct } from '@/lib/catalog-adapters'
import ProductCard from '@/components/productCard/ProductCard'

export default async function CategoryBestSellers({ category }: { category: string }) {
  const locale = await getLocale()
  const response = await createEldokanApi(locale).products.bestSelling(category, { perPage: 5 }).catch(() => null)
  if (!response?.data.length) return null
    return <section className="my-8 space-y-4" aria-labelledby="category-best-sellers">
      <div><p className="text-xs text-shop-accent">{locale === 'ar' ? 'اختيارات المشترين في القسم ده' : 'Customer choices in this category'}</p><h2 id="category-best-sellers" className="mt-1 text-xl font-bold">{locale === 'ar' ? 'الأكثر مبيعًا في القسم' : 'Best sellers in this category'}</h2></div>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">{response.data.map(product => <ProductCard key={product.id} product={toStoreProduct(product)} />)}</div>
    </section>
}
