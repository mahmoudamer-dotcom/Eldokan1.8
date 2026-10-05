
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
    <div className="border-b border-gray-200 bg-white">
      <nav aria-label={translate('Shop by category', locale)} className="category-scrollbar-hidden relative z-20 mx-auto flex max-w-7xl flex-nowrap items-center gap-1 overflow-x-auto px-3 py-2 sm:px-6 md:flex-wrap md:overflow-visible lg:px-8">
        {data.map((item, index) => (
          <div className="group relative shrink-0" key={item.slug || item.name || index}>
            <Link
              href={`/category/${encodeURIComponent(item.slug || item.name)}`}
              aria-haspopup={item.children.length > 0 ? 'true' : undefined}
              className="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-[#F5E7C6] hover:text-[#8a5b00] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C58A36] sm:px-4 sm:text-sm"
            >
              {translateCatalogName(item.name, locale)}
              {item.children.length > 0 && <ChevronDown aria-hidden="true" className="size-3.5 text-gray-400 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" />}
            </Link>
            {item.children.length > 0 && (
              <div className="invisible absolute start-0 top-full z-50 w-64 translate-y-2 pt-2 opacity-0 transition duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100">
                <div className="overflow-hidden rounded-2xl border border-[#eadfca] bg-white p-2 shadow-[0_16px_40px_-16px_rgba(45,35,15,0.35)]">
                  <p className="px-3 pb-2 pt-2 text-[11px] font-bold uppercase tracking-[0.14em] text-[#a66d00]">{locale === 'ar' ? 'تصفح' : 'Browse'} {translateCatalogName(item.name, locale)}</p>
                  <ul className="space-y-1 border-t border-gray-100 pt-2">
                    {item.children.map((child) => (
                      <li key={child.slug || child.name}>
                        <Link
                          href={`/category/${encodeURIComponent(child.slug || child.name)}`}
                          className="group/child flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-[#fff8e7] hover:text-[#8a5b00] focus-visible:bg-[#fff8e7] focus-visible:outline-none"
                        >
                          {translateCatalogName(child.name, locale)}
                          <ChevronRight aria-hidden="true" className="size-4 text-gray-300 transition group-hover/child:translate-x-0.5 group-hover/child:text-[#a66d00] rtl:rotate-180" />
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
