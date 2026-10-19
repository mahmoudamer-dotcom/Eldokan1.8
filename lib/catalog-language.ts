import type { Language } from '@eldokan/customer-api-client'

/**
 * The store keeps one copy of every product and category. The visitor's locale
 * only changes the storefront interface; it never selects a different catalog,
 * so product IDs, carts and wishlists stay the same when the language changes.
 */
export const CATALOG_LANGUAGE: Language = 'en'

/** Pin a store API URL that carries a language to the single catalog language. */
export function withCatalogLanguage(input: Parameters<typeof fetch>[0]) {
  const url = new URL(input instanceof Request ? input.url : input)
  if (url.searchParams.has('lang')) url.searchParams.set('lang', CATALOG_LANGUAGE)
  return url
}
