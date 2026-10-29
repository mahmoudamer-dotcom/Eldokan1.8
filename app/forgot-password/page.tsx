import { titled } from '@/lib/page-title'
import PasswordRecovery from '@/components/auth/PasswordRecovery'
export const generateMetadata = () => titled('Forgot password', 'نسيت كلمة المرور', { robots: { index: false, follow: false } })
export default function Page() { return <PasswordRecovery /> }
