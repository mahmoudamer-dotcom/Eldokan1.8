'use client'

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { translate, type Locale } from '@/lib/i18n'

type LocaleContextValue = {
  locale: Locale
  setLocale: (locale: Locale) => void
  theme: 'light' | 'dark'
  setTheme: (theme: 'light' | 'dark') => void
  t: (text: string) => string
}

const LocaleContext = createContext<LocaleContextValue | null>(null)

export default function LocaleProvider({ children, initialLocale, initialTheme }: { children: React.ReactNode; initialLocale: Locale; initialTheme: 'light' | 'dark' }) {
  const [locale, setLocaleState] = useState(initialLocale)
  const [theme, setThemeState] = useState(initialTheme)

  useEffect(() => {
    document.documentElement.lang = locale
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr'
  }, [locale])

  const setLocale = useCallback((nextLocale: Locale) => {
    document.cookie = `eldokan_locale=${nextLocale}; path=/; max-age=31536000; samesite=lax`
    document.documentElement.lang = nextLocale
    document.documentElement.dir = nextLocale === 'ar' ? 'rtl' : 'ltr'
    setLocaleState(nextLocale)
  }, [])

  useEffect(() => {
    document.documentElement.classList.toggle('dark', theme === 'dark')
    document.documentElement.style.colorScheme = theme
  }, [theme])

  const setTheme = useCallback((nextTheme: 'light' | 'dark') => {
    document.cookie = `eldokan_theme=${nextTheme}; path=/; max-age=31536000; samesite=lax`
    document.documentElement.classList.toggle('dark', nextTheme === 'dark')
    document.documentElement.style.colorScheme = nextTheme
    setThemeState(nextTheme)
  }, [])

  const t = useCallback((text: string) => translate(text, locale), [locale])

  return <LocaleContext.Provider value={{ locale, setLocale, theme, setTheme, t }}>{children}</LocaleContext.Provider>
}

export function useLocale() {
  const value = useContext(LocaleContext)
  if (!value) throw new Error('useLocale must be used inside LocaleProvider')
  return value
}
