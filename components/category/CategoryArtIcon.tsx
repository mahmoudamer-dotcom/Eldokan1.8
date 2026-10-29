import Image from 'next/image'
import { categoryIcon, categoryWorldOf } from '@/lib/category-experience'

/** A department's illustrated icon (the same art as the home circles) in a small round tile of its colour. */
export default function CategoryArtIcon({ slug, name, className = 'size-7' }: { slug?: string; name?: string; className?: string }) {
  const world = categoryWorldOf(slug, name)
  return <span aria-hidden="true" className={`category-world category-world--${world} relative block shrink-0 overflow-hidden rounded-full bg-[var(--department-surface)] ring-1 ring-black/5 ${className}`}>
    <Image src={categoryIcon(world)} alt="" fill sizes="32px" unoptimized className="object-cover" />
  </span>
}
