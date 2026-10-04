import { ArrowUpRight, CreditCard, LifeBuoy, Phone } from 'lucide-react'
import Image from 'next/image'
import Link from 'next/link'
import { fetchCategoriesWithChildren } from '@/services/category'
import { PopularBrands } from '@/services/brand'
import T from '@/components/i18n/T'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'
import { translateCatalogName } from '@/lib/i18n'

type FooterCategory = {
  id?: string | number
  name: string
  slug?: string
  count?: number
  parent_id?: string | number | null
  children?: FooterCategory[]
  subcategories?: FooterCategory[]
}

function categoryHref(category: FooterCategory) {
  return `/category/${category.slug || encodeURIComponent(category.name)}`
}

export default async function Footer() {
  const locale = await getLocale()
  const [response, brandData] = await Promise.all([fetchCategoriesWithChildren(), PopularBrands()])
  const popularBrands = brandData.brands
  const categories: FooterCategory[] = Array.isArray(response?.data) ? response.data : []
  const roots = categories.filter((category) => {
    if (category.parent_id === undefined || category.parent_id === null || category.parent_id === '' || category.parent_id === 0) return true
    return !categories.some((parent) => String(parent.id) === String(category.parent_id))
  })

  function getChildren(parent: FooterCategory) {
    const explicitChildren = [...(parent.children ?? []), ...(parent.subcategories ?? [])]
    const linkedChildren = categories.filter((category) =>
      category.parent_id !== undefined &&
      category.parent_id !== null &&
      String(category.parent_id) === String(parent.id),
    )
    const unique = new Map<string, FooterCategory>()
    for (const child of [...explicitChildren, ...linkedChildren]) {
      unique.set(String(child.id ?? child.slug ?? child.name), child)
    }
    return [...unique.values()]
      .sort((a, b) => (b.count ?? 0) - (a.count ?? 0))
  }

  const categoryGroups = roots.map((category) => ({ category, children: getChildren(category) }))
  const categoriesWithChildren = categoryGroups.filter(({ children }) => children.length > 0)
  const categoriesWithoutChildren = categoryGroups.filter(({ children }) => children.length === 0)

  return (
    <div className='relative bottom-0'>
      <section className="border-y border-[#e8d39e] bg-[#fff8e7]">
        <div className="container mx-auto flex flex-col gap-5 px-4 py-6 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <span className="grid size-11 shrink-0 place-items-center rounded-full bg-white text-[#a66d00] shadow-sm">
              <LifeBuoy className="size-5" />
            </span>
            <div>
              <h2 className="font-semibold text-gray-900"><T text="We're here to help" /></h2>
              <p className="mt-1 text-sm text-gray-600"><T text="Contact our team whenever you need assistance." /></p>
            </div>
          </div>
          <div className="flex flex-wrap gap-3">
            <Link href="https://wa.me/201006806022" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 rounded-full bg-[#202124] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-700">
              <LifeBuoy className="size-4" /> <T text="WhatsApp support" />
            </Link>
            <a href="tel:+201006806022" className="inline-flex items-center gap-2 rounded-full border border-[#d9c58f] bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 transition hover:border-[#a66d00]">
              <Phone className="size-4 text-[#a66d00]" /> 01006806022
            </a>
          </div>
        </div>
      </section>

      <div dir={locale === 'ar' ? 'rtl' : 'ltr'} className="bg-[#202124] text-white">
        <div className="container mx-auto px-4 py-10 sm:py-12">
          <div className="grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(190px,1fr)]">
            <div>
              <div className="border-b border-white/10 pb-3">
                <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-white"><T text="Shop categories" /></h2>
                <p className="mt-1 text-xs text-gray-400"><T text="Explore our departments and collections." /></p>
              </div>
              {roots.length > 0 ? (
                <div className="mt-5 grid grid-cols-1 gap-x-6 gap-y-7 sm:grid-cols-2 xl:grid-cols-4 xl:gap-x-8">
                  {categoriesWithChildren.map(({ category, children }) => (
                    <section key={category.id ?? category.slug ?? category.name}>
                      <Link href={categoryHref(category)} className="inline-flex text-sm font-semibold leading-6 text-white transition hover:text-[#ffd45b]">
                        {translateCatalogName(category.name, locale)}
                      </Link>
                      <ul className="mt-2.5 space-y-2">
                        {children.slice(0, 6).map((child, childIndex) => (
                          <li key={child.id ?? child.slug ?? child.name ?? childIndex}>
                            <Link href={categoryHref(child)} className="inline-flex text-sm leading-5 text-gray-400 transition hover:translate-x-0.5 hover:text-[#ffd45b]">
                              {translateCatalogName(child.name, locale)}
                            </Link>
                          </li>
                        ))}
                      </ul>
                    </section>
                  ))}
                  <div className="space-y-6">
                    {categoriesWithoutChildren.map(({ category }) => (
                      <Link
                        key={category.id ?? category.slug ?? category.name}
                        href={categoryHref(category)}
                        className="block text-sm font-semibold leading-6 text-white transition hover:text-[#ffd45b]"
                      >
                        {translateCatalogName(category.name, locale)}
                      </Link>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="mt-4 text-sm text-gray-400"><T text="Categories are temporarily unavailable." /></p>
              )}
            </div>

            <div>
              <div className="border-b border-white/10 pb-3">
                <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-white">
                  <T text={brandData.source === 'best-sellers' ? 'Popular brands' : 'Featured brands'} />
                </h2>
                <p className="mt-1 text-xs text-gray-400">
                  <T text={brandData.source === 'best-sellers' ? 'Based on our best-selling products.' : 'Explore brands featured in our store.'} />
                </p>
              </div>
              {popularBrands.length > 0 ? (
                <ul className="mt-4 space-y-1.5">
                  {popularBrands.map((brand) => (
                    <li key={brand.id}>
                      <Link
                        href={`/brand/${encodeURIComponent(brand.slug || brand.id)}`}
                        className="group flex items-center rounded-lg px-2 py-2 text-sm text-gray-300 transition hover:bg-white/5 hover:text-[#ffd45b]"
                      >
                        {brand.name}
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mt-4 text-sm text-gray-400"><T text="Brands are temporarily unavailable." /></p>
              )}
            </div>
          </div>

          <div className="mt-10 grid gap-8 border-t border-white/15 pt-7 md:grid-cols-2 xl:grid-cols-[1.4fr_1.5fr_1fr] xl:items-center">
            <div className="flex items-center gap-4">
              <Link href="/" aria-label={translate('Eldokan home', locale)} className="inline-flex shrink-0 rounded-xl bg-white p-2">
                <Image src="/image/Eldokan-logo.webp" width={120} height={60} className="h-10 w-auto object-contain" alt="Eldokan" />
              </Link>
              <div>
                <p className="max-w-xs text-xs leading-5 text-gray-400"><T text="Discover everyday essentials and great finds at Eldokan." /></p>
                <Link href="https://sell.eldokan.com/login" target="_blank" rel="noopener noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-[#ffd45b] transition hover:text-white">
                  <T text="Sell with us" /> <ArrowUpRight className="size-3.5" />
                </Link>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-3" aria-label={translate('Accepted payment methods', locale)}>
              <CreditCard aria-hidden="true" className="size-4 text-[#ffd45b]" />
              <span className="text-xs text-gray-400"><T text="We accept" /></span>
              <span className="rounded-md border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-bold tracking-wide text-gray-200">VISA</span>
              <span className="rounded-md border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-bold tracking-wide text-gray-200">Mastercard</span>
              <span className="rounded-md border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-semibold text-gray-200"><T text="Installments" /></span>
            </div>

            <div className="text-xs leading-5 text-gray-400 xl:text-end">
              <p><T text="Copyright" /> {new Date().getFullYear()} Eldokan. {translate('All rights reserved.', locale)}</p>
              <p><T text="Made for everyday shopping." /></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
