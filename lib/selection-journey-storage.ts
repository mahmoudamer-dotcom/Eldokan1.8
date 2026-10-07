import type { AttributeOptionId } from '@eldokan/customer-api-client'

const KEY = 'eldokan.selection-journey.v1'
export type SelectionDraft = {
  version: 1
  savedAt: number
  categorySlug: string
  budget: string
  purpose: 'daily' | 'work' | 'gift'
  keywords: string
  priority: 'price' | 'rating' | 'sale'
  selected: Record<string, AttributeOptionId>
  step: number
}
export function readSelectionDraft(): SelectionDraft | null {
  try {
    const raw = window.sessionStorage.getItem(KEY)
    if (!raw || raw.length > 15000) return null
    const value: unknown = JSON.parse(raw)
    if (!value || typeof value !== 'object') return null
    const draft = value as Record<string, unknown>
    if (draft.version !== 1 || typeof draft.savedAt !== 'number' || !Number.isFinite(draft.savedAt) || draft.savedAt > Date.now() || Date.now() - draft.savedAt > 86400000 || typeof draft.categorySlug !== 'string' || !draft.categorySlug || draft.categorySlug.length > 200 || typeof draft.budget !== 'string' || draft.budget.length > 20 || typeof draft.keywords !== 'string' || draft.keywords.length > 100 || !['daily', 'work', 'gift'].includes(String(draft.purpose)) || !['price', 'rating', 'sale'].includes(String(draft.priority))) return null
    const selected: Record<string, AttributeOptionId> = {}
    if (draft.selected && typeof draft.selected === 'object' && !Array.isArray(draft.selected)) {
      for (const [id, option] of Object.entries(draft.selected)) {
        if (/^att_[1-9]\d*$/.test(id) && typeof option === 'string' && /^atr_[1-9]\d*$/.test(option)) selected[id] = option as AttributeOptionId
      }
    }
    return { version: 1, savedAt: draft.savedAt, categorySlug: draft.categorySlug, budget: draft.budget, keywords: draft.keywords, purpose: draft.purpose as SelectionDraft['purpose'], priority: draft.priority as SelectionDraft['priority'], selected, step: typeof draft.step === 'number' && draft.step >= 2 ? Math.min(3, Math.floor(draft.step)) : 1 }
  } catch { return null }
}
export function saveSelectionDraft(draft: Omit<SelectionDraft, 'savedAt'> & { savedAt?: number }): boolean {
  try { window.sessionStorage.setItem(KEY, JSON.stringify({ ...draft, savedAt: draft.savedAt ?? Date.now() })); return true } catch { return false }
}
export function clearSelectionDraft() {
  try { window.sessionStorage.removeItem(KEY) } catch { /* Selection works without storage. */ }
}
