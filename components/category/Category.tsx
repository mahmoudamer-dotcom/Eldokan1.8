
import { fetchCategoriesWithChildren } from "@/services/category";
import { ChevronDown, ChevronRight } from "lucide-react";
import Link from "next/link";
import { getLocale } from '@/lib/server-locale'
import { translate } from '@/lib/i18n'
import { translateCatalogName } from '@/lib/i18n'

export default async function Category() {
  const locale = await getLocale()
  const response = await fetchCategoriesWithChildren();
  const data = Array.isArray(response?.data) ? response.data : [];

  return (
    <div className="border-b border-border bg-card">
      <details className="group mx-3 py-2 sm:mx-6 lg:hidden">
        <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between rounded-xl bg-background px-4 text-sm font-semibold [&::-webkit-details-marker]:hidden">
          {translate('Shop by category', locale)}
          <ChevronDown className="size-4 transition-transform group-open:rotate-180" aria-hidden="true" />
        </summary>
        <nav aria-label={translate('Shop by category', locale)} className="mt-2 max-h-[50dvh] overflow-y-auto overscroll-contain rounded-xl border border-border p-2">
          {data.map((item, index) => (
            <div key={item.slug || item.name || index} className="border-b border-border p-2 last:border-0">
              <Link href={`/category/${encodeURIComponent(item.slug || item.name)}`} className="flex min-h-11 items-center rounded-lg px-2 text-sm font-bold hover:bg-shop-soft">
                {translateCatalogName(item.name, locale)}
              </Link>
              {item.children.length > 0 && <ul className="grid gap-1 ps-3 sm:grid-cols-2">
                {item.children.map((child) => <li key={child.slug || child.name}>
                  <Link href={`/category/${encodeURIComponent(child.slug || child.name)}`} className="flex min-h-11 items-center rounded-lg px-2 text-sm text-muted-foreground hover:bg-shop-soft">
                    {translateCatalogName(child.name, locale)}
                  </Link>
                </li>)}
              </ul>}
            </div>
          ))}
        </nav>
      </details>
      <nav aria-label={translate('Shop by category', locale)} className="relative z-20 mx-auto hidden max-w-7xl flex-wrap items-center gap-1 px-8 py-2 lg:flex">
        {data.map((item, index) => (
          <div className="group relative shrink-0" key={item.slug || item.name || index}>
            <Link
              href={`/category/${encodeURIComponent(item.slug || item.name)}`}
              aria-haspopup={item.children.length > 0 ? 'true' : undefined}
              className="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-semibold text-foreground transition-colors hover:bg-shop-soft hover:text-shop-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C58A36] sm:px-4 sm:text-sm"
            >
              {translateCatalogName(item.name, locale)}
              {item.children.length > 0 && <ChevronDown aria-hidden="true" className="size-3.5 text-muted-foreground transition-transform group-hover:rotate-180 group-focus-within:rotate-180" />}
            </Link>
            {item.children.length > 0 && (
              <div className="invisible absolute start-0 top-full z-50 w-64 translate-y-2 pt-2 opacity-0 transition duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100">
                <div className="overflow-hidden rounded-2xl border border-[#eadfca] bg-card p-2 shadow-[0_16px_40px_-16px_rgba(45,35,15,0.35)]">
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
