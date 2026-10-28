import type { Metadata } from 'next'
import Link from 'next/link'
import { BadgeCheck, Tag, Truck } from 'lucide-react'
import ProductCard from '@/components/productCard/ProductCard'
import { UsedLikeNewProducts } from '@/services/product'
import { getLocale } from '@/lib/server-locale'

export async function generateMetadata(): Promise<Metadata> {
  const ar = await getLocale() === 'ar'
  return {
    title: ar ? 'مستعمل كالجديد' : 'Used like new',
    description: ar ? 'منتجات مستعملة بحالة ممتازة وبسعر أقل من الجديد.' : 'Pre-owned products in excellent condition for less than new.',
  }
}

export default async function UsedLikeNewPage() {
  const ar = await getLocale() === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const { products, unavailable } = await UsedLikeNewProducts()
  const points = [
    { icon: BadgeCheck, title: text('Excellent condition', 'حالة ممتازة'), body: text('Pre-owned items that look and work like new.', 'منتجات مستعملة شكلها وأداؤها زي الجديد.') },
    { icon: Tag, title: text('Lower price', 'سعر أقل'), body: text('Pay noticeably less than the new item.', 'هتدفع أقل بكتير من سعر الجديد.') },
    { icon: Truck, title: text('Same delivery and payment', 'نفس التوصيل والدفع'), body: text('Delivered and paid for like any other order.', 'بيتوصل وبيتدفع زي أي طلب تاني.') },
  ]

  return (
    <main className="container mx-auto px-4 pb-14">
      <section className="mt-6 overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-50 via-white to-shop-soft p-6 ring-1 ring-emerald-100 sm:p-10 dark:from-emerald-950/40 dark:via-card dark:to-shop-soft dark:ring-emerald-900/50">
        <p className="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white"><BadgeCheck className="size-4" aria-hidden="true" />{text('Used like new', 'مستعمل كالجديد')}</p>
        <h1 className="mt-4 max-w-2xl text-3xl font-extrabold leading-tight text-foreground sm:text-4xl">{text('Like new. For less.', 'زي الجديد، بسعر أقل.')}</h1>
        <p className="mt-3 max-w-xl text-base leading-7 text-muted-foreground">{text('Hand-picked pre-owned products in excellent condition, ready to ship.', 'منتجات مستعملة مختارة بعناية وبحالة ممتازة، جاهزة للشحن.')}</p>
        <ul className="mt-6 grid gap-3 sm:grid-cols-3">
          {points.map(({ icon: Icon, title, body }) => (
            <li key={title} className="flex gap-3 rounded-2xl bg-card/80 p-4 ring-1 ring-border">
              <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-600/10 text-emerald-700 dark:text-emerald-300"><Icon className="size-5" aria-hidden="true" /></span>
              <span><span className="block text-sm font-bold text-foreground">{title}</span><span className="mt-0.5 block text-sm text-muted-foreground">{body}</span></span>
            </li>
          ))}
        </ul>
      </section>

      <section className="mt-8" aria-labelledby="used-products-title">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <h2 id="used-products-title" className="text-xl font-bold text-foreground sm:text-2xl">{text('Available now', 'متاح دلوقتي')}</h2>
          {products.length > 0 && <p className="text-sm text-muted-foreground">{products.length} {text(products.length === 1 ? 'product' : 'products', 'منتج')}</p>}
        </div>
        {products.length > 0 ? (
          <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
            {products.map((product, index) => <ProductCard key={product.id ?? index} product={product} likeNew />)}
          </div>
        ) : (
          <div className="mt-5 rounded-2xl border border-dashed border-input bg-card px-6 py-16 text-center">
            <h3 className="text-lg font-semibold text-foreground">{unavailable ? text('Products could not be loaded', 'تعذر تحميل المنتجات') : text('New arrivals are on the way', 'منتجات جديدة في الطريق')}</h3>
            <p className="mt-2 text-sm text-muted-foreground">{unavailable ? text('Refresh the page in a moment.', 'حدّث الصفحة بعد شوية.') : text('Like-new products sell fast. Check back soon.', 'المنتجات اللي زي الجديد بتخلص بسرعة. ارجع تاني قريب.')}</p>
            <Link href="/" className="mt-5 inline-flex rounded-full bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground">{text('Continue shopping', 'كمّل تسوق')}</Link>
          </div>
        )}
      </section>
    </main>
  )
}
