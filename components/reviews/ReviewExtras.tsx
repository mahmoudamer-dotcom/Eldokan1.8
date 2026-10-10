'use client'
import Image from 'next/image'
import { useEffect, useRef, useState } from 'react'
import type { CustomerReview } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { useLocale } from '@/components/i18n/LocaleProvider'

export default function ReviewExtras({ review }: { review: CustomerReview & { helpful_count?: number; images?: { url: string; alt: string }[] } }) {
  const { locale } = useLocale(); const ar = locale === 'ar'
  const [reporting, setReporting] = useState(false); const [busy, setBusy] = useState(false); const [message, setMessage] = useState(''); const [voted, setVoted] = useState(false); const [count, setCount] = useState(review.helpful_count ?? 0)
  const [photo, setPhoto] = useState<string | null>(null)
  const viewer = useRef<HTMLDialogElement>(null)
  useEffect(() => { if (photo && !viewer.current?.open) viewer.current?.showModal(); else if (!photo) viewer.current?.close() }, [photo])
  async function send(action: 'helpful' | 'report', reason = '') {
    if (busy || (action === 'helpful' && voted)) return
    setBusy(true); setMessage('')
    try { const api = createEldokanApi(locale); await api.auth.session(); const response = await api.commerce.feedback(review.id, action, reason); setCount(response.data.helpful_count); if (action === 'helpful') setVoted(true); setReporting(false); setMessage(ar ? 'شكرًا، تم تسجيل رأيك.' : 'Thank you. Your feedback was recorded.') }
    catch { setMessage(ar ? 'سجّل دخولك وحاول مرة أخرى. التصويت على تقييمك غير متاح.' : 'Sign in and try again. You cannot vote on your own review.') } finally { setBusy(false) }
  }
  return <div className="space-y-3">
    {!!review.images?.length && <div className="flex flex-wrap gap-3">{review.images.map(image => <button type="button" onClick={() => setPhoto(image.url)} aria-label={ar ? 'تكبير صورة التقييم' : 'Enlarge review photo'} key={image.url} className="relative block size-24 overflow-hidden rounded-lg border"><Image src={image.url} alt={ar ? 'صورة أضافها صاحب التقييم' : image.alt} fill sizes="96px" className="object-cover" /></button>)}</div>}
    <dialog ref={viewer} onClose={() => setPhoto(null)} onClick={event => { if (event.target === event.currentTarget) setPhoto(null) }} aria-label={ar ? 'صورة التقييم' : 'Review photo'} className="fixed inset-0 m-auto w-[min(95vw,60rem)] rounded-2xl bg-card p-4 text-foreground backdrop:bg-black/70"><button type="button" onClick={() => setPhoto(null)} className="mb-3 rounded-lg border px-4 py-2 text-sm">{ar ? 'إغلاق' : 'Close'}</button>{photo && <div className="relative h-[70vh]"><Image src={photo} alt={ar ? 'صورة أضافها صاحب التقييم' : 'Customer review photo'} fill sizes="95vw" className="object-contain" /></div>}</dialog>
    <div className="flex flex-wrap gap-4 text-xs text-muted-foreground"><button disabled={busy || voted} onClick={() => void send('helpful')} className="underline disabled:opacity-50">{ar ? 'التقييم مفيد' : 'Helpful'} ({count})</button><button disabled={busy} onClick={() => setReporting(!reporting)} className="underline">{ar ? 'الإبلاغ عن التقييم' : 'Report review'}</button></div>
    {reporting && <form className="flex flex-wrap gap-2" onSubmit={event => { event.preventDefault(); const data = new FormData(event.currentTarget); void send('report', String(data.get('reason'))) }}><input required maxLength={250} name="reason" aria-label={ar ? 'سبب الإبلاغ' : 'Report reason'} placeholder={ar ? 'سبب الإبلاغ' : 'Report reason'} className="min-w-0 flex-1 rounded-lg border bg-background p-2 text-sm" /><button disabled={busy} className="rounded-lg border px-3 text-sm">{ar ? 'إرسال' : 'Send'}</button></form>}
    {message && <p role="status" className="text-xs text-shop-accent">{message}</p>}
  </div>
}
