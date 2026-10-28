import type { Locale } from './i18n'
import { catalogArabicName } from './i18n'

/**
 * Arabic for store data the API sends in English (the catalog is always read in English).
 * Translated here, not by the API: add a line when a product or section is added in WordPress.
 * Anything without a line keeps its English name.
 */
const catalogArabic: Record<string, string> = {
  // Products
  'iPhone 17 Pro Max 256 GB (eSIM only) Silver 5G With FaceTime - International Version': 'آيفون 17 برو ماكس 256 جيجا (eSIM فقط) فضي 5G مع FaceTime - النسخة الدولية',
  'Total L-Angled socket wrench': 'مفتاح لقمة على شكل L من توتال',
  'Total Infrared thermometer': 'ترمومتر بالأشعة تحت الحمراء من توتال',
  'Total Pipe wrench 50mm 14': 'مفتاح مواسير توتال 14 بوصة (50 مم)',
  'Total Big Test pencil': 'قلم اختبار كهرباء كبير من توتال',
  'Total Super Glue': 'سوبر جلو (لاصق فوري) من توتال',
  'INGCO Set of 18 Precision Screwdriver and Screwdriver Set, Strong Magnetic with Plastic Frame': 'طقم مفكات دقيقة إنجكو 18 قطعة، ممغنط بقوة مع حامل بلاستيك',
  'INGCO PVC Insulating Tape': 'شريط عازل PVC من إنجكو',
  'INGCO 12 Pcs Flexible Shaft Screwdriver Set AKSDFL1208': 'طقم مفكات بعمود مرن إنجكو 12 قطعة AKSDFL1208',
  'Ice cap for women and men': 'طاقية شتوي للرجال والسيدات',
  // Home sections
  'Shop by Category': 'تسوق حسب القسم',
  'Featured Deals': 'عروض مميزة',
  'Best Sellers': 'الأكثر مبيعًا',
  'New Arrivals': 'وصل حديثًا',
  'Featured Brands': 'علامات تجارية مميزة',
  // Checkout
  'Pickup From Branches': 'الاستلام من الفروع',
  'Door Delivery': 'التوصيل للباب',
  'Cash on Delivery': 'الدفع عند الاستلام',
  'Pay when you receive your purchase.': 'ادفع لما طلبك يوصلك.',
  'Collect your items from the available branches.': 'استلم طلبك من أقرب فرع متاح.',
  'Delivery to your checkout address.': 'التوصيل على العنوان اللي كتبته.',
}

const englishByArabic = new Map(Object.entries(catalogArabic).map(([en, ar]) => [ar, en]))

export function translateCatalogText(text: string, locale: Locale) {
  if (locale !== 'ar') return text
  return catalogArabic[text] ?? catalogArabicName(text) ?? text
}

/** The English store name behind an Arabic name shown on the site (for searching the English catalog). */
export function englishCatalogText(text: string) {
  const trimmed = text.trim()
  return englishByArabic.get(trimmed) ?? trimmed
}

const TRANSLATED_KEYS = new Set(['name', 'title', 'description'])

/**
 * Returns a copy of an API payload with known catalog names in Arabic. Only display
 * text under `name`, `title` and short `description` fields changes; IDs, slugs, prices
 * and anything else stay exactly as the store sent them.
 */
export function localizeCatalogData<T>(value: T, locale: Locale): T {
  if (locale !== 'ar') return value
  const walk = (node: unknown): unknown => {
    if (Array.isArray(node)) return node.map(walk)
    if (!node || typeof node !== 'object') return node
    const copy: Record<string, unknown> = {}
    for (const [key, child] of Object.entries(node)) {
      copy[key] = typeof child === 'string' && TRANSLATED_KEYS.has(key) && child.length < 300 ? translateCatalogText(child, locale) : walk(child)
    }
    return copy
  }
  return walk(value) as T
}

/** Wraps a fetch so JSON answers from the store come back with Arabic catalog names. */
export function localizingFetch(fetchImpl: typeof fetch, locale: Locale): typeof fetch {
  if (locale !== 'ar') return fetchImpl
  return async (input, init) => {
    const response = await fetchImpl(input, init)
    if (!response.headers.get('content-type')?.includes('json')) return response
    const text = await response.text()
    let body = text
    try { body = JSON.stringify(localizeCatalogData(JSON.parse(text), locale)) } catch { /* not JSON after all: pass it on unchanged */ }
    const headers = new Headers(response.headers)
    headers.delete('content-length')
    headers.delete('content-encoding')
    return new Response(body, { status: response.status, statusText: response.statusText, headers })
  }
}
