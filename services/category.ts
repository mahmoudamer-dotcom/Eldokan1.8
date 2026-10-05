import { cache } from 'react'
import { unstable_cache } from 'next/cache'
import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
import type { Locale } from '@/lib/i18n'
import { retryApiRead } from '@/lib/retry-api-read'

export async function fetchUsers() {
  try {
    const locale = await getLocale()
    const api = createEldokanApi(locale)
    return await retryApiRead(() => api.categories.list())
  } catch {
    return { data: [] }
  }
}

const loadCategoriesForLocale = unstable_cache(async (locale: Locale) => {
  const api = createEldokanApi('en')

  const [response, localizedResponse] = await Promise.all([
    retryApiRead(() => api.categories.list()),
    locale === 'ar'
      ? retryApiRead(() => createEldokanApi('ar').categories.list()).catch(() => null)
      : Promise.resolve(null),
  ])
  const localizedNames = new Map((localizedResponse?.data ?? []).map((category) => [category.slug, category.name]))
  const roots = response.data ?? []
  if (roots.length === 0) throw new Error('The customer API returned an empty category list.')
  const categories = []
  for (let start = 0; start < roots.length; start += 4) {
    const group = roots.slice(start, start + 4)
    const details = await Promise.all(group.map(async (category) => {
      let children
      try {
        const detail = await retryApiRead(() => api.categories.get(category.slug))
        children = detail.data.children ?? []
      } catch {
        const childList = await retryApiRead(() => api.categories.list({ parent: category.slug }))
        children = childList.data
      }
      return {
        ...category,
        name: locale === 'ar' ? localizedNames.get(category.slug) ?? category.name : category.name,
        children: children.map((child) => ({
          ...child,
          name: locale === 'ar' ? localizedNames.get(child.slug) ?? child.name : child.name,
        })),
      }
    }))
    categories.push(...details)
  }

  return { data: categories }
}, ['category-tree-v3'], { revalidate: 300, tags: ['category-tree'] })

const fetchCategoriesForLocale = cache((locale: Locale) => loadCategoriesForLocale(locale))

export async function fetchCategoriesWithChildren() {
  try {
    return await fetchCategoriesForLocale(await getLocale())
  } catch {
    return { data: [] }
  }
}

export async function fetchCategoryBySlug(slug: string) {
  try {
    const api = createEldokanApi(await getLocale())
    return await retryApiRead(() => api.categories.get(slug))
  } catch {
    return null
  }
}

export async function fetchCategoryFilters(slug: string) {
  try {
    const api = createEldokanApi(await getLocale())
    return await retryApiRead(() => api.categories.filters(slug))
  } catch {
    return null
  }
}
