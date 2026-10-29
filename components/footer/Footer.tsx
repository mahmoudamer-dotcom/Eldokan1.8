import { ArrowUpRight, BadgeCheck, Ban, Banknote, ChevronDown, Cookie, Copyright, CreditCard, FileText, GitCompareArrows, Headphones, Heart, LayoutGrid, Library, LifeBuoy, Lock, Mail, MessageCircle, PackageSearch, Phone, RotateCcw, ScrollText, ShieldCheck, ShoppingBag, ShoppingCart, Store, Tags, Truck, UserCheck, Wallet, type LucideIcon } from 'lucide-react'
import Image from 'next/image'
import Link from 'next/link'
import { fetchCategoriesWithChildren } from '@/services/category'
import { PopularBrands } from '@/services/brand'
import { getLocale } from '@/lib/server-locale'
import { translateCatalogName } from '@/lib/i18n'
import { BUSINESS, POLICIES } from '@/lib/legal-policies'
import BackToTop from './BackToTop'
import FooterWordmark from './FooterWordmark'
import { categoryIconFor } from '@/lib/category-icons'

type FooterCategory = { id?: string | number; name: string; slug?: string; parent_id?: string | number | null; children?: FooterCategory[] }
function categoryHref(category: FooterCategory) { return `/category/${category.slug || encodeURIComponent(category.name)}` }

/**
 * Black and white, like the logo: pure black ground, white type in a few brightness steps,
 * and the logo itself inverted so it sits on the black without a white box.
 */
