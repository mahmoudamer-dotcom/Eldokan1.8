'use client'

import { useEffect, useRef } from 'react'
import { usePathname } from 'next/navigation'

/** Closes the surrounding <details> menu after a link inside it opens another page. */
export default function CloseOnNavigate() {
  const marker = useRef<HTMLSpanElement>(null)
  const pathname = usePathname()
  useEffect(() => {
    const menu = marker.current?.closest('details')
    if (menu) menu.open = false
  }, [pathname])
  return <span ref={marker} hidden />
}
