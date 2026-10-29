import AuthForm from '@/components/auth/AuthForm'
import { titled } from '@/lib/page-title'

export const generateMetadata = () => titled('Sign in', 'تسجيل الدخول', { robots: { index: false, follow: false } })

export default function LoginPage() {
  return <AuthForm mode="login" />
}
