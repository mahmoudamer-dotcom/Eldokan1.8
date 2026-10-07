'use client'

import Link from 'next/link'
import ProductCard from '@/components/productCard/ProductCard'
import T from '@/components/i18n/T'
import { useWishlist } from '@/components/wishlist/WishlistProvider'

export default function WishlistPage() {
  const { products, ready, signedIn, error } = useWishlist()

  return <main className="container mx-auto min-h-[60vh] flex-1 px-4 py-10">
    <h1 className="text-2xl font-bold"><T text="Favorites" /></h1>
    {!ready ? <p className="mt-6 text-sm text-muted-foreground"><T text="Loading your favorites…" /></p>
      : !signedIn && !error ? <div className="mt-6 rounded-2xl border border-dashed border-input p-10 text-center">
        <p className="text-muted-foreground"><T text="Sign in to see and manage your favorites." /></p>
        <Link href="/login" className="mt-4 inline-flex rounded-lg bg-[#222] px-5 py-3 font-semibold text-white"><T text="Sign in" /></Link>
      </div>
        : <>
          {error && <p role="alert" className="mt-6 text-sm text-danger-foreground"><T text={error} /></p>}
          {signedIn && products.length === 0 && !error
            ? <p className="mt-6 rounded-2xl border border-dashed border-input p-10 text-center text-muted-foreground"><T text="Your favorites list is empty." /></p>
            : products.length > 0 && <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
              {products.map((product) => <ProductCard key={product.id} product={product} />)}
            </div>}
        </>}
  </main>
}
