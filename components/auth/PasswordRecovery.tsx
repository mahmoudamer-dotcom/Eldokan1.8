'use client'

import Link from 'next/link'
import { useState } from 'react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'

export default function PasswordRecovery({ login = '', resetKey = '' }: { login?: string; resetKey?: string }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const resetting = Boolean(login && resetKey)
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState(false)
  const [error, setError] = useState('')
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    const fields = new FormData(event.currentTarget)
    setBusy(true); setError('')
    try {
      const api = createEldokanApi(locale)
      if (resetting) {
        if (fields.get('password') !== fields.get('confirm')) { setError(ar ? 'كلمتا المرور غير متطابقتين.' : 'Passwords do not match.'); return }
        await api.commerce.resetPassword({ login, key: resetKey, password: String(fields.get('password')) })
      } else await api.commerce.forgotPassword(String(fields.get('email')).trim())
      setDone(true)
    } catch { setError(ar ? 'تعذر إكمال الطلب. لو بتغيّر كلمة المرور، اطلب رابطًا جديدًا وحاول مرة تانية.' : 'Could not complete the request. If resetting your password, request a new link and try again.') }
    finally { setBusy(false) }
  }
  const field = 'mt-2 w-full rounded-xl border border-input bg-background p-3'
  return <main className="mx-auto my-10 w-full max-w-md px-4"><section className="rounded-2xl border bg-card p-6 sm:p-8">
    <h1 className="text-2xl font-bold">{resetting ? (ar ? 'كلمة مرور جديدة' : 'Set a new password') : (ar ? 'نسيت كلمة المرور؟' : 'Forgot your password?')}</h1>
    {done ? <p role="status" className="mt-5 text-sm leading-7">{resetting ? (ar ? 'تم تغيير كلمة المرور. سجّل دخولك من جديد.' : 'Password changed. Please sign in again.') : (ar ? 'لو البريد مرتبط بحساب عميل، هيوصلك رابط تغيير كلمة المرور. راجع البريد الوارد والرسائل غير المرغوب فيها.' : 'If this email belongs to a customer account, a reset link will arrive. Check your inbox and spam folder.')}</p> : <form onSubmit={submit} className="mt-6 space-y-4">
      {resetting ? <><label className="block text-sm">{ar ? 'كلمة المرور الجديدة' : 'New password'}<input required minLength={10} maxLength={256} type="password" name="password" autoComplete="new-password" className={field} /></label><label className="block text-sm">{ar ? 'تأكيد كلمة المرور' : 'Confirm password'}<input required minLength={10} maxLength={256} type="password" name="confirm" autoComplete="new-password" className={field} /></label></> : <label className="block text-sm">{ar ? 'البريد الإلكتروني' : 'Email address'}<input required name="email" type="email" maxLength={254} dir="ltr" autoComplete="email" className={field} /></label>}
      {error && <p role="alert" className="text-sm text-danger-foreground">{error}</p>}
      <button disabled={busy} className="w-full rounded-xl bg-primary p-3 font-semibold text-primary-foreground disabled:opacity-50">{busy ? (ar ? 'جارٍ الإرسال…' : 'Please wait…') : resetting ? (ar ? 'حفظ كلمة المرور' : 'Save password') : (ar ? 'ابعت رابط الاستعادة' : 'Send reset link')}</button>
    </form>}
    <Link href="/login" className="mt-5 block text-sm underline">{ar ? 'العودة لتسجيل الدخول' : 'Back to sign in'}</Link>
  </section></main>
}
