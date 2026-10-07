import { ArrowUpRight, CreditCard, Headphones, Mail, MessageCircle, Phone, Store } from 'lucide-react'
import Image from 'next/image'
import Link from 'next/link'
import { fetchCategoriesWithChildren } from '@/services/category'
import { PopularBrands } from '@/services/brand'
import { getLocale } from '@/lib/server-locale'
import { translateCatalogName } from '@/lib/i18n'
import { BUSINESS, POLICIES } from '@/lib/legal-policies'

type FooterCategory = { id?: string | number; name: string; slug?: string; parent_id?: string | number | null; children?: FooterCategory[]; subcategories?: FooterCategory[] }
function categoryHref(category: FooterCategory) { return `/category/${category.slug || encodeURIComponent(category.name)}` }

export default async function Footer() {
  const locale = await getLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const [response, brandData] = await Promise.all([fetchCategoriesWithChildren(), PopularBrands()])
  const categories: FooterCategory[] = Array.isArray(response?.data) ? response.data : []
  const roots = categories.filter(category => !category.parent_id || !categories.some(parent => String(parent.id) === String(category.parent_id)))
  const categoryGroups = roots.map(category => {
    const children = new Map<string, FooterCategory>()
    for (const child of [...(category.children ?? []), ...(category.subcategories ?? []), ...categories.filter(child => child.parent_id && String(child.parent_id) === String(category.id))]) {
      children.set(String(child.id ?? child.slug ?? child.name), child)
    }
    return { category, children: [...children.values()] }
  }).sort((a, b) => Number(b.children.length > 0) - Number(a.children.length > 0))
  const linkStyle = 'inline-block rounded-sm text-sm leading-6 text-muted-foreground transition-colors hover:text-white hover:underline focus-visible:outline-white'
  const groups = [
    { title: text('Customer care', 'خدمة العملاء'), links: [
      { href: '/help', label: text('Help & contact', 'المساعدة والتواصل') }, { href: '/orders', label: text('My orders', 'طلباتي') },
      ...POLICIES.filter(p => ['shipping', 'returns', 'buyer-terms'].includes(p.slug)).map(p => ({ href: `/policies/${p.slug}`, label: p.title[locale] })),
    ] },
    { title: text('About your account', 'الشروط والخصوصية'), links: [...POLICIES.filter(p => ['terms', 'privacy', 'cookies'].includes(p.slug)).map(p => ({ href: `/policies/${p.slug}`, label: p.title[locale] })), { href: '/policies', label: text('All policies', 'كل السياسات') }] },
    { title: text('Grow with Eldokan', 'بيع مع الدكان'), links: POLICIES.filter(p => p.audience === 'seller').map(p => ({ href: `/policies/${p.slug}`, label: p.title[locale] })) },
  ]
  return <footer dir={ar ? 'rtl' : 'ltr'} className="mt-auto w-full shrink-0 border-t border-border bg-card text-foreground">
    <section aria-label={text('Customer support', 'دعم العملاء')} className="border-b border-border bg-muted">
      <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-7 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div><h2 className="text-xl font-bold">{text("We're here to help", 'إحنا هنا علشان نساعدك')}</h2><p className="mt-1.5 text-sm text-muted-foreground">{text('Get help with your shopping, delivery and orders.', 'تواصل معانا لأي استفسار عن المنتجات، التوصيل أو طلباتك.')}</p></div>
        <div className="grid gap-5 sm:grid-cols-3 sm:gap-8">
          <Link href="/help" className="group flex items-center gap-3"><span className="grid size-11 shrink-0 place-items-center rounded-full border border-border bg-card"><Headphones aria-hidden="true" className="size-5" /></span><span><span className="block text-xs text-muted-foreground">{text('Need assistance?', 'محتاج مساعدة؟')}</span><span className="mt-1 block text-sm font-semibold group-hover:underline">{text('Help center', 'مركز المساعدة')}</span></span></Link>
          <a href={`https://wa.me/${BUSINESS.phone.replace('+', '')}`} target="_blank" rel="noopener noreferrer" className="group flex items-center gap-3"><span className="grid size-11 shrink-0 place-items-center rounded-full border border-border bg-card"><MessageCircle aria-hidden="true" className="size-5" /></span><span><span className="block text-xs text-muted-foreground">{text('WhatsApp support', 'الدعم على واتساب')}</span><span dir="ltr" className="mt-1 block text-sm font-semibold group-hover:underline">01006806022</span></span></a>
          <a href={`mailto:${BUSINESS.email}`} className="group flex min-w-0 items-center gap-3"><span className="grid size-11 shrink-0 place-items-center rounded-full border border-border bg-card"><Mail aria-hidden="true" className="size-5" /></span><span className="min-w-0"><span className="block text-xs text-muted-foreground">{text('Email us', 'راسلنا')}</span><span dir="ltr" className="mt-1 block break-all text-sm font-semibold group-hover:underline">{BUSINESS.email}</span></span></a>
        </div>
      </div>
    </section>
    <div className="bg-[#191919] text-white"><div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div className="grid gap-x-8 gap-y-9 py-10 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr]">
        <section className="sm:col-span-2 lg:col-span-1"><Link href="/" aria-label={text('Eldokan home', 'الصفحة الرئيسية للدكان')} className="brand-logo-canvas inline-flex rounded-lg px-3 py-2"><Image src="/image/Eldokan-logo.webp" width={120} height={60} className="h-14 w-auto object-contain" alt={BUSINESS.name[locale]} /></Link><p className="mt-4 max-w-xs text-sm leading-7 text-muted-foreground">{text('Shop everyday essentials and discover something new at Eldokan.', 'تسوّق احتياجاتك اليومية واكتشف منتجات جديدة مع الدكان.')}</p><address className="mt-4 max-w-xs text-sm not-italic leading-7 text-[#b0b7c3]">{BUSINESS.address[locale]}</address><a href={`tel:${BUSINESS.phone}`} className="mt-3 inline-flex items-center gap-2 text-sm font-semibold hover:underline"><Phone aria-hidden="true" className="size-4" /><span dir="ltr">01006806022</span></a></section>
        {groups.map((group, index) => <nav key={group.title} aria-label={group.title}><h2 className="mb-5 text-sm font-bold">{group.title}</h2><ul className="space-y-3">{group.links.map(item => <li key={item.href}><Link href={item.href} className={linkStyle}>{item.label}</Link></li>)}</ul>{index === 2 && <a href="https://sell.eldokan.com/login" target="_blank" rel="noopener noreferrer" className="mt-5 inline-flex min-h-11 items-center gap-2 rounded-md border border-white bg-card px-4 text-sm font-semibold text-foreground transition hover:bg-muted focus-visible:outline-white"><Store aria-hidden="true" className="size-4" />{text('Start selling', 'ابدأ البيع')}<ArrowUpRight aria-hidden="true" className="size-4" /></a>}</nav>)}
      </div>
      {roots.length > 0 && <nav aria-label={text('Shop categories', 'تسوق حسب القسم')} className="border-t border-white/15 py-7"><h2 className="mb-5 text-sm font-bold">{text('Shop categories', 'تسوق حسب القسم')}</h2><div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">{categoryGroups.map(({ category, children }) => (
        <div key={category.id ?? category.slug ?? category.name}><Link href={categoryHref(category)} className="text-sm font-semibold hover:underline">{translateCatalogName(category.name, locale)}</Link>{children.length > 0 && <ul className="mt-2 space-y-1.5">{children.slice(0, 6).map(child => <li key={child.id ?? child.slug ?? child.name}><Link href={categoryHref(child)} className={linkStyle}>{translateCatalogName(child.name, locale)}</Link></li>)}</ul>}</div>
      ))}</div></nav>}
      {brandData.brands.length > 0 && <nav aria-label={text('Brands', 'العلامات التجارية')} className="flex flex-col gap-3 border-t border-white/15 py-6 sm:flex-row sm:gap-6"><h2 className="shrink-0 text-sm font-bold">{brandData.source === 'best-sellers' ? text('Popular brands', 'علامات تجارية رائجة') : text('Featured brands', 'علامات تجارية مميزة')}</h2><ul className="flex flex-wrap gap-x-6 gap-y-2">{brandData.brands.map(brand => <li key={brand.id}><Link href={`/brand/${encodeURIComponent(brand.slug || brand.id)}`} className={linkStyle}>{brand.name}</Link></li>)}</ul></nav>}
    </div></div>
    <div className="border-t border-border bg-muted"><div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-5 text-xs leading-6 text-muted-foreground sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8"><p>© {new Date().getFullYear()} {BUSINESS.name[locale]}. {text('All rights reserved.', 'جميع الحقوق محفوظة.')}</p><p className="flex items-start gap-2"><CreditCard aria-hidden="true" className="mt-1 size-4 shrink-0" />{text('Available payment methods are shown at checkout.', 'وسائل الدفع المتاحة تظهر عند إتمام الشراء.')}</p><div className="flex flex-wrap gap-x-5 gap-y-2"><Link href="/policies/privacy" className="hover:text-foreground hover:underline">{text('Privacy', 'الخصوصية')}</Link><Link href="/policies/terms" className="hover:text-foreground hover:underline">{text('Terms of use', 'شروط الاستخدام')}</Link></div></div></div>
  </footer>
}