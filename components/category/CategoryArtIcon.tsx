import { createElement } from 'react'
import { categoryWorldOf } from '@/lib/category-experience'
import { categoryIconFor } from '@/lib/category-icons'

/** A department's Lucide icon in a small round tile of the department's colours. */
export default function CategoryArtIcon({ slug, name, className = 'size-7', iconClassName = 'size-4' }: { slug?: string; name?: string; className?: string; iconClassName?: string }) {
  const world = categoryWorldOf(slug, name)
  return <span aria-hidden="true" className={`category-world category-world--${world} grid shrink-0 place-items-center rounded-full bg-[var(--department-surface)] text-[var(--department-accent)] ring-1 ring-black/5 ${className}`}>
    {createElement(categoryIconFor({ slug, name }), { className: iconClassName, strokeWidth: 2 })}
  </span>
}
