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
import { EGYPT_GOVERNORATES } from '@/lib/egypt-governorates'

type RegisterForm = RegisterRequest & { state: string; city: string; street_address: string; address_extra: string }

/** Egyptian mobile: 01XXXXXXXXX, optionally written with +20 / 0020, spaces or hyphens. */
function egyptMobile(value: string) {
  const digits = value.replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660)).replace(/[\s()-]/g, '')
  const local = digits.replace(/^(\+20|0020)/, '0')
  return /^01[0125][0-9]{8}$/.test(local) ? local : null
}

export default function AuthForm({ mode }: { mode: 'login' | 'register' }) {
  const router = useRouter()
  const { locale, t } = useLocale()
  const [serverError, setServerError] = useState('')
  const isRegister = mode === 'register'
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<RegisterForm>({
    defaultValues: { email: '', password: '', first_name: '', last_name: '', phone: '', state: '', city: '', street_address: '', address_extra: '' },
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

  const ar = locale === 'ar'

  async function submit(values: RegisterForm) {
    setServerError('')
    try {
      const api = createEldokanApi(locale)
      if (isRegister) {
        const input: RegisterRequest = {
          email: values.email.trim(),
          password: values.password,
          first_name: values.first_name?.trim(),
          last_name: values.last_name?.trim(),
          phone: egyptMobile(values.phone ?? '') ?? '',
        }
        await api.auth.register(input)
        // The account exists now; save the delivery address as its default for checkout.
        try {
          await api.addresses.create({
            first_name: input.first_name ?? '', last_name: input.last_name ?? '', phone: input.phone ?? '', email: input.email, country: 'EG',
            state: values.state, city: values.city.trim(), street_address: values.street_address.trim(), address_extra: values.address_extra.trim() || null, is_default: true,
          })
        } catch {
          // Registration succeeded; the customer can add the address from the account page.
        }
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
            <label className="block text-sm font-medium">{ar ? 'رقم الموبايل' : 'Mobile number'}
              <input className={fieldClass} dir="ltr" type="tel" inputMode="tel" autoComplete="tel" maxLength={20} placeholder="01XXXXXXXXX" aria-invalid={Boolean(errors.phone)} {...register('phone', { required: ar ? 'رقم الموبايل مطلوب.' : 'Mobile number is required.', validate: (value) => Boolean(egyptMobile(value ?? '')) || (ar ? 'اكتب رقم موبايل مصري صحيح، مثلًا 01012345678.' : 'Enter a valid Egyptian mobile number, e.g. 01012345678.') })} />
              {errors.phone && <span className={errorClass}>{errors.phone.message}</span>}
            </label>
            <fieldset className="space-y-4 rounded-xl border border-border p-4">
              <legend className="px-1 text-sm font-semibold">{ar ? 'عنوان التوصيل' : 'Delivery address'}</legend>
              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block text-sm font-medium">{ar ? 'المحافظة' : 'Governorate'}
                  <select className={fieldClass} autoComplete="address-level1" aria-invalid={Boolean(errors.state)} {...register('state', { required: ar ? 'اختار المحافظة.' : 'Choose your governorate.' })}>
                    <option value="">{ar ? 'اختار المحافظة' : 'Choose governorate'}</option>
                    {[...EGYPT_GOVERNORATES].sort((a, b) => a[locale].localeCompare(b[locale], locale)).map((governorate) => <option key={governorate.code} value={governorate.code}>{governorate[locale]}</option>)}
                  </select>
                  {errors.state && <span className={errorClass}>{errors.state.message}</span>}
                </label>
                <label className="block text-sm font-medium">{ar ? 'المدينة أو المنطقة' : 'City or district'}
                  <input className={fieldClass} autoComplete="address-level2" maxLength={150} aria-invalid={Boolean(errors.city)} {...register('city', { required: ar ? 'اكتب المدينة أو المنطقة.' : 'Enter your city or district.', validate: (value) => Boolean(value.trim()) || (ar ? 'اكتب المدينة أو المنطقة.' : 'Enter your city or district.') })} />
                  {errors.city && <span className={errorClass}>{errors.city.message}</span>}
                </label>
              </div>
              <label className="block text-sm font-medium">{ar ? 'العنوان بالتفصيل' : 'Street address'}
                <input className={fieldClass} autoComplete="street-address" maxLength={500} placeholder={ar ? 'الشارع ورقم العمارة والدور والشقة' : 'Street, building, floor and apartment'} aria-invalid={Boolean(errors.street_address)} {...register('street_address', { required: ar ? 'اكتب العنوان بالتفصيل.' : 'Enter your street address.', validate: (value) => value.trim().length >= 5 || (ar ? 'اكتب العنوان بالتفصيل.' : 'Enter your full street address.') })} />
                {errors.street_address && <span className={errorClass}>{errors.street_address.message}</span>}
              </label>
              <label className="block text-sm font-medium">{ar ? 'علامة مميزة (اختياري)' : 'Nearby landmark (optional)'}
                <input className={fieldClass} autoComplete="address-line2" maxLength={500} placeholder={ar ? 'مثال: بجوار المدرسة أو المسجد' : 'e.g. next to the school'} {...register('address_extra')} />
              </label>
            </fieldset>
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
