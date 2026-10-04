'use client'

import Link from 'next/link'
import { useLocale } from '@/components/i18n/LocaleProvider'

export type Announcement = {
  text: string
  href?: string
}

type AnnouncementTopBarProps = {
  announcements: Announcement[]
}

export default function AnnouncementTopBar({ announcements }: AnnouncementTopBarProps) {
  const { locale, t } = useLocale()
  if (announcements.length === 0) return null

  const renderAnnouncements = (isDuplicate = false) => (
    <div
      dir="ltr"
      className="flex min-w-[100vw] shrink-0 items-center justify-around"
      aria-hidden={isDuplicate || undefined}
    >
      {announcements.map((announcement, index) => (
        <span key={`${announcement.text}-${index}`} dir={locale === 'ar' ? 'rtl' : 'ltr'} className="flex shrink-0 items-center whitespace-nowrap">
          {announcement.href && !isDuplicate ? (
            <Link href={announcement.href} className="px-6 hover:underline">
              {announcement.text}
            </Link>
          ) : (
            <span className="px-6">{announcement.text}</span>
          )}
        </span>
      ))}
    </div>
  )

  return (
    <div
      className="announcement-top-bar w-full overflow-hidden bg-[#222222] py-2 text-sm text-white"
      role="region"
      aria-label={t('Store announcements')}
    >
      <div dir="ltr" className="announcement-top-bar__track flex w-max">
        {renderAnnouncements()}
        {renderAnnouncements(true)}
      </div>
    </div>
  )
}
