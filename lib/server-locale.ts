import { cookies } from 'next/headers'
import type { Locale } from './i18n'

export async function getLocale(): Promise<Locale> {
  const value = (await cookies()).get('eldokan_locale')?.value
  // Arabic unless the visitor chose English.
  return value === 'en' ? 'en' : 'ar'
}

export async function getTheme(): Promise<'light' | 'dark'> {
  const value = (await cookies()).get('eldokan_theme')?.value
  // Light unless the visitor chose dark.
  return value === 'dark' ? 'dark' : 'light'
}
