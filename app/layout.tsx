import type { Metadata } from "next";

import "./globals.css";

import localFont from 'next/font/local';
import { Suspense } from 'react';
import SiteHeader from '@/components/navbar/SiteHeader';
import LocaleProvider from '@/components/i18n/LocaleProvider';
import { getLocale, getTheme } from '@/lib/server-locale';
import { WishlistProvider } from '@/components/wishlist/WishlistProvider'
import { CartProvider } from '@/components/cart/CartProvider'
import { getStorefrontUrl } from '@/lib/site-url'
import { ComparisonProvider } from '@/components/productDetails/ComparisonProvider'
import Footer from '@/components/footer/Footer'
import { ShoppingChatProvider } from '@/components/discovery/ShoppingChatProvider'


const OpenSans = localFont({
  src: '../public/fonts/Inter-VariableFont_opsz,wght.ttf', 
  display: 'swap',
  variable: '--font-openSans',
});

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getLocale()
  const content = locale === 'ar'
    ? { title: 'الدكان | تسوق عبر الإنترنت', description: 'اكتشف احتياجاتك اليومية وعروضًا رائعة في الدكان.' }
    : { title: 'Eldokan | Shop Online', description: 'Discover everyday essentials and great deals at Eldokan.' }
  return { ...content, metadataBase: getStorefrontUrl(), robots: { index: process.env.STOREFRONT_ALLOW_INDEXING === 'true' && Boolean(getStorefrontUrl()), follow: true } }
}

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const [locale, theme] = await Promise.all([getLocale(), getTheme()]);

  return (
    <html
      lang={locale}
      dir={locale === 'ar' ? 'rtl' : 'ltr'}
      className={`${OpenSans.variable} h-full antialiased${theme === 'dark' ? ' dark' : ''}`}
    >
      <body className="flex min-h-screen flex-col overflow-x-clip bg-background text-foreground">
        <LocaleProvider initialLocale={locale} initialTheme={theme}>
          <CartProvider>
            <WishlistProvider>
              <ComparisonProvider>
              <ShoppingChatProvider>
              <Suspense fallback={<div className="h-[180px] shrink-0 bg-card" aria-hidden="true" />}>
                <SiteHeader />
              </Suspense>
              <div className="flex flex-1 flex-col">{children}</div>
              <Suspense fallback={<footer className="min-h-40 border-t bg-background" aria-label={locale === 'ar' ? 'جارٍ تحميل تذييل الموقع' : 'Loading site footer'} />}>
                <Footer />
              </Suspense>
              </ShoppingChatProvider>
              </ComparisonProvider>
            </WishlistProvider>
          </CartProvider>
        </LocaleProvider>
      </body>
    </html>
  );
}