export default async function Footer() {
  const locale = await getLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const [response, brandData] = await Promise.all([fetchCategoriesWithChildren(), PopularBrands()])
  const categories: FooterCategory[] = Array.isArray(response?.data) ? response.data : []
  const roots = categories.filter(category => !category.parent_id || !categories.some(parent => String(parent.id) === String(category.parent_id)))
  const withSubs = roots.filter(category => category.children?.length)
  const withoutSubs = roots.filter(category => !category.children?.length)
  const policyIcons: Record<string, LucideIcon> = { shipping: Truck, returns: RotateCcw, 'buyer-terms': FileText, terms: ScrollText, privacy: Lock, cookies: Cookie, 'seller-terms': UserCheck, 'prohibited-products': Ban }
  const policyLink = (slug: string) => ({ href: `/policies/${slug}`, label: POLICIES.find(p => p.slug === slug)?.title[locale] ?? slug, icon: policyIcons[slug] ?? FileText })
  const phone = BUSINESS.phone.replace(/^\+20/, '0')
  const name = (category: FooterCategory) => translateCatalogName(category.name, locale)

  const columns = [
    { title: text('Shop', 'تسوّق'), icon: ShoppingBag, links: [
      { href: '/used-like-new', label: text('Used like new', 'مستعمل كالجديد'), icon: BadgeCheck },
      { href: '/wishlist', label: text('Favorites', 'المفضلة'), icon: Heart },
      { href: '/compare', label: text('Compare products', 'مقارنة المنتجات'), icon: GitCompareArrows },
      { href: '/cart', label: text('Shopping cart', 'سلة التسوق'), icon: ShoppingCart },
    ] },
    { title: text('Customer care', 'خدمة العملاء'), icon: Headphones, links: [
      { href: '/help', label: text('Help center', 'مركز المساعدة'), icon: LifeBuoy },
      { href: '/orders', label: text('Track my orders', 'تابع طلباتي'), icon: PackageSearch },
      policyLink('shipping'), policyLink('returns'), policyLink('buyer-terms'),
    ] },
    { title: text('Policies', 'السياسات'), icon: ShieldCheck, links: [policyLink('terms'), policyLink('privacy'), policyLink('cookies'), { href: '/policies', label: text('All policies', 'كل السياسات'), icon: Library }] },
  ]
  // Only promises the policies make: returns within 14 days, and the delivery and payment options checkout offers.
  const perks = [
    { icon: Truck, title: text('Door delivery', 'توصيل لباب البيت'), body: text('Or pick up from a branch', 'أو استلم من أقرب فرع') },
    { icon: Banknote, title: text('Cash on delivery', 'الدفع عند الاستلام'), body: text('Pay when your order arrives', 'ادفع لما طلبك يوصل') },
    { icon: CreditCard, title: text('Cards & installments', 'كارت وتقسيط'), body: text('Secure payment via Paymob', 'دفع آمن عن طريق Paymob') },
    { icon: RotateCcw, title: text('14-day returns', 'إرجاع خلال 14 يوم'), body: text('See the returns policy', 'اعرف سياسة الإرجاع'), href: '/policies/returns' },
  ]
  const contact = [
    { href: `https://wa.me/${BUSINESS.phone.replace('+', '')}`, icon: MessageCircle, label: text('WhatsApp', 'واتساب'), value: phone, external: true },
    { href: `tel:${BUSINESS.phone}`, icon: Phone, label: text('Call us', 'اتصل بينا'), value: phone },
    { href: `mailto:${BUSINESS.email}`, icon: Mail, label: text('Email', 'البريد الإلكتروني'), value: BUSINESS.email },
    { href: '/help', icon: Headphones, label: text('Help center', 'مركز المساعدة'), value: text('Answers to common questions', 'إجابات أشهر الأسئلة'), local: true },
  ]
  const link = 'group/link inline-flex items-center gap-2.5 py-1.5 text-sm text-white/60 transition-colors hover:text-white focus-visible:outline-white'
  const linkIcon = 'size-4 shrink-0 text-white/35 transition group-hover/link:text-white'
  const heading = (title: string, Icon: LucideIcon) => <span className="flex items-center gap-2.5"><span className="grid size-7 place-items-center rounded-lg bg-white text-black"><Icon aria-hidden="true" className="size-4" /></span>{title}</span>

  return <footer dir={ar ? 'rtl' : 'ltr'} className="relative mt-auto w-full shrink-0 overflow-hidden border-t border-white/15 bg-black text-white">
    <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      {/* One band of shopping promises, divided rather than boxed. */}
      <ul aria-label={text('Why shop with us', 'ليه تتسوق معانا')} className="mt-10 grid grid-cols-2 overflow-hidden rounded-3xl border border-white/15 lg:grid-cols-4">
        {perks.map(({ icon: Icon, title, body, href }, index) => {
          const content = <><span className="grid size-9 shrink-0 place-items-center rounded-full border border-white/25 sm:size-12"><Icon aria-hidden="true" className="size-4.5 sm:size-5" /></span><span className="min-w-0"><span className="block text-[13px] font-bold leading-5 sm:text-[15px]">{title}</span><span className="mt-0.5 hidden text-xs leading-5 text-white/50 sm:block">{body}</span></span></>
          const cell = `flex h-full items-center gap-2.5 p-3 sm:gap-3 sm:p-6 ${index % 2 ? 'border-s border-white/15' : ''} ${index > 1 ? 'border-t border-white/15 lg:border-t-0' : ''} ${index === 2 ? 'lg:border-s' : ''}`
          return <li key={title}>{href ? <Link href={href} className={`${cell} transition hover:bg-white/[0.06]`}>{content}</Link> : <div className={cell}>{content}</div>}</li>
        })}
      </ul>

      <div className="grid gap-8 py-8 sm:gap-10 sm:py-12 lg:grid-cols-12 lg:gap-12 lg:py-16">
        <section aria-label={text('Contact Eldokan', 'تواصل مع الدكان')} className="text-center sm:text-start lg:col-span-4">
          {/* Inverted logo: its white ground turns black and blends into the footer. */}
          <Link href="/" aria-label={text('Eldokan home', 'الصفحة الرئيسية للدكان')} className="inline-flex"><Image src="/image/Eldokan-logo.webp" width={180} height={106} className="h-16 w-auto object-contain invert mix-blend-lighten sm:h-20" alt={BUSINESS.name[locale]} /></Link>
          <p className="mx-auto mt-3 max-w-sm text-sm leading-7 text-white/65 sm:mx-0 sm:mt-4 sm:text-[15px]">{text('Everyday essentials and new finds, delivered to your door.', 'احتياجاتك اليومية وحاجات جديدة تكتشفها، لحد باب البيت.')}</p>
          {/* Phones: a 2 x 2 grid of contact tiles. Larger screens: one bordered list. */}
          <ul className="mt-5 grid grid-cols-2 gap-2 text-start sm:mt-6 sm:block sm:divide-y sm:divide-white/10 sm:rounded-2xl sm:border sm:border-white/15">
            {contact.map(({ href, icon: Icon, label, value, external, local }) => <li key={label}>
              <a href={href} {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})} className="group flex h-full min-w-0 items-center gap-2.5 rounded-2xl border border-white/15 p-3 transition hover:bg-white/[0.06] sm:gap-3 sm:rounded-none sm:border-0 sm:px-4">
                <span className="grid size-9 shrink-0 place-items-center rounded-full border border-white/25 transition group-hover:bg-white group-hover:text-black"><Icon aria-hidden="true" className="size-4" /></span>
                <span className="min-w-0 flex-1"><span className="block text-[11px] text-white/45">{label}</span><span dir={local ? undefined : 'ltr'} className={`block text-xs font-semibold wrap-anywhere sm:truncate sm:text-sm ${ar && !local ? 'text-end' : ''}`}>{value}</span></span>
                <ArrowUpRight aria-hidden="true" className="hidden size-4 shrink-0 text-white/30 transition group-hover:text-white sm:block rtl:-rotate-90" />
              </a>
            </li>)}
          </ul>
        </section>

        <div className="lg:col-span-8">
          <div className="grid gap-x-8 sm:grid-cols-3">
            {columns.map((column) => {
              const list = <ul className="pb-4 sm:pb-0">{column.links.map(({ href, label, icon: Icon }) => <li key={href}><Link href={href} className={link}><Icon aria-hidden="true" className={linkIcon} />{label}</Link></li>)}</ul>
              return <div key={column.title}>
                {/* Phones: collapsed lists that open on tap keep the footer short. */}
                <details className="group border-b border-white/15 sm:hidden">
                  <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between text-sm font-bold [&::-webkit-details-marker]:hidden">{heading(column.title, column.icon)}<ChevronDown aria-hidden="true" className="size-4 text-white/50 transition group-open:rotate-180" /></summary>
                  {list}
                </details>
                <nav aria-label={column.title} className="hidden sm:block"><h2 className="mb-4 text-sm font-bold">{heading(column.title, column.icon)}</h2>{list}</nav>
              </div>
            })}
          </div>

          <section aria-label={text('Sell on Eldokan', 'بيع مع الدكان')} className="mt-8 flex flex-col gap-4 rounded-3xl bg-white p-5 text-black sm:mt-10 sm:gap-5 sm:p-6 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-4">
              <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-black text-white"><Store aria-hidden="true" className="size-6" /></span>
              <div>
                <h2 className="text-lg font-extrabold">{text('Have products to sell?', 'عندك منتجات عايز تبيعها؟')}</h2>
                <p className="mt-1 text-sm leading-6 text-black/60">{text('Reach Eldokan shoppers.', 'وصّلها لعملاء الدكان.')} {POLICIES.filter(p => p.audience === 'seller').map((p, i) => <span key={p.slug}>{i > 0 && ' · '}<Link href={`/policies/${p.slug}`} className="inline-flex items-center gap-1 underline decoration-black/30 underline-offset-4 hover:text-black"><PolicyIcon icon={policyIcons[p.slug] ?? FileText} />{p.title[locale]}</Link></span>)}</p>
              </div>
            </div>
            <a href="https://sell.eldokan.com/login" target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-black px-6 text-sm font-extrabold text-white transition hover:bg-black/80">{text('Start selling', 'ابدأ البيع')}<ArrowUpRight aria-hidden="true" className="size-4 rtl:-rotate-90" /></a>
          </section>
        </div>
      </div>

      {roots.length > 0 && <nav aria-label={text('Departments', 'الأقسام')} className="border-t border-white/15 py-8 sm:py-10">
        <h2 className="mb-3 text-sm font-bold sm:mb-6">{heading(text('Departments', 'الأقسام'), LayoutGrid)}</h2>
        {/* Phones: one collapsed row per department; tapping it shows its subcategories. */}
        <div className="sm:hidden">
          {withSubs.map(category => {
            const children = category.children ?? []
            return <details key={category.id ?? category.slug ?? category.name} className="group border-b border-white/10">
              <summary className="flex min-h-12 cursor-pointer list-none items-center gap-2.5 text-sm font-bold [&::-webkit-details-marker]:hidden"><CategoryBadge icon={categoryIconFor(category)} /><span className="min-w-0 flex-1">{name(category)}</span><span className="text-[11px] font-medium text-white/40">{children.length}</span><ChevronDown aria-hidden="true" className="size-4 text-white/50 transition group-open:rotate-180" /></summary>
              <ul className="grid grid-cols-2 gap-x-3 gap-y-1 pb-4 ps-9">
                {children.map(child => <li key={child.id ?? child.slug ?? child.name} className="min-w-0"><SubLink href={categoryHref(child)} icon={categoryIconFor(child)} label={name(child)} /></li>)}
                <li className="col-span-2 pt-1"><Link href={categoryHref(category)} className="inline-flex items-center gap-1 text-[13px] font-semibold text-white underline underline-offset-4">{text(`All of ${name(category)}`, `كل ${name(category)}`)}<ArrowUpRight aria-hidden="true" className="size-3.5 rtl:-rotate-90" /></Link></li>
              </ul>
            </details>
          })}
          {withoutSubs.length > 0 && <div className="flex flex-wrap gap-2 pt-4">
            {withoutSubs.map(category => <Link key={category.id ?? category.slug ?? category.name} href={categoryHref(category)} className="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3 py-1.5 text-xs font-medium text-white/75 transition hover:bg-white hover:text-black"><PolicyIcon icon={categoryIconFor(category)} />{name(category)}</Link>)}
          </div>}
        </div>
        {/* Larger screens: balanced columns, each department keeps its subcategories together. */}
        <div className="hidden gap-x-8 sm:block sm:columns-3 lg:columns-4">
          {withSubs.map(category => {
            const children = category.children ?? []
            return <div key={category.id ?? category.slug ?? category.name} className="mb-8 break-inside-avoid">
              <Link href={categoryHref(category)} className="group/link inline-flex items-center gap-2 text-[15px] font-bold transition hover:underline hover:underline-offset-4"><CategoryBadge icon={categoryIconFor(category)} />{name(category)}</Link>
              <ul className="mt-2.5 space-y-1.5 border-s border-white/15 ps-3">
                {children.slice(0, 6).map(child => <li key={child.id ?? child.slug ?? child.name}><SubLink href={categoryHref(child)} icon={categoryIconFor(child)} label={name(child)} /></li>)}
                {children.length > 6 && <li><Link href={categoryHref(category)} className="text-[13px] font-semibold text-white underline underline-offset-4">{text('View all', 'عرض الكل')}</Link></li>}
              </ul>
            </div>
          })}
          {withoutSubs.length > 0 && <div className="mb-8 break-inside-avoid">
            <p className="inline-flex items-center gap-2 text-[15px] font-bold"><CategoryBadge icon={LayoutGrid} />{text('More departments', 'أقسام تانية')}</p>
            <ul className="mt-2.5 space-y-1.5 border-s border-white/15 ps-3">
              {withoutSubs.map(category => <li key={category.id ?? category.slug ?? category.name}><SubLink href={categoryHref(category)} icon={categoryIconFor(category)} label={name(category)} /></li>)}
            </ul>
          </div>}
        </div>
        {brandData.brands.length > 0 && <div className="mt-6 flex flex-wrap items-center gap-2 border-t border-white/15 pt-6 sm:mt-2"><h2 className="me-2 inline-flex items-center gap-2 text-sm font-bold"><Tags aria-hidden="true" className="size-4" />{brandData.source === 'best-sellers' ? text('Popular brands', 'علامات رائجة') : text('Featured brands', 'علامات مميزة')}</h2>
          {brandData.brands.map(brand => <Link key={brand.id} href={`/brand/${encodeURIComponent(brand.slug || brand.id)}`} className="rounded-full border border-white/20 px-3.5 py-1.5 text-xs font-medium text-white/75 transition hover:bg-white hover:text-black">{brand.name}</Link>)}
        </div>}
      </nav>}
    </div>

    <FooterWordmark locale={locale} />

    <div className="relative border-t border-white/15">
      {/* Bottom room: the floating "Ask Eldokan" button sits over this corner. */}
      <div className="mx-auto flex max-w-7xl flex-col items-center gap-4 px-4 pb-40 pt-5 text-xs text-white/50 sm:px-6 md:pb-5 lg:flex-row lg:items-center lg:justify-between lg:pe-52 lg:ps-8">
        <p className="inline-flex items-center gap-1.5"><Copyright aria-hidden="true" className="size-3.5" />{new Date().getFullYear()} {BUSINESS.name[locale]}. {text('All rights reserved.', 'جميع الحقوق محفوظة.')}</p>
        <ul aria-label={text('Payment methods', 'وسائل الدفع')} className="flex flex-wrap items-center justify-center gap-2">
          {/* White chips so each payment brand keeps its own colours on the black footer. */}
          {[
            { label: text('Cash', 'كاش'), mark: <Banknote aria-hidden="true" className="size-4 text-emerald-600" />, named: true },
            { label: 'Visa', mark: <VisaMark /> },
            { label: 'Mastercard', mark: <MastercardMark /> },
            { label: text('Meeza', 'ميزة'), mark: <MeezaMark /> },
            { label: text('Installments', 'تقسيط'), mark: <Wallet aria-hidden="true" className="size-4 text-amber-500" />, named: true },
          ].map(({ label, mark, named }) => <li key={label} title={label} className="inline-flex h-8 items-center gap-1.5 rounded-md bg-white px-2.5 text-[11px] font-bold text-black">{mark}{named ? label : <span className="sr-only">{label}</span>}</li>)}
        </ul>
        <BackToTop label={text('Back to top', 'لأعلى الصفحة')} />
      </div>
    </div>
  </footer>
}

