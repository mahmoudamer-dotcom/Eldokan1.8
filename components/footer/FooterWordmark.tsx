'use client'

import { useState } from 'react'
import type { Locale } from '@/lib/i18n'

/** Each letter of the name carries one part of the Eldokan story. */
const STORY = [
  { letter: 'E', en: ['Everyday', 'Your daily essentials, in one place.'], ar: ['كل يوم', 'احتياجاتك اليومية في مكان واحد.'] },
  { letter: 'L', en: ['Local', 'Sellers and makers from close to home.'], ar: ['محلي', 'بائعين وصنّاع من بلدنا.'] },
  { letter: 'D', en: ['Delivered', 'Brought to your door, or picked up nearby.'], ar: ['لحد البيت', 'بيوصلك لباب البيت، أو استلمه من أقرب فرع.'] },
  { letter: 'O', en: ['Offers', 'Deals worth coming back for, every day.'], ar: ['عروض', 'عروض تستاهل ترجع لها كل يوم.'] },
  { letter: 'K', en: ['Kept promises', 'Fourteen days to change your mind.'], ar: ['وعد', '14 يوم ترجّع فيهم لو غيّرت رأيك.'] },
  { letter: 'A', en: ['Ask us', 'Real help whenever you need it.'], ar: ['اسألنا', 'مساعدة حقيقية وقت ما تحتاجها.'] },
  { letter: 'N', en: ['New finds', 'Something new to discover each visit.'], ar: ['جديد', 'حاجة جديدة تكتشفها كل زيارة.'] },
]

/**
 * ELDOKAN in outline. Hovering (or tapping) fills the letters in a wave and shows the word
 * each one stands for; the letter under the pointer lifts and tells its line of the story.
 */
export default function FooterWordmark({ locale }: { locale: Locale }) {
  const ar = locale === 'ar'
  const [active, setActive] = useState<number | null>(null)
  const [open, setOpen] = useState(false)
  const story = active === null ? null : STORY[active]

  return (
    <div className="eldokan-wordmark relative mx-auto max-w-7xl px-4 pb-8 pt-4 sm:px-6 lg:px-8" data-open={open || undefined} onMouseLeave={() => { setActive(null); setOpen(false) }}>
      <p className="sr-only">{ar ? 'قصة الدكان: ' : 'The Eldokan story: '}{STORY.map((item) => `${item.letter} — ${item[locale][0]}: ${item[locale][1]}`).join(' ')}</p>
      <div aria-hidden="true" dir="ltr" className="flex select-none justify-center" onMouseEnter={() => setOpen(true)}>
        {STORY.map((item, index) => (
          <button key={item.letter} type="button" tabIndex={-1} onMouseEnter={() => setActive(index)} onClick={() => { setOpen(true); setActive(active === index ? null : index) }}
            style={{ '--i': index } as React.CSSProperties} data-active={active === index || undefined}
            className="eldokan-letter group/letter relative flex flex-col items-center px-[0.5vw] lg:px-2">
            <span className="eldokan-glyph font-serif text-[15vw] leading-[0.9] lg:text-[170px]">{item.letter}</span>
            <span className="eldokan-word mt-1 whitespace-nowrap text-[9px] font-bold uppercase tracking-[0.18em] text-white sm:text-xs" dir={ar ? 'rtl' : 'ltr'}>{item[locale][0]}</span>
          </button>
        ))}
      </div>
      <p aria-hidden="true" className={`mt-4 min-h-6 text-center text-sm text-white/80 transition-opacity duration-300 sm:text-base ${story ? 'opacity-100' : 'opacity-0'}`}>
        {story && <><span className="font-serif text-lg font-bold text-white">{story.letter}</span> · <span className="font-bold text-white">{story[locale][0]}</span> — {story[locale][1]}</>}
      </p>
    </div>
  )
}
