export const COMPARE_KEY = 'eldokan.compare.v1'
export function normalizeComparisonIds(values: unknown): string[] {
  return Array.isArray(values) ? [...new Set(values.filter((value): value is string => typeof value === 'string' && /^prd_[1-9][0-9]*$/.test(value)))].slice(0, 4) : []
}
