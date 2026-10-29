'use client'

import Link from 'next/link'

// Replaces the root layout when it fails, so it renders its own document and
// cannot rely on the locale provider or global styles: the store sign, styled inline.
export default function GlobalError({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  const button = { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', minHeight: '3rem', padding: '0 1.75rem', borderRadius: '999px', fontSize: '1rem', fontWeight: 800, textDecoration: 'none', cursor: 'pointer' } as const
  return (
    <html lang="ar" dir="rtl">
      <body style={{ margin: 0, fontFamily: 'system-ui, Tahoma, sans-serif', background: '#f6f3ec', color: '#1d1d1b' }}>
        <title>حصلت مشكلة | الدكان</title>
        <main style={{ minHeight: '100vh', boxSizing: 'border-box', display: 'flex', flexWrap: 'wrap-reverse', alignItems: 'center', justifyContent: 'center', gap: '2rem 4rem', padding: '2rem 1rem', textAlign: 'center' }}>
          <div style={{ maxWidth: '32rem' }}>
            <h1 style={{ fontSize: '2rem', lineHeight: 1.3, margin: 0 }}>الموقع ما فتحش دلوقتي</h1>
            <p style={{ lineHeight: 1.8, color: '#55524b' }}>حاول مرة تانية كمان شوية. لو كنت بتكمل طلب، افتح حالة الطلب قبل ما تبدأ شراء جديد.</p>
            <p dir="ltr" style={{ lineHeight: 1.8, color: '#55524b', marginTop: 0 }}>The store could not load. Please try again in a moment.</p>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '.75rem', justifyContent: 'center', marginTop: '1rem' }}>
              <button type="button" onClick={() => retry()} style={{ ...button, border: 0, background: '#f5b400', color: '#1d1d1b' }}>حاول مرة تانية · Try again</button>
              <Link href="/checkout/result" style={{ ...button, border: '1px solid #1d1d1b', color: '#1d1d1b' }}>حالة الطلب · Order status</Link>
            </div>
            {error.digest && <p dir="ltr" style={{ fontSize: '.75rem', color: '#6b675e' }}>Error reference: {error.digest}</p>}
          </div>
          <svg aria-hidden="true" viewBox="0 0 380 420" style={{ width: '14rem', maxWidth: '60vw' }}>
            <path d="M120 120 C120 60 150 30 190 30 C230 30 260 60 260 120" fill="none" stroke="#1d1d1b" strokeWidth="14" strokeLinecap="round" />
            <rect x="40" y="110" width="300" height="290" rx="36" fill="#f5b400" />
            <circle cx="120" cy="150" r="9" fill="#1d1d1b" />
            <circle cx="260" cy="150" r="9" fill="#1d1d1b" />
            <text x="190" y="350" textAnchor="middle" fontSize="200" fontWeight="900" fill="#1d1d1b">؟</text>
          </svg>
        </main>
      </body>
    </html>
  )
}
