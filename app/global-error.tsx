'use client'

import Link from 'next/link'

// Replaces the root layout when it fails, so it renders its own document and
// cannot rely on the locale provider or global styles.
export default function GlobalError({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <html lang="ar" dir="rtl">
      <body style={{ margin: 0, fontFamily: 'system-ui, sans-serif', background: '#fafafa', color: '#1a1a1a' }}>
        <title>الدكان | Eldokan</title>
        <main style={{ maxWidth: '32rem', margin: '4rem auto', padding: '2rem', textAlign: 'center' }}>
          <h1 style={{ fontSize: '1.5rem' }}>تعذر تحميل الموقع حاليًا</h1>
          <p style={{ lineHeight: 1.8 }}>حاول مرة أخرى بعد لحظات. لو كنت بتكمل طلب، افتح حالة الطلب قبل بدء عملية شراء جديدة.</p>
          <p dir="ltr" style={{ lineHeight: 1.8, color: '#555' }}>The store could not load. Please try again in a moment.</p>
          {error.digest && <p dir="ltr" style={{ fontSize: '.75rem', color: '#777' }}>Error reference: {error.digest}</p>}
          <button onClick={() => retry()} style={{ marginTop: '1rem', padding: '.75rem 1.5rem', border: 0, borderRadius: '.75rem', background: '#f5b400', fontWeight: 600, fontSize: '1rem', cursor: 'pointer' }}>حاول مرة أخرى · Try again</button>
          <p style={{ marginTop: '1.5rem' }}><Link href="/checkout/result" style={{ color: 'inherit' }}>حالة الطلب · Order status</Link></p>
        </main>
      </body>
    </html>
  )
}
