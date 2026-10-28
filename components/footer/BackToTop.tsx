'use client'

import { ArrowUp } from 'lucide-react'

export default function BackToTop({ label }: { label: string }) {
  return (
    <button type="button" onClick={() => window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })} className="inline-flex min-h-10 items-center gap-2 rounded-full border border-white/20 px-4 text-sm font-semibold text-white transition hover:bg-white/10">
      <ArrowUp aria-hidden="true" className="size-4" />{label}
    </button>
  )
}
