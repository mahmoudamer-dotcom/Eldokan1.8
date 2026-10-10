'use client'

import { customerError } from '@/lib/customer-error'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { LoginRequest, RegisterRequest } from '@eldokan/customer-api-client'
import { EldokanClientError } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { createEldokanApi } from '@/lib/eldokan-api'

export default function AuthForm({ mode }: { mode: 'login' | 'register' }) {
  const router = useRouter()
  const { locale, t } = useLocale()
  const [serverError, setServerError] = useState('')
  const isRegister = mode === 'register'
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<RegisterRequest>({
    defaultValues: { email: '', password: '', first_name: '', last_name: '' },
  })

  useEffect(() => {
    let active = true
    createEldokanApi(locale).auth.session()
      .then(() => {
        if (active) router.replace('/account')
      })
      .catch(() => {
        // Guests stay on the sign-in or registration page.
      })
    return () => { active = false }
  }, [locale, router])

  async function submit(values: RegisterRequest) {
    setServerError('')
    try {
      const api = createEldokanApi(locale)
      if (isRegister) {
        const input: RegisterRequest = {
          email: values.email.trim(),
          password: values.password,
          first_name: values.first_name?.trim(),
          last_name: values.last_name?.trim(),
          phone: values.phone?.trim() ?? '',
        }
        await api.auth.register(input)
      } else {
        const input: LoginRequest = { email: values.email.trim(), password: values.password }
        await api.auth.login(input)
      }
      window.dispatchEvent(new Event('eldokan:session-changed'))
      router.replace('/account')
      router.refresh()
    } catch (cause) {
      setServerError(cause instanceof EldokanClientError && cause.kind === 'network'
        ? t('Unable to connect to the storefront API. Check your connection and try again.')
        : customerError(cause, locale, 'Unable to authenticate. Please try again.'))
    }
  }

  const fieldClass = 'mt-1 h-12 w-full rounded-lg border border-input bg-card px-3 text-sm outline-none focus:border-[#c58a36] focus:ring-2 focus:ring-[#c58a36]/20'
  const errorClass = 'mt-1 block text-sm text-danger-foreground'

  return (
    <main className="mx-auto flex w-full max-w-md flex-1 items-center px-4 py-7 sm:py-12">
      <section className="w-full rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-8">
        <h1 className="text-2xl font-bold">{isRegister ? t('Create your account') : t('Welcome back')}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{isRegister ? t('Register to shop and manage your account.') : t('Sign in to continue to your account.')}</p>
        <form className="mt-7 space-y-4" onSubmit={handleSubmit(submit)} noValidate>
          {isRegister && <>
            <label className="block text-sm font-medium">{t("First name")}
              <input className={fieldClass} autoComplete="given-name" aria-invalid={Boolean(errors.first_name)} {...register('first_name', { required: 'First name is required.', validate: (value) => Boolean(value?.trim()) || 'Enter your first name.' })} />
              {errors.first_name && <span className={errorClass}>{t(errors.first_name.message ?? '')}</span>}
            </label>
            <label className="block text-sm font-medium">{t("Last name")}
              <input className={fieldClass} autoComplete="family-name" aria-invalid={Boolean(errors.last_name)} {...register('last_name', { required: 'Last name is required.', validate: (value) => Boolean(value?.trim()) || 'Enter your last name.' })} />
              {errors.last_name && <span className={errorClass}>{t(errors.last_name.message ?? '')}</span>}
            </label>
            <label className="block text-sm font-medium">{t("Phone (optional)")}
              <input className={fieldClass} dir="ltr" type="tel" autoComplete="tel" maxLength={32} aria-invalid={Boolean(errors.phone)} {...register('phone', { validate: (value) => !value || (/^\+?[0-9 ()-]+$/.test(value.trim()) && (value.match(/[0-9]/g)?.length ?? 0) >= 6) || 'Use at least six digits, with only digits, spaces, parentheses, hyphens, and an optional leading +.' })} />
              {errors.phone && <span className={errorClass}>{t(errors.phone.message ?? '')}</span>}
            </label>
          </>}
          <label className="block text-sm font-medium">{t("Email address")}
            <input className={fieldClass} dir="ltr" type="email" autoComplete="email" aria-invalid={Boolean(errors.email)} {...register('email', { required: 'Email address is required.', validate: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) || 'Enter a valid email address.' })} />
            {errors.email && <span className={errorClass}>{t(errors.email.message ?? '')}</span>}
          </label>
          <label className="block text-sm font-medium">{t("Password")}
            <input className={fieldClass} type="password" autoComplete={isRegister ? 'new-password' : 'current-password'} aria-invalid={Boolean(errors.password)} {...register('password', { required: 'Password is required.', minLength: isRegister ? { value: 10, message: 'Password must be at least 10 characters.' } : undefined })} />
            {errors.password && <span className={errorClass}>{t(errors.password.message ?? '')}</span>}
          </label>
          {serverError && <p role="alert" className="rounded-lg bg-danger-soft p-3 text-sm text-danger-foreground">{serverError}</p>}
          <button disabled={isSubmitting} className="h-12 w-full rounded-lg bg-[#f5b400] px-4 font-semibold text-primary-foreground transition hover:bg-[#e5a900] disabled:cursor-wait disabled:opacity-60">
            {isSubmitting ? t('Please wait…') : isRegister ? t('Create account') : t('Sign in')}
          </button>
        </form>
        {!isRegister && <Link href="/forgot-password" className="mt-4 block text-sm underline">{locale === 'ar' ? 'نسيت كلمة المرور؟' : 'Forgot your password?'}</Link>}
        <p className="mt-6 text-center text-sm text-muted-foreground">
          {isRegister ? t('Already have an account?') : t('New to Eldokan?')}{' '}
          <Link className="font-semibold text-shop-accent hover:underline" href={isRegister ? '/login' : '/register'}>{isRegister ? t('Sign in') : t('Create an account')}</Link>
        </p>
      </section>
    </main>
  )
}