function CategoryBadge({ icon: Icon }: { icon: LucideIcon }) {
  return <span className="grid size-7 shrink-0 place-items-center rounded-lg border border-white/20 transition group-hover/link:bg-white group-hover/link:text-black"><Icon aria-hidden="true" className="size-4" /></span>
}

function SubLink({ href, icon: Icon, label }: { href: string; icon: LucideIcon; label: string }) {
  return <Link href={href} className="group/link inline-flex items-center gap-2 text-[13px] leading-5 text-white/55 transition hover:text-white"><Icon aria-hidden="true" className="size-3.5 shrink-0 text-white/35 group-hover/link:text-white" />{label}</Link>
}

function VisaMark() {
  return <svg aria-hidden="true" viewBox="0 0 48 16" className="h-3.5 w-auto"><text x="24" y="13.5" textAnchor="middle" fontFamily="Arial, Helvetica, sans-serif" fontSize="16" fontStyle="italic" fontWeight="900" fill="#1434CB" letterSpacing="-0.5">VISA</text></svg>
}

function MastercardMark() {
  return <svg aria-hidden="true" viewBox="0 0 32 20" className="h-5 w-auto">
    <circle cx="12" cy="10" r="8" fill="#EB001B" />
    <circle cx="20" cy="10" r="8" fill="#F79E1B" />
    <path d="M16 3.07a8 8 0 0 1 0 13.86a8 8 0 0 1 0-13.86z" fill="#FF5F00" />
  </svg>
}

function MeezaMark() {
  // Meeza's purple wordmark with its orange underline.
  return <svg aria-hidden="true" viewBox="0 0 52 20" className="h-4.5 w-auto"><text x="26" y="12.5" textAnchor="middle" fontFamily="Arial, Helvetica, sans-serif" fontSize="14" fontWeight="800" fill="#5B2C8F" letterSpacing="-0.3">meeza</text><rect x="27" y="15.5" width="21" height="3" rx="1.5" fill="#F26A21" /></svg>
}

function PolicyIcon({ icon: Icon }: { icon: LucideIcon }) {
  return <Icon aria-hidden="true" className="size-3.5" />
}
