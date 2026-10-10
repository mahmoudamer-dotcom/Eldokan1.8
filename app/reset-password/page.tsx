import PasswordRecovery from '@/components/auth/PasswordRecovery'
export const metadata = { robots: { index: false, follow: false }, referrer: 'no-referrer' as const }
export default async function Page({ searchParams }: { searchParams: Promise<{ login?: string; key?: string }> }) {
  const query = await searchParams
  return <PasswordRecovery login={typeof query.login === 'string' ? query.login : ''} resetKey={typeof query.key === 'string' ? query.key : ''} />
}
