import AuthForm from '@/components/auth/AuthForm'
import Link from 'next/link'
import { getLocale } from '@/lib/server-locale'

export default async function RegisterPage() {
  const ar = await getLocale() === 'ar'
  return <><AuthForm mode="register" /><p dir={ar ? 'rtl' : 'ltr'} className="mx-auto mb-8 max-w-xl px-4 text-center text-sm leading-7 text-muted-foreground">{ar ? 'تعرف على سياسات حسابك: ' : 'Read your account policies: '}<Link href="/policies/terms" className="underline">{ar ? 'شروط الاستخدام' : 'Terms of use'}</Link> · <Link href="/policies/privacy" className="underline">{ar ? 'الخصوصية' : 'Privacy'}</Link></p></>
}
