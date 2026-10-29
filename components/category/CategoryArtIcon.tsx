import { createElement } from 'react'
import { categoryIconFor } from '@/lib/category-icons'

/**
 * A department's Lucide icon inside an arched doorway, the shape of the doorway in the Eldokan logo,
 * in the logo's black and white. `paper` is the white version for the black footer.
 */
export default function CategoryArtIcon({ slug, name, tone = 'ink', className = 'h-8 w-7', iconClassName = 'size-4' }: { slug?: string; name?: string; tone?: 'ink' | 'paper'; className?: string; iconClassName?: string }) {
  return <span aria-hidden="true" className={`dokan-arch grid shrink-0 place-items-center pt-0.5 ${tone === 'paper' ? 'bg-white text-black' : 'bg-foreground text-background'} ${className}`}>
    {createElement(categoryIconFor({ slug, name }), { className: iconClassName, strokeWidth: 2 })}
  </span>
}
