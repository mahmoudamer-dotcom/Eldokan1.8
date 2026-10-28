import { ArrowUpRight, Banknote, ChevronDown, CreditCard, Headphones, Mail, MessageCircle, Phone, RotateCcw, Store, Truck } from 'lucide-react'
import Image from 'next/image'
import Link from 'next/link'
import { fetchCategoriesWithChildren } from '@/services/category'
import { PopularBrands } from '@/services/brand'
import { getLocale } from '@/lib/server-locale'
import { translateCatalogName } from '@/lib/i18n'
import { BUSINESS, POLICIES } from '@/lib/legal-policies'
import BackToTop from './BackToTop'

type FooterCategory = { id?: string | number; name: string; slug?: string; parent_id?: string | number | null; children?: FooterCategory[] }
function categoryHref(category: FooterCategory) { return `/category/${category.slug || encodeURIComponent(category.name)}` }

export default async function Footer() {
  const locale = await getLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const [response, brandData] = await Promise.all([fetchCategoriesWithChildren(), PopularBrands()])
  const categories: FooterCategory[] = Array.isArray(response?.data) ? response.data : []
  const roots = categories.filter(category => !category.parent_id || !categories.some(parent => String(parent.id) === String(category.parent_id)))
  const policyLink = (slug: string) => ({ href: `/policies/${slug}`, label: POLICIES.find(p => p.slug === slug)?.title[locale] ?? slug })
  const phone = BUSINESS.phone.replace(/^\+20/, '0')

  const columns = [
    { title: text('Shop', 'تسوّق'), links: [
      { href: '/used-like-new', label: text('Used like new', 'مستعمل كالجديد') },
      { href: '/wishlist', label: text('Favorites', 'المفضلة') },
      { href: '/compare', label: text('Compare products', 'مقارنة المنتجات') },
      { href: '/cart', label: text('Shopping cart', 'سلة التسوق') },
    ] },
    { title: text('Customer care', 'خدمة العملاء'), links: [
      { href: '/help', label: text('Help center', 'مركز المساعدة') },
      { href: '/orders', label: text('Track my orders', 'تابع طلباتي') },
      policyLink('shipping'), policyLink('returns'), policyLink('buyer-terms'),
    ] },
    { title: text('Policies', 'السياسات'), links: [policyLink('terms'), policyLink('privacy'), policyLink('cookies'), { href: '/policies', label: text('All policies', 'كل السياسات') }] },
    { title: text('Sell on Eldokan', 'بيع مع الدكان'), links: POLICIES.filter(p => p.audience === 'seller').map(p => policyLink(p.slug)), sell: true },
  ]
  // Only promises the policies make: returns within 14 days, and the delivery and payment options checkout offers.
  const perks = [
    { icon: Truck, title: text('Door delivery', 'توصيل لباب البيت'), body: text('Or pick up from a branch', 'أو استلم من أقرب فرع') },
    { icon: Banknote, title: text('Cash on delivery', 'الدفع عند الاستلام'), body: text('Pay when your order arrives', 'ادفع لما طلبك يوصل') },
    { icon: CreditCard, title: text('Cards & installments', 'كارت وتقسيط'), body: text('Secure payment through Paymob', 'دفع آمن عن طريق Paymob') },
    { icon: RotateCcw, title: text('14-day returns', 'إرجاع خلال 14 يوم'), body: text('See the returns policy', 'اعرف سياسة الإرجاع'), href: '/policies/returns' },
  ]
  const contact = [
    { href: '/help', icon: Headphones, label: text('Help center', 'مركز المساعدة'), value: text('Answers and support', 'إجابات ودعم') },
    { href: `https://wa.me/${BUSINESS.phone.replace('+', '')}`, icon: MessageCircle, label: text('WhatsApp', 'واتساب'), value: phone, external: true },
    { href: `tel:${BUSINESS.phone}`, icon: Phone, label: text('Call us', 'اتصل بينا'), value: phone },
    { href: `mailto:${BUSINESS.email}`, icon: Mail, label: text('Email', 'البريد'), value: BUSINESS.email },
  ]
  const link = 'inline-flex py-1.5 text-sm text-white/70 transition-colors hover:text-[#f5b400] focus-visible:outline-white'
  const chip = 'rounded-full border border-white/10 bg-white/[0.04] px-3.5 py-1.5 text-xs font-medium text-white/75 transition hover:border-[#f5b400]/60 hover:text-white'

  return <footer dir={ar ? 'rtl' : 'ltr'} className="relative mt-auto w-full shrink-0 overflow-hidden bg-[#050505] text-white">
    {/* Gold hairline and a soft glow mark where the store ends and the footer begins. */}
    <div aria-hidden="true" className="h-px bg-gradient-to-r from-transparent via-[#f5b400]/70 to-transparent" />
    <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_at_top,rgba(245,180,0,0.12),transparent_65%)]" />

    <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

      <ul aria-label={text('Why shop with us', 'ليه تتسوق معانا')} className="mt-10 grid grid-cols-2 gap-3 lg:grid-cols-4">
        {perks.map(({ icon: Icon, title, body, href }) => {
          const content = <><span className="grid size-9 shrink-0 place-items-center rounded-xl bg-[#f5b400]/12 sm:size-11 sm:rounded-2xl text-[#f5b400] ring-1 ring-[#f5b400]/25"><Icon aria-hidden="true" className="size-5" /></span><span className="min-w-0"><span className="block text-sm font-bold">{title}</span><span className="mt-0.5 block text-xs leading-5 text-white/55">{body}</span></span></>
          const box = 'flex h-full items-center gap-2.5 rounded-2xl border border-white/[0.08] bg-white/[0.03] p-3 sm:gap-3 sm:p-4'
          return <li key={title}>{href ? <Link href={href} className={`${box} transition hover:border-[#f5b400]/40`}>{content}</Link> : <div className={box}>{content}</div>}</li>
        })}
      </ul>

      <div className="grid gap-10 py-12 lg:grid-cols-[1.2fr_2.8fr] lg:gap-14">
        <section aria-label={text('Contact Eldokan', 'تواصل مع الدكان')}>
          <Link href="/" aria-label={text('Eldokan home', 'الصفحة الرئيسية للدكان')} className="brand-logo-canvas inline-flex rounded-2xl px-3 py-2"><Image src="/image/Eldokan-logo.webp" width={120} height={60} className="h-12 w-auto object-contain" alt={BUSINESS.name[locale]} /></Link>
          <p className="mt-4 max-w-sm text-sm leading-7 text-white/65">{text('Everyday essentials and new finds, delivered to your door.', 'احتياجاتك اليومية وحاجات جديدة تكتشفها، لحد باب البيت.')}</p>
          <ul className="mt-5 grid grid-cols-2 gap-2.5">
            {contact.map(({ href, icon: Icon, label, value, external }) => <li key={label}>
              <a href={href} {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})} className="flex h-full items-center gap-2.5 rounded-2xl border border-white/[0.08] bg-white/[0.03] p-3 transition hover:border-[#f5b400]/40 hover:bg-white/[0.06]">
                <Icon aria-hidden="true" className="size-5 shrink-0 text-[#f5b400]" />
                <span className="min-w-0"><span className="block text-[11px] text-white/50">{label}</span><span dir={href === '/help' ? undefined : 'ltr'} className="block truncate text-sm font-semibold">{value}</span></span>
              </a>
            </li>)}
          </ul>
        </section>

        <div className="grid gap-x-8 sm:grid-cols-2 lg:grid-cols-4">
          {columns.map((column) => {
            const list = <>
              <ul className="pb-4 sm:pb-0">
                {column.links.map(item => <li key={item.href}><Link href={item.href} className={link}>{item.label}</Link></li>)}
              </ul>
              {column.sell && <a href="https://sell.eldokan.com/login" target="_blank" rel="noopener noreferrer" className="mb-5 mt-3 inline-flex min-h-10 items-center gap-2 rounded-full border border-[#f5b400]/60 px-4 text-sm font-bold text-[#f5b400] transition hover:bg-[#f5b400] hover:text-[#111]"><Store aria-hidden="true" className="size-4" />{text('Start selling', 'ابدأ البيع')}<ArrowUpRight aria-hidden="true" className="size-4 rtl:-rotate-90" /></a>}
            </>
            const heading = <span className="flex items-center gap-2"><span aria-hidden="true" className="h-4 w-1 rounded-full bg-[#f5b400]" />{column.title}</span>
            return <div key={column.title}>
              {/* Phones: a collapsed list that opens on tap, so the footer stays short. */}
              <details className="group border-b border-white/10 sm:hidden">
                <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between text-sm font-bold [&::-webkit-details-marker]:hidden">
                  {heading}<ChevronDown aria-hidden="true" className="size-4 text-white/60 transition group-open:rotate-180" />
                </summary>
                {list}
              </details>
              <nav aria-label={column.title} className="hidden sm:block"><h2 className="mb-4 text-sm font-bold">{heading}</h2>{list}</nav>
            </div>
          })}
        </div>
      </div>

      {(roots.length > 0 || brandData.brands.length > 0) && <div className="space-y-8 border-t border-white/10 py-8">
        {roots.length > 0 && <nav aria-label={text('Departments', 'الأقسام')}>
          <h2 className="mb-5 flex items-center gap-2 text-sm font-bold"><span aria-hidden="true" className="h-4 w-1 rounded-full bg-[#f5b400]" />{text('Departments', 'الأقسام')}</h2>
          {/* Every department with its subcategories underneath (departments with subcategories first). */}
          <div className="grid grid-cols-2 gap-x-6 gap-y-7 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
            {[...roots].sort((a, b) => Number(Boolean(b.children?.length)) - Number(Boolean(a.children?.length))).map(category => {
              const children = category.children ?? []
              return <div key={category.id ?? category.slug ?? category.name} className="min-w-0">
                <Link href={categoryHref(category)} className="text-sm font-bold text-white transition hover:text-[#f5b400]">{translateCatalogName(category.name, locale)}</Link>
                {children.length > 0 && <ul className="mt-2 space-y-1">
                  {children.slice(0, 6).map(child => <li key={child.id ?? child.slug ?? child.name}><Link href={categoryHref(child)} className="inline-flex py-0.5 text-[13px] leading-5 text-white/60 transition hover:text-[#f5b400]">{translateCatalogName(child.name, locale)}</Link></li>)}
                  {children.length > 6 && <li><Link href={categoryHref(category)} className="inline-flex py-0.5 text-[13px] font-semibold text-[#f5b400] hover:underline">{text('View all', 'عرض الكل')}</Link></li>}
                </ul>}
              </div>
            })}
          </div>
        </nav>}
        {brandData.brands.length > 0 && <nav aria-label={text('Brands', 'العلامات التجارية')} className="flex flex-wrap items-center gap-2"><h2 className="me-2 text-sm font-bold text-white/90">{brandData.source === 'best-sellers' ? text('Popular brands', 'علامات رائجة') : text('Featured brands', 'علامات مميزة')}</h2>
          {brandData.brands.map(brand => <Link key={brand.id} href={`/brand/${encodeURIComponent(brand.slug || brand.id)}`} className={chip}>{brand.name}</Link>)}
        </nav>}
      </div>}
    </div>

    <div className="relative border-t border-white/10 bg-black">
      {/* Bottom room: the floating "Ask Eldokan" button sits over this corner. */}
      <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 pb-24 pt-5 text-xs text-white/55 sm:px-6 md:pb-5 lg:flex-row lg:items-center lg:justify-between lg:pe-52 lg:ps-8">
        <p>© {new Date().getFullYear()} {BUSINESS.name[locale]}. {text('All rights reserved.', 'جميع الحقوق محفوظة.')}</p>
        <ul aria-label={text('Payment methods', 'وسائل الدفع')} className="flex flex-wrap items-center gap-2">
          {[text('Cash', 'كاش'), 'Visa', 'Mastercard', text('Meeza', 'ميزة'), text('Installments', 'تقسيط')].map(method => <li key={method} className="rounded-md border border-white/15 bg-white/[0.06] px-2.5 py-1 text-[11px] font-bold text-white/85">{method}</li>)}
        </ul>
        <BackToTop label={text('Back to top', 'لأعلى الصفحة')} />
      </div>
    </div>
  </footer>
}
