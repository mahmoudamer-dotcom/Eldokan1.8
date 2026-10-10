'use client'
import { useLocale } from './LocaleProvider'
import { formatMoney, type DisplayMoney } from '@/lib/format-money'

export default function Money({ value }: { value?: DisplayMoney | null }) {
  const { locale } = useLocale()
  return <bdi className="tabular-nums">{formatMoney(value, locale)}</bdi>
}
