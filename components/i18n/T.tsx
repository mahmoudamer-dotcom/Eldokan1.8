'use client'

import { useLocale } from './LocaleProvider'

export default function T({ text }: { text: string }) {
  const { t } = useLocale()
  return <>{t(text)}</>
}
