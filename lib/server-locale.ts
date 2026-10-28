import { cookies } from 'next/headers'
import type { Locale } from './i18n'

export async function getLocale(): Promise<Locale> {
  const value = (await cookies()).get('eldokan_locale')?.value
  return value === 'ar' ? 'ar' : 'en'
}

export async function getTheme(): Promise<'light' | 'dark'> {
  const value = (await cookies()).get('eldokan_theme')?.value
  // Dark (deep black) is the store's look; a visitor who picks light keeps it.
  return value === 'light' ? 'light' : 'dark'
}
