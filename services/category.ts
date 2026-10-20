import { cache } from 'react'
import { unstable_cache } from 'next/cache'
import { createEldokanApi } from '@/lib/eldokan-api'
import { getLocale } from '@/lib/server-locale'
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

const loadCategoryTree = unstable_cache(async () => {
  const api = createEldokanApi()
  const response = await retryApiRead(() => api.categories.list())
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
      return { ...category, children }
    }))
    categories.push(...details)
  }

  return { data: categories }
}, ['category-tree-v4'], { revalidate: 60, tags: ['category-tree'] })

const fetchCategoryTree = cache(() => loadCategoryTree())

export async function fetchCategoriesWithChildren() {
  try {
    return await fetchCategoryTree()
  } catch {
    return { data: [] }
  }
}

export const fetchCategoryBySlug = cache(async (slug: string) => {
  try {
    const api = createEldokanApi(await getLocale())
    return await retryApiRead(() => api.categories.get(slug))
  } catch {
    return null
  }
})

export async function fetchCategoryFilters(slug: string) {
  try {
    const api = createEldokanApi(await getLocale())
    return await retryApiRead(() => api.categories.filters(slug))
  } catch {
    return null
  }
}
