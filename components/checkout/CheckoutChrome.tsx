'use client'

import type { ReactNode } from 'react'
import { usePathname } from 'next/navigation'

/** Checkout pages (before the order is placed) show no site header, footer or floating buttons. */
export function isFocusedCheckout(pathname: string) {
  return pathname.startsWith('/checkout') && !pathname.startsWith('/checkout/result')
}

/** Renders the normal site chrome everywhere except on focused checkout pages. */
export default function SiteChrome({ children }: { children: ReactNode }) {
  const pathname = usePathname()
  return isFocusedCheckout(pathname) ? null : children
}
