'use client'

import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import { usePathname } from 'next/navigation'
import Image from 'next/image'
import Link from 'next/link'
import { MessageCircle, Send, X, RotateCcw, Check, ChevronDown } from 'lucide-react'
import type { AttributeOptionId, Category, FilterAttribute, ProductCard as CatalogProduct, ProductListParams } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'
import { translateCatalogName } from '@/lib/i18n'
import { clearSelectionDraft, readSelectionDraft, saveSelectionDraft, type SelectionDraft } from '@/lib/selection-journey-storage'
import ProductCompareButton from '@/components/productDetails/ProductCompareButton'

type OpenOptions = { categorySlug?: string; keywords?: string; resume?: boolean }
const Context = createContext<((options?: OpenOptions) => void) | null>(null)
export function useShoppingChat() {
  const value = useContext(Context)
  if (!value) throw new Error('ShoppingChatProvider is required')
  return value
}
export function ShoppingChatProvider({ children }: { children: React.ReactNode }) {
  const { locale } = useLocale()
  const pathname = usePathname()
  const [open, setOpen] = useState(false)
  const [started, setStarted] = useState(false)
  const [config, setConfig] = useState<{ id: number; options: OpenOptions }>({ id: 0, options: {} })
  const source = useRef<HTMLElement | null>(null)
  const show = useCallback((options: OpenOptions = {}) => {
    source.current = document.activeElement instanceof HTMLElement ? document.activeElement : null
    if (options.categorySlug || options.keywords || options.resume) setConfig(previous => ({ id: previous.id + 1, options }))
    setStarted(true); setOpen(true)
  }, [])
  const close = useCallback((restoreFocus = true) => {
    setOpen(false)
    if (restoreFocus) window.setTimeout(() => {
      if (source.current?.isConnected) source.current.focus()
      else document.querySelector<HTMLButtonElement>('[aria-controls="eldokan-shopping-chat"]')?.focus()
    }, 0)
  }, [])
  const hidden = pathname.startsWith('/checkout')
  return <Context.Provider value={show}>{children}
    {!hidden && !open && <button type="button" onClick={() => show()} aria-haspopup="dialog" aria-expanded={false} aria-controls="eldokan-shopping-chat" className="shopping-chat-launcher fixed bottom-[max(1rem,env(safe-area-inset-bottom))] end-4 z-50 inline-flex min-h-14 items-center gap-2 rounded-full bg-[#202124] px-5 text-sm font-bold text-white shadow-xl transition hover:bg-black focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary print:hidden"><MessageCircle aria-hidden="true" className="size-5 text-[#ffd45b]" /><span>{locale === 'ar' ? 'اسأل الدكان' : 'Ask Eldokan'}</span></button>}
    {started && <Chat key={`${locale}:${config.id}`} locale={locale} options={config.options} open={open && !hidden} close={close} />}
  </Context.Provider>
}

