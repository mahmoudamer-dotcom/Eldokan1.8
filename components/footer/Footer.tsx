import { ArrowUpRight, Banknote, ChevronDown, CreditCard, Headphones, Mail, MessageCircle, Phone, RotateCcw, Store, Truck } from 'lucide-react'
import Image from 'next/image'
import Link from 'next/link'
import { fetchCategoriesWithChildren } from '@/services/category'
import { PopularBrands } from '@/services/brand'
import { getLocale } from '@/lib/server-locale'
import { translateCatalogName } from '@/lib/i18n'
import { BUSINESS, POLICIES } from '@/lib/legal-policies'
import BackToTop from './BackToTop'

type FooterCategory = { id?: string | number; name: string; slug?: string; parent_id?: string | number | null }
function categoryHref(category: FooterCategory) { return `/category/${category.slug || encodeURIComponent(category.name)}` }

export default async function Footer() {
  const locale = await getLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const [response, brandData] = await Promise.all([fetchCategoriesWithChildren(), PopularBrands()])
  const categories: FooterCategory[] = Array.isArray(response?.data) ? response.data : []
  const roots = categories.filter(category => !category.parent_id || !categories.some(parent => String(parent.id) === String(category.parent_id)))
  const policy = (slug: string) => POLICIES.find(p => p.slug === slug)
  const policyLink = (slug: string) => ({ href: `/policies/${slug}`, label: policy(slug)?.title[locale] ?? slug })
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
    { href: '/help', icon: Headphones, label: text('Help center', 'مركز المساعدة'), value: text('Answers and support', 'إجابات ودعم'), external: false },
    { href: `https://wa.me/${BUSINESS.phone.replace('+', '')}`, icon: MessageCircle, label: text('WhatsApp', 'واتساب'), value: phone, external: true },
    { href: `tel:${BUSINESS.phone}`, icon: Phone, label: text('Call us', 'اتصل بينا'), value: phone, external: false },
    { href: `mailto:${BUSINESS.email}`, icon: Mail, label: text('Email', 'البريد'), value: BUSINESS.email, external: false },
  ]
  const link = 'inline-block py-1 text-sm text-white/75 transition-colors hover:text-[#f5b400] focus-visible:outline-white'

  return <footer dir={ar ? 'rtl' : 'ltr'} className="mt-auto w-full shrink-0 text-foreground">
    <section aria-label={text('Why shop with us', 'ليه تتسوق معانا')} className="border-t border-border bg-card">
      <ul className="mx-auto grid max-w-7xl grid-cols-2 gap-px bg-border px-0 lg:grid-cols-4">
        {perks.map(({ icon: Icon, title, body, href }) => {
          const content = <><span className="grid size-11 shrink-0 place-items-center rounded-full bg-shop-soft text-shop-accent"><Icon aria-hidden="true" className="size-5" /></span><span className="min-w-0"><span className="block text-sm font-bold">{title}</span><span className="mt-0.5 block text-xs text-muted-foreground">{body}</span></span></>
          return <li key={title} className="bg-card">{href
            ? <Link href={href} className="flex h-full items-center gap-3 px-4 py-5 transition hover:bg-muted sm:px-6">{content}</Link>
            : <div className="flex h-full items-center gap-3 px-4 py-5 sm:px-6">{content}</div>}</li>
        })}
      </ul>
    </section>

    <div className="bg-[#16181b] text-white">
      <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1.25fr_2.75fr] lg:gap-12 lg:px-8 lg:py-14">
        <section aria-label={text('Contact Eldokan', 'تواصل مع الدكان')}>
          <Link href="/" aria-label={text('Eldokan home', 'الصفحة الرئيسية للدكان')} className="brand-logo-canvas inline-flex rounded-xl px-3 py-2"><Image src="/image/Eldokan-logo.webp" width={120} height={60} className="h-12 w-auto object-contain" alt={BUSINESS.name[locale]} /></Link>
          <p className="mt-4 max-w-sm text-sm leading-7 text-white/70">{text('Everyday essentials and new finds, delivered to your door.', 'احتياجاتك اليومية وحاجات جديدة تكتشفها، لحد باب البيت.')}</p>
          <ul className="mt-5 grid grid-cols-2 gap-2.5">
            {contact.map(({ href, icon: Icon, label, value, external }) => <li key={label}>
              <a href={href} {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})} className="flex h-full items-center gap-2.5 rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/10 transition hover:bg-white/[0.12]">
                <Icon aria-hidden="true" className="size-5 shrink-0 text-[#f5b400]" />
                <span className="min-w-0"><span className="block text-[11px] text-white/60">{label}</span><span dir={href === '/help' ? undefined : 'ltr'} className="block truncate text-sm font-semibold">{value}</span></span>
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
              {column.sell && <a href="https://sell.eldokan.com/login" target="_blank" rel="noopener noreferrer" className="mb-5 mt-2 inline-flex min-h-10 items-center gap-2 rounded-full bg-[#f5b400] px-4 text-sm font-bold text-[#222] transition hover:bg-[#ffc933]"><Store aria-hidden="true" className="size-4" />{text('Start selling', 'ابدأ البيع')}<ArrowUpRight aria-hidden="true" className="size-4 rtl:-rotate-90" /></a>}
            </>
            return <div key={column.title}>
              {/* Phones: a collapsed list that opens on tap, so the footer stays short. */}
              <details className="group border-b border-white/10 sm:hidden">
                <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between text-sm font-bold [&::-webkit-details-marker]:hidden">
                  {column.title}<ChevronDown aria-hidden="true" className="size-4 transition group-open:rotate-180" />
                </summary>
                {list}
              </details>
              <nav aria-label={column.title} className="hidden sm:block"><h2 className="mb-3 text-sm font-bold">{column.title}</h2>{list}</nav>
            </div>
          })}
        </div>
      </div>

      {(roots.length > 0 || brandData.brands.length > 0) && <div className="mx-auto max-w-7xl space-y-4 border-t border-white/10 px-4 py-6 sm:px-6 lg:px-8">
        {roots.length > 0 && <nav aria-label={text('Departments', 'الأقسام')} className="flex flex-wrap items-center gap-2"><h2 className="me-2 text-sm font-bold">{text('Departments', 'الأقسام')}</h2>
          {roots.map(category => <Link key={category.id ?? category.slug ?? category.name} href={categoryHref(category)} className="rounded-full bg-white/[0.06] px-3 py-1.5 text-xs font-medium text-white/80 ring-1 ring-white/10 transition hover:bg-white/[0.14] hover:text-white">{translateCatalogName(category.name, locale)}</Link>)}
        </nav>}
        {brandData.brands.length > 0 && <nav aria-label={text('Brands', 'العلامات التجارية')} className="flex flex-wrap items-center gap-2"><h2 className="me-2 text-sm font-bold">{brandData.source === 'best-sellers' ? text('Popular brands', 'علامات رائجة') : text('Featured brands', 'علامات مميزة')}</h2>
          {brandData.brands.map(brand => <Link key={brand.id} href={`/brand/${encodeURIComponent(brand.slug || brand.id)}`} className="rounded-full bg-white/[0.06] px-3 py-1.5 text-xs font-medium text-white/80 ring-1 ring-white/10 transition hover:bg-white/[0.14] hover:text-white">{brand.name}</Link>)}
        </nav>}
      </div>}

      <div className="border-t border-white/10">
        {/* Bottom room: the floating "Ask Eldokan" button sits over this corner. */}
        <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 pb-24 pt-5 text-xs text-white/60 sm:px-6 md:pb-5 lg:flex-row lg:items-center lg:justify-between lg:pe-52 lg:ps-8">
          <p>© {new Date().getFullYear()} {BUSINESS.name[locale]}. {text('All rights reserved.', 'جميع الحقوق محفوظة.')}</p>
          <ul aria-label={text('Payment methods', 'وسائل الدفع')} className="flex flex-wrap items-center gap-2">
            {[text('Cash', 'كاش'), 'Visa', 'Mastercard', text('Meeza', 'ميزة'), text('Installments', 'تقسيط')].map(method => <li key={method} className="rounded-md bg-white px-2 py-1 text-[11px] font-bold text-[#16181b]">{method}</li>)}
          </ul>
          <BackToTop label={text('Back to top', 'لأعلى الصفحة')} />
        </div>
      </div>
    </div>
  </footer>
}
