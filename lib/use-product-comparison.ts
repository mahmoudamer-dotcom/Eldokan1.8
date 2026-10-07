'use client'

import { useMemo, useSyncExternalStore } from 'react'
import { COMPARE_KEY, normalizeComparisonIds } from './product-comparison'

function snapshot() {
  try { return localStorage.getItem(COMPARE_KEY) ?? '[]' } catch { return '[]' }
}

function subscribe(listener: () => void) {
  window.addEventListener('eldokan:compare-changed', listener)
  window.addEventListener('storage', listener)
  return () => {
    window.removeEventListener('eldokan:compare-changed', listener)
    window.removeEventListener('storage', listener)
  }
}

export function useProductComparison() {
  const value = useSyncExternalStore(subscribe, snapshot, () => '[]')
  return useMemo(() => {
    try { return normalizeComparisonIds(JSON.parse(value)) } catch { return [] }
  }, [value])
}