type Step = 'category' | 'budget' | 'keywords' | 'priority' | 'attribute' | 'confirm' | 'results'
type Settings = { category: { id: string; name: string; slug: string } | null; budget: string; purpose: SelectionDraft['purpose']; keywords: string; priority: SelectionDraft['priority']; selected: Record<string, AttributeOptionId> }
type Message = { id: number; role: 'assistant' | 'customer'; text: string }
const empty: Settings = { category: null, budget: '', purpose: 'daily', keywords: '', priority: 'price', selected: {} }
function numberText(value: string) {
  return value.replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit))).replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))).replace('٫', '.').trim()
}
function validBudget(value: string) {
  const clean = numberText(value)
  return /^\d+(\.\d{1,2})?$/.test(clean) && Number(clean) > 0 && Number(clean) <= 100000000
}
function priceOf(product: CatalogProduct) {
  const money = (product.pricing.on_sale ? product.pricing.sale_price : null) ?? product.pricing.price ?? product.pricing.min_price ?? product.pricing.regular_price
  if (!money || money.currency !== 'EGP' || !Number.isFinite(money.amount) || money.amount < 0 || !Number.isInteger(money.decimals) || money.decimals < 0 || money.decimals > 6) return null
  return money.amount / 10 ** money.decimals
}
function Chat({ locale, options, open, close }: { locale: 'ar' | 'en'; options: OpenOptions; open: boolean; close: (restoreFocus?: boolean) => void }) {
  const ar = locale === 'ar'
  const text = useCallback((en: string, arabic: string) => ar ? arabic : en, [ar])
  const api = createEldokanApi(locale)
  const [messages, setMessages] = useState<Message[]>([{ id: 0, role: 'assistant', text: text('Hi! I’m Eldokan’s automatic product selector. Let’s find products based on your needs and budget. Which department shall we start with?', 'أهلًا بيك! أنا مساعد الاختيار التلقائي للدكان. خلينا نلاقي منتجات حسب احتياجك وميزانيتك. نبدأ بأنهي قسم؟') }])
  const [step, setStep] = useState<Step>('category')
  const [settings, setSettings] = useState<Settings>({ ...empty, keywords: options.keywords?.slice(0, 100) ?? '' })
  const prefs = useRef(settings)
  const [categories, setCategories] = useState<Category[]>([])
  const [filters, setFilters] = useState<FilterAttribute[]>([])
  const [attributeIndex, setAttributeIndex] = useState(0)
  const [products, setProducts] = useState<CatalogProduct[]>([])
  const [budgetPrices, setBudgetPrices] = useState<Array<{ amount: number; starting: boolean }>>([])
  const [budgetPricesFailed, setBudgetPricesFailed] = useState(false)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [input, setInput] = useState('')
  const [busy, setBusy] = useState(true)
  const [error, setError] = useState('')
  const [draft, setDraft] = useState<SelectionDraft | null>(null)
  const sequence = useRef(0)
  const request = useRef(0)
  const retry = useRef<(() => void) | null>(null)
  const editingBudget = useRef(false)
  const inputRef = useRef<HTMLInputElement>(null)
  const scrollRef = useRef<HTMLDivElement>(null)
  const chips = 'min-h-10 rounded-2xl border border-border bg-card px-3 py-2 text-start text-xs font-semibold text-card-foreground transition hover:border-primary focus-visible:outline-primary disabled:opacity-50'
  const categoryName = (category: { name: string }) => translateCatalogName(category.name, locale)
  const add = (role: Message['role'], message: string) => setMessages(previous => [...previous, { id: ++sequence.current, role, text: message }].slice(-60))
  function commit(partial: Partial<Settings>) {
    const next = { ...prefs.current, ...partial }
    prefs.current = next; setSettings(next)
    if (next.category) saveSelectionDraft({ version: 1, categorySlug: next.category.slug, budget: next.budget, purpose: next.purpose, keywords: next.keywords, priority: next.priority, selected: next.selected, step: validBudget(next.budget) ? 3 : 2 })
    return next
  }
  function ask(next: Step, message: string) { setStep(next); add('assistant', message); setInput('') }
  function confirm() {
    ask('confirm', text('Ready. Shall I show available products within your budget and chosen specifications? Shipping is calculated later at checkout.', 'تمام، أعرضلك المنتجات المتاحة في حدود الميزانية والمواصفات اللي اخترتها؟ الشحن بيتحسب بعد كده عند إتمام الشراء.'))
  }
  async function loadBudgetPrices(categorySlug: string, token: number) {
    const responses = await Promise.allSettled([
      api.products.list({ category: categorySlug, stockStatus: 'in_stock', sort: 'price_asc', perPage: 24 }),
      api.products.list({ category: categorySlug, stockStatus: 'in_stock', sort: 'price_desc', perPage: 24 }),
    ])
    if (request.current !== token) return
    const prices = new Map<number, { amount: number; starting: boolean }>()
    for (const response of responses) {
      if (response.status !== 'fulfilled') continue
      for (const product of response.value.data) {
        const amount = priceOf(product)
        if (product.stock.status !== 'in_stock' || amount === null || !validBudget(String(amount))) continue
        const existing = prices.get(amount)
        prices.set(amount, { amount, starting: Boolean(existing?.starting || product.type === 'variable') })
      }
    }
    const sorted = [...prices.values()].sort((a, b) => a.amount - b.amount)
    const suggestions = sorted.length <= 8 ? sorted : Array.from({ length: 8 }, (_, index) => sorted[Math.round(index * (sorted.length - 1) / 7)])
    setBudgetPrices(suggestions)
    setBudgetPricesFailed(responses.every(response => response.status === 'rejected'))
  }
  async function changeBudget() {
    if (busy || !prefs.current.category) return
    editingBudget.current = true
    setProducts([]); setBudgetPrices([]); setBudgetPricesFailed(false); setBusy(true)
    ask('budget', text('Choose a price from products in this department as your maximum budget, or write another amount.', 'اختار سعر من منتجات القسم كحد أقصى لميزانيتك، أو اكتب مبلغ تاني.'))
    const token = ++request.current
    await loadBudgetPrices(prefs.current.category.slug, token)
    if (request.current === token) setBusy(false)
  }
  async function chooseCategory(category: { id: string; name: string; slug: string }, announce = true) {
    if (announce) add('customer', categoryName(category))
    commit({ category, selected: {} }); setProducts([]); setBudgetPrices([]); setBudgetPricesFailed(false); setBusy(true); setError(''); setDraft(null)
    const token = ++request.current
    try {
      const response = await api.categories.filters(category.slug)
      if (request.current === token) setFilters(response.data.attributes.filter(filter => filter.id && filter.options.some(option => option.id)))
    } catch {
      if (request.current === token) { setFilters([]); add('assistant', text('Detailed specifications are unavailable right now; we can still use budget and search.', 'المواصفات التفصيلية مش متاحة دلوقتي، بس نقدر نكمل بالميزانية والبحث.')) }
    } finally {
      if (request.current === token) {
        await loadBudgetPrices(category.slug, token)
        if (request.current === token) { setBusy(false); ask('budget', text('These are prices from products in this department. Choose one as your maximum budget, or write another amount.', 'دي أسعار من منتجات القسم. اختار منها حد أقصى لميزانيتك، أو اكتب مبلغ تاني.')) }
      }
    }
  }
  async function initialize() {
    const token = ++request.current
    const saved = readSelectionDraft()
    setDraft(saved)
    if (options.resume && saved) { await restore(saved); return }
    try {
      const response = await api.categories.list()
      if (request.current !== token) return
      setCategories(response.data.filter(category => category.slug))
      if (options.categorySlug) {
        const category = response.data.find(category => category.slug === options.categorySlug) ?? (await api.categories.get(options.categorySlug)).data
        if (request.current !== token) return
        await chooseCategory(category)
      }
    } catch {
      if (request.current === token) { setError(text('Could not load departments. Try again.', 'تعذر تحميل الأقسام. حاول مرة أخرى.')); retry.current = () => { setBusy(true); setError(''); void initialize() } }
    } finally { if (request.current === token) setBusy(false) }
  }
  useEffect(() => {
    const counter = request
    queueMicrotask(() => { void initialize() })
    return () => { ++counter.current }
    // Initialize once for this language/context; opening again preserves the conversation.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])
  useEffect(() => {
    if (open && !busy) {
      const timer = window.setTimeout(() => inputRef.current?.focus(), 0)
      return () => window.clearTimeout(timer)
    }
  }, [open, busy])
  useEffect(() => {
    if (open && scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight
  }, [messages, products, busy, open, error, draft])
  async function restore(saved: SelectionDraft) {
    setBusy(true); setError(''); setDraft(null)
    const token = ++request.current
    try {
      const category = (await api.categories.get(saved.categorySlug)).data
      let available: FilterAttribute[] = []
      try { available = (await api.categories.filters(saved.categorySlug)).data.attributes.filter(filter => filter.id && filter.options.some(option => option.id)) } catch { /* Budget/search still work. */ }
      if (request.current !== token) return
      const selected = Object.fromEntries(available.flatMap(filter => filter.options.some(option => option.id === saved.selected[filter.id]) ? [[filter.id, saved.selected[filter.id]]] : []))
      setFilters(available)
      commit({ category, budget: saved.budget, purpose: saved.purpose, keywords: saved.keywords, priority: saved.priority, selected })
      add('customer', text('Continue my saved selection.', 'كمّل اختياراتي المحفوظة.'))
      add('assistant', `${categoryName(category)} · ${text('Budget:', 'الميزانية:')} ${saved.budget} ${text('EGP', 'جنيه')}`)
      if (Object.keys(selected).length < Object.keys(saved.selected).length) add('assistant', text('Some saved specifications are no longer available. You can review them before searching.', 'بعض المواصفات المحفوظة مبقتش متاحة. تقدر تراجعها قبل البحث.'))
      if (validBudget(saved.budget)) confirm()
      else { await loadBudgetPrices(category.slug, token); if (request.current === token) ask('budget', text('Choose a price from products in this department as your maximum budget, or write another amount.', 'اختار سعر من منتجات القسم كحد أقصى لميزانيتك، أو اكتب مبلغ تاني.')) }
    } catch {
      if (request.current === token) { setError(text('Could not restore your selection. Retry or start again.', 'تعذر استعادة الاختيارات. حاول مرة أخرى أو ابدأ من جديد.')); retry.current = () => { void restore(saved) } }
    } finally { if (request.current === token) setBusy(false) }
  }
  async function findProducts(nextPage = 1) {
    const current = prefs.current
    if (!current.category || !validBudget(current.budget)) return
    setBusy(true); setError(''); setStep('results')
    if (nextPage === 1) { setProducts([]); setPage(1); setTotalPages(1) }
    const token = ++request.current
    const attributes = filters.flatMap(filter => current.selected[filter.id] ? [{ attributeId: filter.id, optionIds: [current.selected[filter.id]] }] : [])
    const params: ProductListParams = { category: current.category.slug, maxPrice: Number(numberText(current.budget)), stockStatus: 'in_stock', search: current.keywords.trim() || undefined, sort: current.priority === 'rating' ? 'rating' : 'price_asc', onSale: current.priority === 'sale' ? true : undefined, attributes: attributes.length ? attributes : undefined, page: nextPage, perPage: 6 }
    try {
      const response = await api.products.list(params)
      if (request.current !== token) return
      const matches = response.data.filter(product => product.stock.status === 'in_stock' && priceOf(product) !== null && priceOf(product)! <= Number(numberText(current.budget)) && (current.priority !== 'sale' || product.pricing.on_sale))
      setProducts(previous => nextPage === 1 ? matches : [...new Map([...previous, ...matches].map(product => [product.id, product])).values()])
      setPage(response.meta.page); setTotalPages(Math.max(1, response.meta.total_pages))
      if (nextPage === 1) add('assistant', matches.length ? text('Here are products matching your catalog filters. Open a product for full details, or compare your favorites. Variable products may show starting prices.', 'دي منتجات مطابقة لفلاتر المتجر. افتح المنتج للتفاصيل أو قارن اللي عجبك. المنتجات متعددة الاختيارات ممكن تعرض سعر ابتدائي.') : text('No matching products on this page. You can change the budget or keywords, review specifications, or load more pages if available.', 'مفيش منتجات مطابقة في الصفحة دي. تقدر تغيّر الميزانية أو كلمة البحث، تراجع المواصفات، أو تحمّل صفحات أكتر لو متاحة.'))
    } catch {
      if (request.current === token) { setError(text('Could not reach the catalog. Your choices are kept.', 'تعذر الوصول للمنتجات. اختياراتك محفوظة.')); retry.current = () => { void findProducts(nextPage) } }
    } finally { if (request.current === token) setBusy(false) }
  }
  function startAttributes(index = 0) {
    if (index >= filters.length) { confirm(); return }
    setAttributeIndex(index)
    ask('attribute', `${text('Any preference for', 'عندك تفضيل لـ')} ${filters[index].name}${ar ? '؟ ممكن تتخطّى السؤال.' : '? You can skip this question.'}`)
  }
  function pickAttribute(option?: { id: AttributeOptionId; name: string }) {
    const filter = filters[attributeIndex]
    if (!filter) { confirm(); return }
    add('customer', option?.name ?? text('No preference', 'بدون تفضيل'))
    const selected = { ...prefs.current.selected }
    if (option) selected[filter.id] = option.id; else delete selected[filter.id]
    commit({ selected }); startAttributes(attributeIndex + 1)
  }
  function choosePriority(id: Settings['priority']) {
    add('customer', id === 'rating' ? text('Highest catalog ratings', 'أعلى تقييمات المتجر') : id === 'sale' ? text('Discounts only', 'خصومات فقط') : text('Lowest price first', 'الأقل سعرًا الأول'))
    commit({ priority: id }); startAttributes()
  }
  function chooseBudget(value: string, label = value) {
    if (busy || step !== 'budget') return
    add('customer', label)
    setInput('')
    if (!validBudget(value)) { add('assistant', text('Choose a budget below, or enter a positive amount like 1500 with at most two decimal places.', 'اختار ميزانية من اللي تحت، أو اكتب مبلغ أكبر من صفر زي 1500، بحد أقصى رقمين بعد العلامة العشرية.')); return }
    commit({ budget: numberText(value) })
    if (editingBudget.current) { editingBudget.current = false; void findProducts(); return }
    ask('keywords', text('Any product name or feature keyword? Type it, or skip.', 'في اسم منتج أو ميزة معينة بتدور عليها؟ اكتب كلمة أو تخطّى.'))
    setInput(prefs.current.keywords)
  }
  function submit() {
    const value = input.trim()
    if (!value || busy) return
    setInput('')
    if (step === 'budget') { chooseBudget(value); return }
    if (step === 'keywords' || step === 'results') {
      add('customer', value); commit({ keywords: value.slice(0, 100) })
      if (step === 'results') { void findProducts(); return }
      ask('priority', text('What should come first in your results?', 'إيه تحب يكون الأول في النتائج؟')); return
    }
    if (step === 'category') {
      const found = categories.filter(category => `${categoryName(category)} ${category.name} ${category.slug}`.toLocaleLowerCase().includes(value.toLocaleLowerCase()))
      if (found.length === 1) { void chooseCategory(found[0]); return }
    }
    const normalized = value.toLocaleLowerCase()
    if (step === 'priority') {
      if (['الأقل سعرًا الأول', 'الأقل سعر', 'السعر', 'price', 'lowest price'].includes(normalized)) { choosePriority('price'); return }
      if (['أعلى تقييم', 'التقييم', 'rating', 'ratings'].includes(normalized)) { choosePriority('rating'); return }
      if (['خصومات', 'خصومات فقط', 'خصم', 'sale', 'discounts'].includes(normalized)) { choosePriority('sale'); return }
    }
    if (step === 'confirm' && ['نعم', 'تمام', 'اعرض', 'وريني', 'وريني المنتجات', 'yes', 'show', 'show my products'].includes(normalized)) {
      add('customer', value); void findProducts(); return
    }
    if (step === 'attribute') {
      const found = filters[attributeIndex]?.options.find(option => option.id && option.name.toLocaleLowerCase() === value.toLocaleLowerCase())
      if (found?.id) { pickAttribute({ id: found.id, name: found.name }); return }
    }
    add('customer', value); add('assistant', text('Please choose one of the replies below so I can apply the correct catalog filter.', 'اختار رد من اللي تحت علشان أطبّق فلتر المتجر الصحيح.'))
  }
  function reset() {
    ++request.current; clearSelectionDraft(); prefs.current = { ...empty }; setSettings({ ...empty }); setFilters([]); setProducts([]); setBudgetPrices([]); setBudgetPricesFailed(false); setDraft(null); setInput(''); setError(''); setBusy(false); setStep('category'); editingBudget.current = false
    setMessages([{ id: ++sequence.current, role: 'assistant', text: text('Let’s start fresh. Which department?', 'نبدأ من جديد. تختار أنهي قسم؟') }])
    if (categories.length === 0) { setBusy(true); void initialize() }
  }
  return <section id="eldokan-shopping-chat" role="dialog" aria-modal="false" aria-labelledby="shopping-chat-title" dir={ar ? 'rtl' : 'ltr'} hidden={!open} onKeyDown={event => { if (event.key === 'Escape') { event.stopPropagation(); close() } }} className="shopping-chat fixed inset-x-2 bottom-2 z-50 flex h-[min(720px,calc(100dvh-1rem))] flex-col overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-2xl sm:inset-x-auto sm:bottom-20 sm:end-5 sm:h-[min(720px,80dvh)] sm:w-[440px] print:hidden">
    <header className="flex shrink-0 items-center justify-between gap-3 bg-[#202124] px-4 py-4 text-white"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-full bg-white/10"><MessageCircle aria-hidden="true" className="size-5 text-[#ffd45b]" /></span><div><h2 id="shopping-chat-title" className="text-sm font-bold">{text('Chat with Eldokan', 'شات الدكان')}</h2><p className="mt-1 text-[11px] text-white/70">{text('Automatic product selection assistant', 'مساعد تلقائي لاختيار المنتجات')}</p></div></div><div className="flex gap-1"><button type="button" disabled={busy} onClick={reset} title={text('Clear and restart', 'مسح وبدء من جديد')} aria-label={text('Clear and restart', 'مسح وبدء من جديد')} className="rounded-full p-2 hover:bg-white/10 disabled:opacity-40"><RotateCcw className="size-4" /></button><button type="button" onClick={() => close()} aria-label={text('Close chat', 'إغلاق الشات')} className="rounded-full p-2 hover:bg-white/10"><X className="size-5" /></button></div></header>
    <div ref={scrollRef} className="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-background px-3 py-4" onClick={event => { const anchor = (event.target as HTMLElement).closest('a'); if (anchor?.getAttribute('href')?.startsWith('/product/')) close(false) }}>
      <div role="log" aria-live="polite" aria-relevant="additions text" aria-label={text('Selection conversation', 'محادثة الاختيار')} className="space-y-3">{messages.map(message => <div key={message.id} className={`flex ${message.role === 'customer' ? 'justify-end' : 'justify-start'}`}><p className={`max-w-[90%] whitespace-pre-wrap break-words rounded-2xl px-3.5 py-3 text-sm leading-7 ${message.role === 'customer' ? 'shopping-chat-answer rounded-ee-sm bg-primary' : 'rounded-es-sm border border-border bg-card text-card-foreground'}`}><span className="sr-only">{message.role === 'customer' ? text('You: ', 'أنت: ') : text('Eldokan: ', 'الدكان: ')}</span>{message.text}</p></div>)}</div>
      {busy && <p role="status" className="mt-3 inline-flex items-center gap-2 rounded-2xl border border-border bg-card px-4 py-3 text-xs text-muted-foreground"><span className="flex gap-1" aria-hidden="true"><span className="size-1.5 animate-pulse rounded-full bg-primary" /><span className="size-1.5 animate-pulse rounded-full bg-primary" /><span className="size-1.5 animate-pulse rounded-full bg-primary" /></span>{text('Checking the catalog…', 'براجع بيانات المتجر…')}</p>}
      {error && <div role="alert" className="shopping-chat-error mt-3 rounded-xl border border-danger-foreground/40 p-3 text-sm text-danger-foreground"><p>{error}</p><button type="button" disabled={busy} onClick={() => retry.current?.()} className="mt-2 underline">{text('Try again', 'حاول مرة أخرى')}</button></div>}
      {!busy && <div className="mt-4 flex flex-wrap gap-2" aria-label={text('Suggested replies', 'ردود مقترحة')}>
        {step === 'category' && <>{draft && <button type="button" className={chips} onClick={() => void restore(draft)}>{text('Continue saved choices', 'كمّل اختياراتي المحفوظة')}</button>}{categories.map(category => <button type="button" key={category.slug} className={chips} onClick={() => void chooseCategory(category)}>{categoryName(category)}</button>)}</>}
        {step === 'budget' && <>
          <p className="w-full text-xs leading-6 text-muted-foreground">{text('Suggested prices from available products in this department, excluding shipping. Choosing a price sets your maximum budget; it does not select a product.', 'أسعار مقترحة من منتجات القسم المتاحة، من غير الشحن. اختيار السعر بيحدد أقصى ميزانية، مش بيختار منتج بعينه.')}</p>
          {budgetPrices.map(({ amount, starting }) => {
            const label = text(`Up to ${amount.toLocaleString('en-EG')} EGP`, `حتى ${amount.toLocaleString('ar-EG')} جنيه`)
            return <button type="button" key={amount} className={chips} onClick={() => chooseBudget(String(amount), label)}>{label}{starting && <span className="ms-1 text-[10px] text-muted-foreground">{text('· starting price', '· سعر ابتدائي')}</span>}</button>
          })}
          {budgetPrices.length === 0 && <p className="w-full text-xs text-muted-foreground">{budgetPricesFailed ? text('Could not load product prices. Retry or write your budget.', 'تعذر تحميل أسعار المنتجات. حاول تاني أو اكتب ميزانيتك.') : text('No available product prices were returned. You can write your budget.', 'مفيش أسعار منتجات متاحة رجعت من القسم. ممكن تكتب ميزانيتك.')}</p>}
          {budgetPricesFailed && <button type="button" className={chips} onClick={() => { if (prefs.current.category) { setBusy(true); const token = ++request.current; void loadBudgetPrices(prefs.current.category.slug, token).finally(() => { if (request.current === token) setBusy(false) }) } }}>{text('Retry prices', 'إعادة تحميل الأسعار')}</button>}
          <button type="button" className={chips} onClick={() => inputRef.current?.focus()}>{text('Write another amount', 'اكتب مبلغ تاني')}</button>
        </>}
        {step === 'keywords' && <button type="button" className={chips} onClick={() => { add('customer', text('Skip', 'تخطّى')); commit({ keywords: '' }); ask('priority', text('What should come first in your results?', 'إيه تحب يكون الأول في النتائج؟')) }}>{text('Skip', 'تخطّى')}</button>}
        {step === 'priority' && <>{(['price', 'rating', 'sale'] as const).map(id => <button type="button" key={id} className={chips} onClick={() => choosePriority(id)}>{id === 'rating' ? text('Highest ratings', 'أعلى تقييم') : id === 'sale' ? text('Discounts only', 'خصومات فقط') : text('Lowest price first', 'الأقل سعرًا الأول')}</button>)}</>}
        {step === 'attribute' && <>{filters[attributeIndex]?.options.filter(option => option.id).map(option => <button type="button" key={option.id} className={chips} onClick={() => pickAttribute({ id: option.id!, name: option.name })}>{option.name}</button>)}<button type="button" className={chips} onClick={() => pickAttribute()}>{text('No preference', 'بدون تفضيل')}</button><button type="button" className={chips} onClick={confirm}>{text('Keep remaining specifications open', 'كمّل بدون مواصفات إضافية')}</button></>}
        {step === 'confirm' && <><button type="button" className={chips} onClick={() => { add('customer', text('Show my products', 'وريني المنتجات')); void findProducts() }}><Check aria-hidden="true" className="me-1 inline size-3" />{text('Show my products', 'وريني المنتجات')}</button><button type="button" className={chips} onClick={() => startAttributes()}>{text('Review specifications', 'راجع المواصفات')}</button></>}
      </div>}
      {step === 'results' && products.length > 0 && <div className="mt-4 space-y-3">{products.map(product => <ChatProduct key={product.id} product={product} locale={locale} />)}</div>}
      {step === 'results' && !busy && <div className="mt-4 flex flex-wrap gap-2">{page < totalPages && <button type="button" className={chips} onClick={() => void findProducts(page + 1)}><ChevronDown className="me-1 inline size-3" />{text('More matches', 'اختيارات أكتر')}</button>}<button type="button" className={chips} onClick={() => void changeBudget()}>{text('Change budget', 'غيّر الميزانية')}</button><button type="button" className={chips} onClick={() => { setProducts([]); startAttributes() }}>{text('Change specifications', 'غيّر المواصفات')}</button><button type="button" className={chips} onClick={reset}>{text('Start again', 'ابدأ من جديد')}</button></div>}
    </div>
    <form onSubmit={event => { event.preventDefault(); submit() }} className="shrink-0 border-t border-border bg-card p-3 pb-[max(.75rem,env(safe-area-inset-bottom))]"><label htmlFor="shopping-chat-input" className="sr-only">{text('Your reply', 'ردّك')}</label><div className="flex items-center gap-2"><input ref={inputRef} id="shopping-chat-input" disabled={busy} value={input} maxLength={step === 'budget' ? 20 : 100} inputMode={step === 'budget' ? 'decimal' : 'text'} onChange={event => setInput(event.target.value)} placeholder={step === 'budget' ? text('Or write your budget, e.g. 1500', 'أو اكتب ميزانيتك، مثلًا 1500') : step === 'results' ? text('Search a different product keyword…', 'اكتب كلمة بحث مختلفة…') : text('Type a reply or choose above…', 'اكتب ردّك أو اختار من فوق…')} className="min-w-0 flex-1 rounded-full border border-input bg-background px-4 py-3 text-sm text-foreground outline-none focus:border-primary" /><button type="submit" disabled={busy || !input.trim()} aria-label={text('Send reply', 'إرسال الرد')} className="shopping-chat-answer grid size-11 shrink-0 place-items-center rounded-full bg-primary disabled:opacity-40"><Send aria-hidden="true" className={`size-4 ${ar ? 'rotate-180' : ''}`} /></button></div><p className="mt-2 text-center text-[10px] leading-5 text-muted-foreground">{text('Guided catalog selection · not a human support agent', 'اختيار موجّه من المتجر · ليس موظف دعم بشري')}</p></form>
  </section>
}

function ChatProduct({ product, locale }: { product: CatalogProduct; locale: 'ar' | 'en' }) {
  const ar = locale === 'ar'
  const price = (product.pricing.on_sale ? product.pricing.sale_price : null) ?? product.pricing.price ?? product.pricing.min_price ?? product.pricing.regular_price
  return <article className="rounded-2xl border border-border bg-card p-3 text-card-foreground"><div className="flex gap-3">{product.image?.url && <Image src={product.image.url} alt={product.image.alt || product.name} width={80} height={80} className="size-20 shrink-0 rounded-xl bg-card object-contain p-1" />}<div className="min-w-0 flex-1"><Link href={`/product/${product.id}`} className="line-clamp-2 text-sm font-semibold leading-6 hover:underline">{product.name}</Link><p className="mt-1 text-sm font-bold">{price?.formatted}</p><p className="mt-1 text-[11px] text-muted-foreground">{ar ? 'داخل ميزانيتك' : 'Within your budget'}{product.type === 'variable' ? (ar ? ' · سعر ابتدائي' : ' · starting price') : ''}</p></div></div><div className="mt-3 flex items-center justify-between gap-2 border-t border-border pt-2"><Link href={`/product/${product.id}`} className="rounded-lg px-2 py-2 text-xs font-semibold hover:underline">{ar ? 'شوف التفاصيل' : 'View details'}</Link><ProductCompareButton productId={product.id} /></div></article>
}
