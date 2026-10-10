import { Products } from '@/services/product'
import { Suspense } from 'react'
import CategoryBestSellers from '@/components/category/CategoryBestSellers'
import { fetchCategoryBySlug, fetchCategoryFilters, fetchCategoriesWithChildren } from '@/services/category'
import T from '@/components/i18n/T'
import type { StoreProduct } from '@/components/productCard/ProductCard'
import CategoryProducts from '@/components/category/CategoryProducts'
import CategoryExperience from '@/components/category/CategoryExperience'
import { categoryWorld } from '@/lib/category-experience'
import { getLocale } from '@/lib/server-locale'
import type { Metadata } from 'next'
import { getStorefrontUrl } from '@/lib/site-url'

export async function generateMetadata({ params }: { params: Promise<{ name: string }> }): Promise<Metadata> {
  const { name } = await params
  const [category, locale] = await Promise.all([fetchCategoryBySlug(decodeURIComponent(name)), getLocale()])
  const title = category?.data.name ?? name
  const origin = getStorefrontUrl()
  return { title: `${title} | ${locale === 'ar' ? 'الدكان' : 'Eldokan'}`, description: locale === 'ar' ? `تسوق ${title} وقارن المنتجات والأسعار في الدكان.` : `Shop ${title} and compare products and prices at Eldokan.`, alternates: origin ? { canonical: new URL(`/category/${encodeURIComponent(name)}`, origin).toString() } : undefined }
}

export default async function CategoryPage({
  params,
}: {
  params: Promise<{ name: string }>
}) {
  const { name } = await params
  const categorySlug = decodeURIComponent(name)
  const locale = await getLocale()
  const [response, categoryResponse, filterResponse, treeResponse] = await Promise.all([
    Products(categorySlug, { stockStatus: 'in_stock' }),
    fetchCategoryBySlug(categorySlug),
    fetchCategoryFilters(categorySlug),
    fetchCategoriesWithChildren(),
  ])
  if (response.unavailable) throw new Error('Category catalog temporarily unavailable')
  const categoryName = categoryResponse?.data.name ?? categorySlug
  const products = Array.isArray(response?.data) ? response.data : []
  const availableProducts = products.filter((product: StoreProduct) => product.stock?.status === 'in_stock')
  const origin = getStorefrontUrl()
  const crumbs = origin ? { '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: [{ '@type': 'ListItem', position: 1, name: locale === 'ar' ? 'الدكان' : 'Eldokan', item: origin.toString() }, { '@type': 'ListItem', position: 2, name: categoryName, item: new URL(`/category/${encodeURIComponent(categorySlug)}`, origin).toString() }] } : null

  return (
    <>
      <main className="container mx-auto  px-4 pb-12">
        {crumbs && <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(crumbs).replace(/</g, '\\u003c') }} />}
        <section className="py-5 sm:py-8">
          <CategoryExperience world={categoryWorld(categorySlug, categoryResponse?.data, treeResponse.data)} category={categoryResponse?.data} name={categoryName} slug={categorySlug} products={availableProducts} locale={locale} />
          <div id="category-products" className="scroll-mt-48">
          <Suspense fallback={null}><CategoryBestSellers category={categorySlug} /></Suspense>
          {availableProducts.length > 0 ? (
            <CategoryProducts
              key={categorySlug}
              category={categorySlug}
              products={availableProducts}
              initialTotalPages={response?.meta?.total_pages ?? 1}
              filters={filterResponse?.data.attributes ?? []}
            />
          ) : (
            <div className="mt-6 rounded-2xl border border-dashed border-input bg-card px-6 py-16 text-center">
              <h2 className="text-lg font-semibold text-foreground"><T text="No products available yet" /></h2>
              <p className="mt-2 text-sm text-muted-foreground"><T text="Check back soon for new finds in this category." /></p>
            </div>
          )}
          </div>
        </section>
      </main>
    </>
  )
}
