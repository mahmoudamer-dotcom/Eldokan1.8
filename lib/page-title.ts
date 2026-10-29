import type { Metadata } from 'next'
import { getLocale } from './server-locale'

/**
 * A page's tab title in the visitor's language. The root layout's template adds the store name,
 * so "Shopping cart" shows as "سلة التسوق | الدكان".
 */
export async function titled(en: string, ar: string, extra: Metadata = {}): Promise<Metadata> {
  const locale = await getLocale()
  return { ...extra, title: locale === 'ar' ? ar : en }
}
