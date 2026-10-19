'use client'

import Link from 'next/link'
import { useState } from 'react'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { EldokanClientError } from '@eldokan/customer-api-client'
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
    } catch (cause) {
      const code = cause instanceof EldokanClientError ? cause.code : ''
      setError(code === 'rate_limited' ? (ar ? 'طلبات كتير. استنى ساعة وحاول تاني، أو راجع البريد اللي وصلك.' : 'Too many requests. Wait an hour and try again, or check the email you already received.')
        : code === 'invalid_password_reset' ? (ar ? 'الرابط ده انتهى أو اتستخدم قبل كده. اطلب رابط جديد.' : 'This link has expired or was already used. Request a new one.')
        : code === 'password_delivery_unavailable' ? (ar ? 'خدمة استعادة كلمة المرور مش متاحة دلوقتي. كلمنا على wecare@eldokan.com.' : 'Password recovery is unavailable right now. Contact wecare@eldokan.com.')
        : code === 'invalid_email' ? (ar ? 'اكتب بريد إلكتروني صحيح.' : 'Enter a valid email address.')
        : (ar ? 'تعذر إكمال الطلب. حاول مرة تانية بعد شوية.' : 'Could not complete the request. Please try again shortly.'))
    }
    finally { setBusy(false) }
  }
  const field = 'mt-2 w-full rounded-xl border border-input bg-background p-3'
  return <main className="mx-auto my-10 w-full max-w-md px-4"><section className="rounded-2xl border bg-card p-6 sm:p-8">
    <h1 className="text-2xl font-bold">{resetting ? (ar ? 'كلمة مرور جديدة' : 'Set a new password') : (ar ? 'نسيت كلمة المرور؟' : 'Forgot your password?')}</h1>
    {done ? <p role="status" className="mt-5 text-sm leading-7">{resetting ? (ar ? 'تم تغيير كلمة المرور، ووصلك إيميل بالتأكيد. سجّل دخولك بكلمة المرور الجديدة.' : 'Password changed, and a confirmation email is on its way. Sign in with your new password.') : (ar ? 'لو البريد مرتبط بحساب عميل، هيوصلك رابط تغيير كلمة المرور. راجع البريد الوارد والرسائل غير المرغوب فيها.' : 'If this email belongs to a customer account, a reset link will arrive. Check your inbox and spam folder.')}</p> : <form onSubmit={submit} className="mt-6 space-y-4">
      {resetting ? <><label className="block text-sm">{ar ? 'كلمة المرور الجديدة' : 'New password'}<input required minLength={10} maxLength={128} type="password" name="password" autoComplete="new-password" className={field} /></label><label className="block text-sm">{ar ? 'تأكيد كلمة المرور' : 'Confirm password'}<input required minLength={10} maxLength={128} type="password" name="confirm" autoComplete="new-password" className={field} /></label></> : <label className="block text-sm">{ar ? 'البريد الإلكتروني' : 'Email address'}<input required name="email" type="email" maxLength={254} dir="ltr" autoComplete="email" className={field} /></label>}
      {error && <p role="alert" className="text-sm text-danger-foreground">{error}</p>}
      <button disabled={busy} className="w-full rounded-xl bg-primary p-3 font-semibold text-primary-foreground disabled:opacity-50">{busy ? (ar ? 'جارٍ الإرسال…' : 'Please wait…') : resetting ? (ar ? 'حفظ كلمة المرور' : 'Save password') : (ar ? 'ابعت رابط الاستعادة' : 'Send reset link')}</button>
    </form>}
    <Link href="/login" className="mt-5 block text-sm underline">{ar ? 'العودة لتسجيل الدخول' : 'Back to sign in'}</Link>
  </section></main>
}
