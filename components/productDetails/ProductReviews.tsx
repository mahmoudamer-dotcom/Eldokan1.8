'use client'

import { useEffect, useMemo, useState } from 'react'
import { Star, MessageSquare, Pencil, Trash2 } from 'lucide-react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { readProductReviews, reviewStorageKey, saveProductReviews, type ProductReview } from '@/lib/product-reviews'

export default function ProductReviews({ productId }: { productId: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const [reviews, setReviews] = useState<ProductReview[]>([])
  const [loaded, setLoaded] = useState(false)
  const [rating, setRating] = useState(0)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [author, setAuthor] = useState('')
  const [title, setTitle] = useState('')
  const [comment, setComment] = useState('')
  const [filter, setFilter] = useState(0)
  const [sort, setSort] = useState('newest')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    function load() {
      if (!active) return
      try { setReviews(readProductReviews(productId)); setError('') }
      catch { setError(ar ? 'تعذر قراءة التعليقات المحفوظة على هذا الجهاز.' : 'Could not read reviews saved on this device.') }
      setLoaded(true)
    }
    queueMicrotask(load)
    const sync = (event: StorageEvent) => { if (event.key === reviewStorageKey(productId) || event.key === null) load() }
    window.addEventListener('storage', sync)
    return () => { active = false; window.removeEventListener('storage', sync) }
  }, [productId, ar])

  const visible = useMemo(() => reviews.filter((review) => !filter || review.rating === filter).sort((a, b) =>
    sort === 'highest' ? b.rating - a.rating || Date.parse(b.createdAt) - Date.parse(a.createdAt)
      : sort === 'lowest' ? a.rating - b.rating || Date.parse(b.createdAt) - Date.parse(a.createdAt)
        : Date.parse(b.createdAt) - Date.parse(a.createdAt)), [reviews, filter, sort])
  const average = reviews.length ? reviews.reduce((sum, review) => sum + review.rating, 0) / reviews.length : 0

  function reset() { setRating(0); setEditingId(null); setAuthor(''); setTitle(''); setComment('') }
  function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(''); setNotice('')
    if (!rating || !author.trim() || comment.trim().length < 10) {
      setError(text('Choose a rating, enter your name, and write at least 10 characters.', 'اختار تقييم، واكتب اسمك وتعليق لا يقل عن ١٠ حروف.')); return
    }
    try {
      const current = readProductReviews(productId)
      if (editingId && !current.some((review) => review.id === editingId)) throw new Error('review_changed')
      const review: ProductReview = { id: editingId ?? crypto.randomUUID(), productId, author: author.trim(), rating,
        title: title.trim(), comment: comment.trim(), createdAt: current.find((item) => item.id === editingId)?.createdAt ?? new Date().toISOString() }
      const next = [review, ...current.filter((item) => item.id !== review.id)]
      saveProductReviews(productId, next); setReviews(next); reset()
      setNotice(text('Saved on this device. Your review has not been published.', 'تم الحفظ على هذا الجهاز. تعليقك لم يُنشر للآخرين.'))
    } catch {
      setError(text('Could not save. Browser storage may be unavailable, the review may have changed, or the 50-review limit was reached.', 'تعذر الحفظ. قد يكون تخزين المتصفح غير متاح، أو التعليق اتغير، أو وصلت للحد الأقصى (٥٠ تعليق).'))
    }
  }
  function remove(id: string) {
    if (!window.confirm(text('Delete this review from this device?', 'حذف التعليق من هذا الجهاز؟'))) return
    try {
      const next = readProductReviews(productId).filter((review) => review.id !== id)
      saveProductReviews(productId, next); setReviews(next)
      if (editingId === id) reset()
      setNotice(text('Review deleted from this device.', 'تم حذف التعليق من هذا الجهاز.')); setError('')
    } catch { setError(text('Could not delete the review.', 'تعذر حذف التعليق.')) }
  }
  const field = 'w-full rounded-xl border border-input bg-card px-3 py-2.5 text-foreground focus-visible:outline-2 focus-visible:outline-amber-500'

  return <section id="product-reviews" dir={ar ? 'rtl' : 'ltr'} className="mt-8 scroll-mt-28 rounded-2xl border border-border bg-card p-5 sm:p-8" aria-labelledby="reviews-heading">
    <div className="flex flex-wrap items-center justify-between gap-3"><h2 id="reviews-heading" className="flex items-center gap-2 text-xl font-bold"><MessageSquare className="size-5 text-shop-accent" aria-hidden="true" />{text('Ratings & reviews', 'التقييمات والتعليقات')}</h2><span className="rounded-full bg-shop-soft px-3 py-1 text-xs font-semibold text-shop-accent">{text('Saved on this device', 'محفوظة على هذا الجهاز')}</span></div>
    <p className="mt-2 text-sm leading-6 text-muted-foreground">{text('Reviews added here are visible only in this browser. They are not public reviews or verified purchases, and clearing browser data removes them.', 'التعليقات هنا بتظهر في المتصفح ده بس، ومش تقييمات منشورة أو مشتريات موثقة. مسح بيانات المتصفح بيحذفها.')}</p>
    <div className="mt-6 grid items-start gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
      <aside className="rounded-xl bg-background p-5"><p className="text-4xl font-bold">{reviews.length ? average.toLocaleString(ar ? 'ar-EG' : 'en', { maximumFractionDigits: 1 }) : '—'} <span className="text-base font-normal text-muted-foreground">/ 5</span></p><p className="mt-1 text-sm text-muted-foreground">{reviews.length} {text('local reviews', 'تعليق محلي')}</p><div className="mt-5 space-y-3">{[5, 4, 3, 2, 1].map((stars) => {
        const count = reviews.filter((review) => review.rating === stars).length
        const percent = reviews.length ? Math.round(count / reviews.length * 100) : 0
        return <button key={stars} type="button" onClick={() => setFilter(filter === stars ? 0 : stars)} aria-pressed={filter === stars} className="flex w-full items-center gap-2 text-sm"><span className="flex items-center gap-1">{stars}<Star className="size-3 fill-amber-400 text-amber-500" aria-hidden="true" /></span><span className="h-2 flex-1 overflow-hidden rounded-full bg-muted"><span className="block h-full rounded-full bg-amber-400" style={{ width: `${percent}%` }} /></span><span className="w-10 text-end text-xs text-muted-foreground">{percent}%</span></button>
      })}</div></aside>
      <div className="min-w-0 space-y-6">
        <form onSubmit={submit} className="space-y-4 rounded-xl border border-border p-4 sm:p-5">
          <h3 className="font-bold">{editingId ? text('Edit your review', 'تعديل تعليقك') : text('Write a review', 'اكتب تقييمك')}</h3>
          <fieldset><legend className="mb-2 text-sm font-medium">{text('Your rating', 'تقييمك')}</legend><div className="flex gap-1" role="radiogroup" aria-label={text('Rating out of five', 'التقييم من خمس نجوم')}>{[1, 2, 3, 4, 5].map((stars) => <label key={stars} className="cursor-pointer rounded-lg p-2 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-amber-500"><input className="sr-only" type="radio" name="review-rating" value={stars} checked={rating === stars} onChange={() => setRating(stars)} required /><span className="sr-only">{stars} {text('stars', 'نجوم')}</span><Star aria-hidden="true" className={`size-7 ${stars <= rating ? 'fill-amber-400 text-amber-500' : 'text-muted-foreground'}`} /></label>)}</div></fieldset>
          <div className="grid gap-4 sm:grid-cols-2"><label className="space-y-1 text-sm font-medium"><span>{text('Display name', 'الاسم الظاهر')}</span><input required maxLength={60} value={author} onChange={(event) => setAuthor(event.target.value)} autoComplete="nickname" className={field} /></label><label className="space-y-1 text-sm font-medium"><span>{text('Headline (optional)', 'عنوان التعليق (اختياري)')}</span><input maxLength={100} value={title} onChange={(event) => setTitle(event.target.value)} className={field} /></label></div>
          <label className="block space-y-1 text-sm font-medium"><span>{text('Your experience', 'تجربتك مع المنتج')}</span><textarea required minLength={10} maxLength={2000} rows={4} value={comment} onChange={(event) => setComment(event.target.value)} className={field} placeholder={text('What did you like? What could be better? Do not include private contact details.', 'إيه اللي عجبك وإيه اللي محتاج يتحسن؟ بلاش تكتب بيانات تواصل خاصة.')} /><span className="block text-end text-xs text-muted-foreground">{comment.length}/2000</span></label>
          <div className="flex gap-3"><button disabled={!loaded} className="rounded-xl bg-[#f5b400] text-primary-foreground px-5 py-3 text-sm font-bold disabled:opacity-50">{text('Save review', 'حفظ التعليق')}</button>{editingId && <button type="button" onClick={reset} className="rounded-xl border px-5 py-3 text-sm">{text('Cancel', 'إلغاء')}</button>}</div>
        </form>
        {notice && <p role="status" className="rounded-lg bg-success-soft p-3 text-sm text-success-foreground">{notice}</p>}{error && <p role="alert" className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{error}</p>}
        <div className="flex flex-wrap gap-3"><label className="text-sm">{text('Filter', 'التصفية')}<select value={filter} onChange={(event) => setFilter(Number(event.target.value))} className={`${field} mt-1`}><option value={0}>{text('All ratings', 'كل التقييمات')}</option>{[5, 4, 3, 2, 1].map((stars) => <option key={stars} value={stars}>{stars} {text('stars', 'نجوم')}</option>)}</select></label><label className="text-sm">{text('Sort', 'الترتيب')}<select value={sort} onChange={(event) => setSort(event.target.value)} className={`${field} mt-1`}><option value="newest">{text('Newest first', 'الأحدث أولًا')}</option><option value="highest">{text('Highest rated', 'الأعلى تقييمًا')}</option><option value="lowest">{text('Lowest rated', 'الأقل تقييمًا')}</option></select></label></div>
        {!loaded ? <p className="text-sm text-muted-foreground">{text('Loading reviews…', 'جارٍ تحميل التعليقات…')}</p> : !visible.length ? <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">{filter ? text('No reviews match this rating.', 'لا توجد تعليقات بهذا التقييم.') : text('No reviews saved yet. Share your experience above.', 'مفيش تعليقات محفوظة لسه. اكتب تجربتك فوق.')}</p> : <ul className="divide-y divide-border">{visible.map((review) => <li key={review.id} className="space-y-2 py-5"><div className="flex flex-wrap justify-between gap-2"><strong className="break-words text-sm">{review.author}</strong><time dateTime={review.createdAt} className="text-xs text-muted-foreground">{new Date(review.createdAt).toLocaleDateString(ar ? 'ar-EG' : 'en-EG')}</time></div><div className="flex gap-1" aria-label={`${review.rating}/5`}>{[1, 2, 3, 4, 5].map((stars) => <Star key={stars} aria-hidden="true" className={`size-4 ${stars <= review.rating ? 'fill-amber-400 text-amber-500' : 'text-muted-foreground'}`} />)}</div>{review.title && <h3 className="break-words font-semibold">{review.title}</h3>}<p className="whitespace-pre-wrap break-words text-sm leading-7 text-foreground">{review.comment}</p><div className="flex gap-4 pt-1 text-xs text-muted-foreground"><button type="button" onClick={() => { setEditingId(review.id); setAuthor(review.author); setRating(review.rating); setTitle(review.title); setComment(review.comment); setNotice(''); setError('') }} className="flex items-center gap-1"><Pencil className="size-3" aria-hidden="true" />{text('Edit', 'تعديل')}</button><button type="button" onClick={() => remove(review.id)} className="flex items-center gap-1 text-danger-foreground"><Trash2 className="size-3" aria-hidden="true" />{text('Delete', 'حذف')}</button></div></li>)}</ul>}
      </div>
    </div>
  </section>
}
