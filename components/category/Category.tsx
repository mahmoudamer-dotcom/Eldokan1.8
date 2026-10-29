
import { fetchCategoriesWithChildren } from "@/services/category";
import { ChevronDown, ChevronRight } from "lucide-react";
import Link from "next/link";
import Image from 'next/image'
import { categoryArt, categoryWorldOf } from '@/lib/category-experience'
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'
import { translateCatalogName } from '@/lib/i18n'
import { categoryIconFor } from '@/lib/category-icons'

export default async function Category() {
  const locale = await getLocale()
  const response = await fetchCategoriesWithChildren();
  const data = Array.isArray(response?.data) ? response.data : [];

  return (
    <div className="border-b border-border bg-card">
      {/* Phones and tablets: one swipeable row of department chips, like an app's tabs. */}
      <nav aria-label={translate('Shop by category', locale)} className="category-scrollbar-hidden flex gap-2 overflow-x-auto overscroll-x-contain px-3 py-2 sm:px-6 lg:hidden">
        {data.map((item, index) => {
          const Icon = categoryIconFor(item)
          return <Link key={item.slug || item.name || index} href={`/category/${encodeURIComponent(item.slug || item.name)}`} className="inline-flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-border bg-background px-3.5 text-[13px] font-semibold transition hover:border-foreground/40 active:scale-95">
            <Icon aria-hidden="true" className="size-4 shrink-0 text-shop-accent" />{translateCatalogName(item.name, locale)}
          </Link>
        })}
      </nav>
      <nav aria-label={translate('Shop by category', locale)} className="relative z-20 mx-auto hidden max-w-7xl flex-wrap items-center gap-1 px-8 py-2 lg:flex">
        {data.map((item, index) => (
          <div className="group relative shrink-0" key={item.slug || item.name || index}>
            <Link
              href={`/category/${encodeURIComponent(item.slug || item.name)}`}
              aria-haspopup={item.children.length > 0 ? 'true' : undefined}
              className="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-semibold text-foreground transition-colors hover:bg-shop-soft hover:text-shop-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C58A36] sm:px-4 sm:text-sm"
            >
              {translateCatalogName(item.name, locale)}
              {item.children.length > 0 && <ChevronDown aria-hidden="true" className="size-3.5 text-muted-foreground transition-transform group-hover:rotate-180 group-has-[:focus-visible]:rotate-180" />}
            </Link>
            {item.children.length > 0 && (
              <div className="invisible absolute start-0 top-full z-50 w-64 translate-y-2 pt-2 opacity-0 transition duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-has-[:focus-visible]:visible group-has-[:focus-visible]:translate-y-0 group-has-[:focus-visible]:opacity-100">
                <div className="overflow-hidden rounded-2xl border border-[#eadfca] bg-card p-2 shadow-[0_16px_40px_-16px_rgba(45,35,15,0.35)]">
                  <Image src={categoryArt(categoryWorldOf(item.slug, item.name), 'small')} alt="" width={480} height={144} unoptimized className="h-20 w-full rounded-xl object-cover" />
                  <p className="px-3 pb-2 pt-2 text-[11px] font-bold uppercase tracking-[0.14em] text-shop-accent">{locale === 'ar' ? 'تصفح' : 'Browse'} {translateCatalogName(item.name, locale)}</p>
                  <ul className="space-y-1 border-t border-border pt-2">
                    {item.children.map((child) => (
                      <li key={child.slug || child.name}>
                        <Link
                          href={`/category/${encodeURIComponent(child.slug || child.name)}`}
                          className="group/child flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-shop-soft hover:text-shop-accent focus-visible:bg-shop-soft focus-visible:outline-none"
                        >
                          {translateCatalogName(child.name, locale)}
                          <ChevronRight aria-hidden="true" className="size-4 text-muted-foreground transition group-hover/child:translate-x-0.5 group-hover/child:text-shop-accent rtl:rotate-180" />
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            )}
          </div>
        ))}
      </nav>
    </div>
  );
}
