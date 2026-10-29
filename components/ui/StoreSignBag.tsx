/** The yellow shopping bag holding a question mark: the art of the 404 and error pages. */
export default function StoreSignBag({ mark = '؟' }: { mark?: string }) {
  return <div aria-hidden="true" className="relative grid w-44 shrink-0 place-items-center sm:w-56 md:w-80 lg:w-96">
    <svg viewBox="0 0 380 420" className="w-full">
      <path d="M120 120 C120 60 150 30 190 30 C230 30 260 60 260 120" fill="none" stroke="currentColor" strokeWidth="14" strokeLinecap="round" />
      <rect x="40" y="110" width="300" height="290" rx="36" fill="#f5b400" />
      <circle cx="120" cy="150" r="9" fill="#1d1d1b" />
      <circle cx="260" cy="150" r="9" fill="#1d1d1b" />
    </svg>
    <span className="absolute inset-x-0 top-[38%] text-center text-[6.5rem] font-black leading-none text-[#1d1d1b] sm:text-[8rem] md:text-[11rem] lg:text-[13rem]">{mark}</span>
  </div>
}
