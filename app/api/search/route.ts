import type { NextRequest } from 'next/server'
import { Search } from '@/services/search'
import type { Locale } from '@/lib/i18n'

export async function GET(request: NextRequest) {
  const query = request.nextUrl.searchParams.get('q')?.trim() ?? ''
  const requestedLocale = request.nextUrl.searchParams.get('lang')
  const cookieLocale = request.cookies.get('eldokan_locale')?.value
  const locale: Locale = requestedLocale === 'ar' || requestedLocale === 'en'
    ? requestedLocale
    : cookieLocale === 'ar' ? 'ar' : 'en'

  if (query.length < 2) {
    return Response.json({ data: [] }, { headers: { 'Cache-Control': 'no-store' } })
  }

  const result = await Search(query, locale)
  return Response.json(result, { headers: { 'Cache-Control': 'no-store' } })
}
