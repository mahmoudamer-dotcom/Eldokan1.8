'use client'

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
        : cause instanceof Error ? cause.message : 'Unable to authenticate. Please try again.')
    }
  }

  const fieldClass = 'mt-1 h-12 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-[#c58a36] focus:ring-2 focus:ring-[#c58a36]/20 dark:bg-[#202124]'
  const errorClass = 'mt-1 block text-sm text-red-700'

  return (
    <main className="mx-auto flex w-full max-w-md flex-1 items-center px-4 py-12">
      <section className="w-full rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 dark:bg-[#202124]">
        <h1 className="text-2xl font-bold">{isRegister ? 'Create your account' : 'Welcome back'}</h1>
        <p className="mt-2 text-sm text-gray-500">{isRegister ? 'Register to shop and manage your account.' : 'Sign in to continue to your account.'}</p>
        <form className="mt-7 space-y-4" onSubmit={handleSubmit(submit)} noValidate>
          {isRegister && <>
            <label className="block text-sm font-medium">First name
              <input className={fieldClass} autoComplete="given-name" aria-invalid={Boolean(errors.first_name)} {...register('first_name', { required: 'First name is required.', validate: (value) => Boolean(value?.trim()) || 'Enter your first name.' })} />
              {errors.first_name && <span className={errorClass}>{errors.first_name.message}</span>}
            </label>
            <label className="block text-sm font-medium">Last name
              <input className={fieldClass} autoComplete="family-name" aria-invalid={Boolean(errors.last_name)} {...register('last_name', { required: 'Last name is required.', validate: (value) => Boolean(value?.trim()) || 'Enter your last name.' })} />
              {errors.last_name && <span className={errorClass}>{errors.last_name.message}</span>}
            </label>
          </>}
          <label className="block text-sm font-medium">Email address
            <input className={fieldClass} type="email" autoComplete="email" aria-invalid={Boolean(errors.email)} {...register('email', { required: 'Email address is required.', validate: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) || 'Enter a valid email address.' })} />
            {errors.email && <span className={errorClass}>{errors.email.message}</span>}
          </label>
          <label className="block text-sm font-medium">Password
            <input className={fieldClass} type="password" autoComplete={isRegister ? 'new-password' : 'current-password'} aria-invalid={Boolean(errors.password)} {...register('password', { required: 'Password is required.', minLength: isRegister ? { value: 8, message: 'Password must be at least 8 characters.' } : undefined })} />
            {errors.password && <span className={errorClass}>{errors.password.message}</span>}
          </label>
          {serverError && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{serverError}</p>}
          <button disabled={isSubmitting} className="h-12 w-full rounded-lg bg-[#f5b400] px-4 font-semibold text-[#222] transition hover:bg-[#e5a900] disabled:cursor-wait disabled:opacity-60">
            {isSubmitting ? 'Please wait…' : isRegister ? 'Create account' : 'Sign in'}
          </button>
        </form>
        <p className="mt-6 text-center text-sm text-gray-600">
          {isRegister ? 'Already have an account?' : 'New to Eldokan?'}{' '}
          <Link className="font-semibold text-[#9a681d] hover:underline" href={isRegister ? '/login' : '/register'}>{isRegister ? 'Sign in' : 'Create an account'}</Link>
        </p>
      </section>
    </main>
  )
}
