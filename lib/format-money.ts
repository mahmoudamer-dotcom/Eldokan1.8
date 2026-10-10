import { translate, type Locale } from './i18n'

export type DisplayMoney = { amount?: number; currency?: string; decimals?: number; formatted?: string }

export function formatMoney(money: DisplayMoney | null | undefined, locale: Locale): string {
  if (!money) return '—'
  if (Number.isFinite(money.amount) && Number.isInteger(money.decimals) && money.decimals! >= 0 && money.decimals! <= 6) {
    const value = new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-EG', { minimumFractionDigits: money.decimals, maximumFractionDigits: money.decimals }).format(money.amount! / 10 ** money.decimals!)
    return `${value} ${money.currency === 'EGP' && locale === 'ar' ? 'جنيه' : money.currency ?? ''}`.trim()
  }
  return locale === 'ar' ? translate(money.formatted ?? '—', locale).replace(/\bEGP\b/g, 'جنيه') : money.formatted ?? '—'
}
