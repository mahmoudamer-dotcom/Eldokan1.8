'use client'

import Link from 'next/link'
import { useEffect, useState } from 'react'
import type { DecisionProfile, ProductAlert } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { readSelectionDraft, saveSelectionDraft } from '@/lib/selection-journey-storage'
import { useShoppingChat } from '@/components/discovery/ShoppingChatProvider'
import { COMPARE_KEY, normalizeComparisonIds } from '@/lib/product-comparison'

export default function CommercePreferences() {
  const { locale } = useLocale(); const ar = locale === 'ar'; const openChat = useShoppingChat()
  const [alerts, setAlerts] = useState<ProductAlert[]>([])
  const [decision, setDecision] = useState<DecisionProfile | null>(null)
  const [busy, setBusy] = useState(false); const [message, setMessage] = useState('')
  useEffect(() => { let active = true; const api = createEldokanApi(locale); Promise.all([api.commerce.alerts(), api.commerce.decision()]).then(([a, d]) => { if (active) { setAlerts(a.data.items); setDecision(d.data) } }).catch(() => { if (active) setMessage(ar ? 'تعذر تحميل التنبيهات والاختيارات. تأكد من تحديث إضافة المتجر.' : 'Could not load alerts and selections. Check the store plugin update.') }); return () => { active = false } }, [locale, ar])
  async function save() {
    setBusy(true); setMessage('')
    try {
      const draft = readSelectionDraft()
      if (!draft) { setMessage(ar ? 'ابدأ رحلة اختيار من مساعد الدكان الأول.' : 'Start a selection with the shopping assistant first.'); return }
      const api = createEldokanApi(locale); await api.auth.session()
      let ids: string[] = []; try { ids = normalizeComparisonIds(JSON.parse(localStorage.getItem(COMPARE_KEY) ?? '[]')) } catch { /* Empty comparison is valid. */ }
      setDecision((await api.commerce.saveDecision({ category: draft.categorySlug, budget: draft.budget, keywords: draft.keywords, priority: draft.priority, compare_ids: ids })).data)
      setMessage(ar ? 'تم حفظ اختياراتك في حسابك.' : 'Your selections were saved to your account.')
    } catch { setMessage(ar ? 'تعذر حفظ الاختيارات.' : 'Could not save selections.') } finally { setBusy(false) }
  }
  function resume() {
    if (!decision) return
    saveSelectionDraft({ version: 1, categorySlug: decision.category, budget: decision.budget, purpose: 'daily', keywords: decision.keywords, priority: ['price', 'rating', 'sale'].includes(decision.priority) ? decision.priority as 'price' | 'rating' | 'sale' : 'price', selected: {}, step: 2 })
    try { localStorage.setItem(COMPARE_KEY, JSON.stringify(normalizeComparisonIds(decision.compare_ids))); window.dispatchEvent(new Event('eldokan:compare-changed')) } catch { /* Chat remains available. */ }
    openChat({ resume: true })
  }
  async function remove(id: ProductAlert['product_id']) { setBusy(true); try { const api = createEldokanApi(locale); await api.auth.session(); setAlerts((await api.commerce.removeAlert(id)).data.items) } catch { setMessage(ar ? 'تعذر إلغاء التنبيه.' : 'Could not remove alert.') } finally { setBusy(false) } }
  return <section className="mt-8 space-y-6 border-t border-border pt-6"><div><h2 className="text-lg font-bold">{ar ? 'ملف اختياراتك' : 'Your selection profile'}</h2><p className="mt-2 text-sm text-muted-foreground">{ar ? 'احفظ رحلة الاختيار والمقارنة وارجع لها من حسابك.' : 'Save your selection journey and comparison, then resume from your account.'}</p><div className="mt-4 flex flex-wrap gap-3"><button disabled={busy} onClick={() => void save()} className="rounded-xl border px-4 py-2 text-sm disabled:opacity-50">{ar ? 'حفظ الاختيارات الحالية' : 'Save current selections'}</button>{decision?.category && <button onClick={resume} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">{ar ? 'كمّل اختيارك' : 'Resume your selection'}</button>}</div></div>
    <div><h2 className="text-lg font-bold">{ar ? 'تنبيهات السعر والمخزون' : 'Price & stock alerts'}</h2><p className="mt-2 text-sm text-muted-foreground">{ar ? 'التنبيهات بتوصلك على بريد حسابك. تقدر تضيفها من صفحة المنتج.' : 'Alerts are sent to your account email. Add them from a product page.'}</p>{alerts.map(alert => <div key={alert.product_id} className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 text-sm"><Link href={`/product/${alert.product_id}`} className="font-semibold underline">{alert.name}</Link><span>{alert.kind === 'stock' ? (ar ? 'عودة المخزون' : 'Back in stock') : `${ar ? 'السعر المستهدف' : 'Target price'}: ${alert.target_price}`}{alert.notified_at && ` · ${ar ? 'تم إرسال التنبيه' : 'Notification sent'}`}</span><button disabled={busy} onClick={() => void remove(alert.product_id)} className="underline">{ar ? 'إلغاء التنبيه' : 'Remove alert'}</button></div>)}</div>
    {message && <p role="status" className="text-sm text-shop-accent">{message}</p>}
  </section>
}
