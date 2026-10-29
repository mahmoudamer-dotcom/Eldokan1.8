import { CircleQuestionMark, ShoppingBag } from 'lucide-react'

/** The 404 and error art, all Lucide: a shopping bag on a yellow disc with a question-mark badge. */
export default function StoreSignBag() {
  return <div aria-hidden="true" className="relative grid aspect-square w-40 shrink-0 place-items-center rounded-full bg-[#f5b400] sm:w-52 md:w-72 lg:w-80">
    <ShoppingBag className="size-1/2 text-[#1d1d1b]" strokeWidth={1.5} />
    <span className="absolute bottom-[8%] end-[8%] grid size-[30%] place-items-center rounded-full bg-[#1d1d1b] text-white ring-8 ring-background">
      <CircleQuestionMark className="size-[70%]" strokeWidth={1.75} />
    </span>
  </div>
}
