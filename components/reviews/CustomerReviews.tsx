'use client'

import Link from 'next/link'
import ReviewExtras from './ReviewExtras'
import { useCallback, useEffect, useRef, useState } from 'react'
import { Star } from 'lucide-react'
import { EldokanClientError, type CustomerReview, type ProductId, type SellerId, type ReviewListResponse, type ReviewMineResponse } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function CustomerReviews({ target }: { target: ProductId | SellerId }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const text = (en: string, arabic: string) => ar ? arabic : en
  const [result, setResult] = useState<ReviewListResponse | null>(null)
  const [mine, setMine] = useState<ReviewMineResponse['data'] | null>(null)
  const [signedIn, setSignedIn] = useState(false)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [page, setPage] = useState(1)
  const [filter, setFilter] = useState(0)
  const [sort, setSort] = useState<'newest' | 'highest' | 'lowest'>('newest')
  const [rating, setRating] = useState(0)
  const [title, setTitle] = useState('')
  const [comment, setComment] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [deleteConfirm, setDeleteConfirm] = useState(false)
  const [photos, setPhotos] = useState<File[]>([])
  const generation = useRef(0)
  const loadedDraft = useRef<string | null>(null)
  const load = useCallback(async () => {
    const version = ++generation.current
    const api = createEldokanApi(locale)
    setLoading(true); setError('')
    const publishedPromise = api.reviews.list(target, { page, perPage: 10, rating: filter, sort })
    const ownPromise = api.reviews.mine(target).then(response => ({ signedIn: true, data: response.data })).catch((cause: unknown) => {
      if (cause instanceof EldokanClientError && cause.status === 401) return { signedIn: false, data: null }
      throw cause
    })
    const [published, own] = await Promise.allSettled([publishedPromise, ownPromise])
    if (version !== generation.current) return
    if (published.status === 'fulfilled') setResult(published.value)
    else { setResult(null); setError(ar ? 'تعذر تحميل التقييمات. جرّب التحديث.' : 'Could not load reviews. Please refresh.') }
    if (own.status === 'fulfilled') {
      setSignedIn(own.value.signedIn); setMine(own.value.data)
      const review = own.value.data?.review
      const draftKey = JSON.stringify([target, own.value.signedIn, review?.id, review?.rating, review?.title, review?.comment])
      if (loadedDraft.current !== draftKey) {
        loadedDraft.current = draftKey
        setRating(review?.rating ?? 0); setTitle(review?.title ?? ''); setComment(review?.comment ?? '')
      }
    } else { setMine(null); setError(ar ? 'تعذر تحميل صلاحية التقييم. جرّب التحديث.' : 'Could not load your review eligibility. Please refresh.') }
    setLoading(false)
  }, [target, locale, page, filter, sort, ar])
  useEffect(() => {
    let active = true
    queueMicrotask(() => { if (active) void load() })
    const refresh = () => { void load() }
    const invalidate = () => { generation.current += 1 }
    window.addEventListener('eldokan:session-changed', refresh)
    return () => { active = false; invalidate(); window.removeEventListener('eldokan:session-changed', refresh) }
  }, [load])
  function failure(cause: unknown) {
    const code = cause instanceof EldokanClientError ? cause.code : ''
    const messages: Record<string, [string, string]> = {
      purchase_required: ['A qualifying purchase is required before reviewing.', 'تقدر تقيّم بعد الشراء.'],
      reviews_closed: ['Reviews are closed for this product.', 'التقييمات غير متاحة للمنتج ده حاليًا.'],
      review_exists: ['You already submitted a review. Refresh to edit it.', 'عندك تقييم بالفعل. حدّث التعليقات علشان تعدّله.'],
      invalid_review: ['Choose a rating and write a non-empty review of at most 2000 characters.', 'اختار تقييم واكتب تعليق، بحد أقصى ٢٠٠٠ حرف.'],
      review_rate_limited: ['Wait a minute before submitting another review.', 'استنى دقيقة قبل إرسال تقييم تاني.'],
      review_moderated: ['This review is being moderated. Contact the store.', 'التقييم ده تحت المراجعة. تواصل مع المتجر.'],
    }
    const message = code ? messages[code] : undefined
    setError(message ? text(...message) : text('Could not complete the request. Refresh and try again.', 'تعذر إكمال الطلب. حدّث التقييمات وحاول مرة تانية.'))
  }
  async function submit(event: React.FormEvent) {
    event.preventDefault()
    if (busy || !mine?.can_review) return
    if (!rating || !comment.trim() || [...comment.trim()].length > 2000) {
      setError(text('Choose a rating and write your review.', 'اختار تقييم واكتب تعليقك.'))
      return
    }
    setBusy(true); setError(''); setNotice('')
    try {
      const api = createEldokanApi(locale); await api.auth.session()
      const input = { rating, title: title.trim(), comment: comment.trim() }
      const saved = mine.review ? await api.reviews.update(target, mine.review.id, input) : await api.reviews.create(target, input)
      // Reload the saved identity before uploading so an upload failure cannot retry creation.
      await load()
      for (const file of photos) {
        const image = await new Promise<string>((resolve, reject) => {
          const reader = new FileReader()
          reader.onload = () => resolve(String(reader.result))
          reader.onerror = () => reject(new Error('Image could not be read'))
          reader.readAsDataURL(file)
        })
        await api.commerce.addReviewImage(saved.data.id, image)
        setPhotos(previous => previous.filter(photo => photo !== file))
      }
      setPhotos([])
      await load()
      setNotice(text('Saved. Your review will appear after moderation.', 'تم الحفظ. تقييمك هيظهر بعد المراجعة.'))
    } catch (cause) { failure(cause) } finally { setBusy(false) }
  }
  async function remove() {
    if (!mine?.review || busy) return
    setBusy(true); setError(''); setNotice('')
    try {
      const api = createEldokanApi(locale); await api.auth.session()
      await api.reviews.remove(target, mine.review.id)
      setDeleteConfirm(false); await load(); setNotice(text('Your review was deleted.', 'تم حذف تقييمك.'))
    } catch (cause) { failure(cause) } finally { setBusy(false) }
  }
  const field = 'w-full rounded-xl border border-input bg-background px-3 py-2.5 text-foreground'
  const summary = result?.data.summary
  const reviewCard = (review: CustomerReview) => <article className="space-y-2 py-5">
    <div className="flex flex-wrap justify-between gap-2"><strong className="text-sm" dir="auto">{review.author}</strong><time dateTime={review.created_at} className="text-xs text-muted-foreground">{new Date(review.created_at).toLocaleDateString(ar ? 'ar-EG' : 'en-EG')}</time></div>
    <div className="flex flex-wrap items-center gap-3"><div className="flex gap-1" aria-label={`${review.rating}/5`}>{[1,2,3,4,5].map(stars => <Star key={stars} aria-hidden="true" className={`size-4 ${stars <= review.rating ? 'fill-amber-400 text-amber-500' : 'text-muted-foreground'}`} />)}</div>{review.verified_purchase && <span className="text-xs text-success-foreground">{text('Verified purchase', 'شراء موثّق')}</span>}</div>
    <ReviewExtras review={review} />
    {review.title && <h3 className="break-words font-semibold" dir="auto">{review.title}</h3>}<p className="whitespace-pre-wrap break-words text-sm leading-7" dir="auto">{review.comment}</p>
  </article>
  return <section id={target.startsWith('sel_') ? 'seller-reviews' : 'product-reviews'} className="mt-8 scroll-mt-28 rounded-2xl border border-border bg-card p-5 sm:p-8" aria-labelledby={`reviews-${target}`}>
    <div className="flex flex-wrap items-center justify-between gap-3"><h2 id={`reviews-${target}`} className="text-xl font-bold">{target.startsWith('sel_') ? text('Seller ratings & reviews', 'تقييمات البائع وتعليقات المشترين') : text('Ratings & reviews', 'التقييمات والتعليقات')}</h2><button type="button" disabled={loading || busy} onClick={() => { void load() }} className="rounded-lg border px-3 py-2 text-sm disabled:opacity-50">{text('Refresh reviews', 'تحديث التقييمات')}</button></div>
    <div className="mt-6 grid items-start gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]"><aside className="rounded-xl bg-background p-5"><p className="text-4xl font-bold">{summary?.average != null ? summary.average.toLocaleString(ar ? 'ar-EG' : 'en-EG', { maximumFractionDigits: 1 }) : '—'} <span className="text-base text-muted-foreground">/ 5</span></p><p className="mt-2 text-sm text-muted-foreground">{summary?.count ?? 0} {text('published reviews', 'تقييم منشور')}</p><div className="mt-5 space-y-3">{[5,4,3,2,1].map(stars => {
      const count = summary?.distribution[String(stars)] ?? 0
      const percent = summary?.count ? Math.round(count / summary.count * 100) : 0
      return <button key={stars} type="button" disabled={busy} aria-pressed={filter === stars} onClick={() => { setFilter(filter === stars ? 0 : stars); setPage(1) }} className="flex w-full items-center gap-2 text-sm"><span>{stars} ★</span><span className="h-2 flex-1 overflow-hidden rounded-full bg-muted"><span className="block h-full rounded-full bg-amber-400" style={{ width: `${percent}%` }} /></span><span>{percent}%</span></button>
    })}</div></aside><div className="min-w-0 space-y-5">
      {loading && <p role="status" className="text-sm text-muted-foreground">{text('Loading reviews…', 'جارٍ تحميل التقييمات…')}</p>}
      {!loading && !signedIn && <p className="rounded-xl bg-background p-4 text-sm"><Link href="/login" className="font-bold underline">{text('Sign in', 'سجّل دخولك')}</Link> {text('to review your purchase.', 'علشان تقيّم مشترياتك.')}</p>}
      {!loading && signedIn && mine && !mine.can_review && <p className="rounded-xl bg-background p-4 text-sm text-muted-foreground">{mine.reason === 'reviews_closed' ? text('Reviews are closed for this product.', 'التقييمات غير متاحة للمنتج ده حاليًا.') : text('A qualifying purchase is required before reviewing.', 'التقييم متاح بعد ما يكون عندك طلب مؤهل للتقييم.')}</p>}
      {!loading && mine?.review && <div className="rounded-xl border p-4"><h3 className="font-bold">{text('Your review', 'تقييمك')}</h3><p className="mt-1 text-xs text-shop-accent">{mine.review.status === 'pending' ? text('Awaiting moderation', 'في انتظار المراجعة') : text('Published', 'منشور')}</p>{reviewCard(mine.review)}{deleteConfirm ? <div className="flex flex-wrap items-center gap-3"><span className="text-sm">{text('Delete your review?', 'تحذف تقييمك؟')}</span><button type="button" disabled={busy} onClick={() => { void remove() }} className="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger-foreground">{text('Confirm deletion', 'تأكيد الحذف')}</button><button type="button" onClick={() => setDeleteConfirm(false)} className="text-sm underline">{text('Keep review', 'الاحتفاظ بالتقييم')}</button></div> : <button type="button" disabled={busy} onClick={() => setDeleteConfirm(true)} className="text-sm text-danger-foreground">{text('Delete my review', 'حذف تقييمي')}</button>}</div>}
      {!loading && mine?.can_review && <form noValidate onSubmit={submit} className="space-y-4 rounded-xl border p-4"><h3 className="font-bold">{mine.review ? text('Edit your review', 'تعديل تقييمك') : text('Write a review', 'اكتب تقييمك')}</h3><p className="text-xs leading-6 text-muted-foreground">{text('Your account name appears with your review. New and edited reviews are moderated before publication.', 'اسم حسابك بيظهر مع التقييم. التقييمات الجديدة والمعدّلة بتتراجع قبل النشر.')}</p><fieldset disabled={busy}><legend className="mb-2 text-sm">{text('Your rating', 'تقييمك')}</legend><div className="flex gap-1">{[1,2,3,4,5].map(stars => <label key={stars} className="cursor-pointer rounded-lg p-2 has-[:focus-visible]:outline-2"><input className="sr-only" type="radio" name={`rating-${target}`} checked={rating === stars} onChange={() => setRating(stars)} required /><span className="sr-only">{stars}/5</span><Star aria-hidden="true" className={`size-7 ${stars <= rating ? 'fill-amber-400 text-amber-500' : 'text-muted-foreground'}`} /></label>)}</div></fieldset><label className="block space-y-1 text-sm"><span>{text('Headline (optional)', 'عنوان التقييم (اختياري)')}</span><input maxLength={100} value={title} disabled={busy} onChange={event => setTitle(event.target.value)} className={field} /></label><label className="block space-y-1 text-sm"><span>{text('Your experience', 'تجربتك')}</span><textarea required minLength={1} maxLength={2000} rows={4} disabled={busy} value={comment} onChange={event => setComment(event.target.value)} className={field} /><span className="block text-end text-xs text-muted-foreground">{comment.length}/2000</span></label><label className="block space-y-2 text-sm"><span>{text('Photos (optional; up to 3 total, 3 MB each)', 'صور (اختيارية؛ ٣ صور إجمالًا، ٣ ميجا لكل صورة)')}</span><input key={photos.length === 0 ? 'empty' : 'selected'} type="file" accept="image/jpeg,image/png,image/webp" multiple disabled={busy} onChange={event => { const files = Array.from(event.target.files ?? []); if (files.length > 3 || files.some(file => file.size > 3000000 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) { event.target.value = ''; setPhotos([]); setError(text('Choose up to three JPEG, PNG or WebP images under 3 MB each.', 'اختار حتى ٣ صور JPEG أو PNG أو WebP، أقل من ٣ ميجا للصورة.')); } else { setPhotos(files); setError('') } }} className="block w-full text-sm" /></label><button disabled={busy || !rating} className="rounded-xl bg-[#f5b400] px-5 py-3 text-sm font-bold text-black disabled:opacity-50">{busy ? text('Saving…', 'جارٍ الحفظ…') : text('Submit for review', 'إرسال للمراجعة')}</button></form>}
      {notice && <p role="status" className="rounded-lg bg-success-soft p-3 text-sm text-success-foreground">{notice}</p>}{error && <p role="alert" className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{error}</p>}
      <div className="flex flex-wrap gap-3"><label className="text-sm">{text('Filter', 'التصفية')}<select className={`${field} mt-1`} value={filter} disabled={busy} onChange={event => { setFilter(Number(event.target.value)); setPage(1) }}><option value={0}>{text('All ratings', 'كل التقييمات')}</option>{[5,4,3,2,1].map(stars => <option key={stars} value={stars}>{stars} ★</option>)}</select></label><label className="text-sm">{text('Sort', 'الترتيب')}<select className={`${field} mt-1`} value={sort} disabled={busy} onChange={event => { setSort(event.target.value as typeof sort); setPage(1) }}><option value="newest">{text('Newest first', 'الأحدث أولًا')}</option><option value="highest">{text('Highest rated', 'الأعلى تقييمًا')}</option><option value="lowest">{text('Lowest rated', 'الأقل تقييمًا')}</option></select></label></div>
      {!loading && result && (result.data.items.length ? <div className="divide-y divide-border">{result.data.items.map(review => <div key={review.id}>{reviewCard(review)}</div>)}</div> : <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">{text('No published reviews match this selection.', 'مفيش تقييمات منشورة للاختيار ده حاليًا.')}</p>)}
      {result && result.meta.total_pages > 1 && <nav aria-label={text('Review pages', 'صفحات التقييمات')} className="flex items-center gap-4"><button type="button" disabled={page === 1 || loading || busy} onClick={() => setPage(page - 1)} className="rounded-lg border px-3 py-2 text-sm disabled:opacity-40">{text('Previous', 'السابق')}</button><span className="text-sm">{page} / {result.meta.total_pages}</span><button type="button" disabled={page >= result.meta.total_pages || loading || busy} onClick={() => setPage(page + 1)} className="rounded-lg border px-3 py-2 text-sm disabled:opacity-40">{text('Next', 'التالي')}</button></nav>}
    </div></div>
  </section>
}
