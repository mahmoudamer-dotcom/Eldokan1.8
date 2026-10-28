'use client'

import { useState } from 'react'
import type { Locale } from '@/lib/i18n'

type Part = { glyph: string; letter: string; word: string; line: string }

const ZWJ = '‍'

/** ELDOKAN: each letter carries one part of the Eldokan story. */
const ENGLISH: Part[] = [
  { glyph: 'E', letter: 'E', word: 'Everyday', line: 'Your daily essentials, in one place.' },
  { glyph: 'L', letter: 'L', word: 'Limitless', line: 'Products from sellers in Egypt and around the world.' },
  { glyph: 'D', letter: 'D', word: 'Delivered', line: 'Brought to your door, or picked up nearby.' },
  { glyph: 'O', letter: 'O', word: 'Offers', line: 'Deals worth coming back for, every day.' },
  { glyph: 'K', letter: 'K', word: 'Kept promises', line: 'Fourteen days to change your mind.' },
  { glyph: 'A', letter: 'A', word: 'Ask us', line: 'Real help whenever you need it.' },
  { glyph: 'N', letter: 'N', word: 'New finds', line: 'Something new to discover each visit.' },
]

/**
 * الدكان: each letter starts its own word of the story. Arabic letters join, so every piece
 * keeps its connected shape with a zero-width joiner on the joining side (لـ ـد كـ ـا).
 */
const ARABIC: Part[] = [
  { glyph: 'ا', letter: 'ا', word: 'احتياجاتك', line: 'احتياجاتك اليومية في مكان واحد.' },
  { glyph: `ل${ZWJ}`, letter: 'ل', word: 'لحد بابك', line: 'بيوصلك لباب البيت، أو استلمه من أقرب فرع.' },
  { glyph: `${ZWJ}د`, letter: 'د', word: 'دايمًا عروض', line: 'عروض تستاهل ترجع لها كل يوم.' },
  { glyph: `ك${ZWJ}`, letter: 'ك', word: 'كلمتنا وعد', line: '14 يوم ترجّع فيهم لو غيّرت رأيك.' },
  { glyph: `${ZWJ}ا`, letter: 'ا', word: 'اسألنا', line: 'مساعدة حقيقية وقت ما تحتاجها.' },
  { glyph: 'ن', letter: 'ن', word: 'نجمعلك', line: 'بنجمعلك منتجات من كل مكان، من مصر وبرّه مصر.' },
]

/**
 * The store name in outline, in the visitor's language. Hovering (or tapping) fills the letters
 * in a wave; the letter under the pointer lifts and tells its line of the story.
 */
export default function FooterWordmark({ locale }: { locale: Locale }) {
  const ar = locale === 'ar'
  const parts = ar ? ARABIC : ENGLISH
  const [active, setActive] = useState<number | null>(null)
  const [open, setOpen] = useState(false)
  const part = active === null ? null : parts[active]

  return (
    <div className="eldokan-wordmark relative mx-auto max-w-7xl px-4 pb-8 pt-4 sm:px-6 lg:px-8" data-open={open || undefined} data-joined={ar || undefined} onMouseLeave={() => { setActive(null); setOpen(false) }}>
      <p className="sr-only">{ar ? 'قصة الدكان: ' : 'The Eldokan story: '}{parts.map((item) => `${item.letter} — ${item.word}: ${item.line}`).join(' ')}</p>
      <div aria-hidden="true" dir={ar ? 'rtl' : 'ltr'} className="relative flex select-none justify-center" onMouseEnter={() => setOpen(true)}>
        {/* Arabic outline: the whole word drawn as one joined run, so no seams show where letters meet.
            The letters above it carry the hover and the fill, without outlines of their own. */}
        {ar && <span className="eldokan-outline pointer-events-none absolute inset-x-0 top-0 text-center text-[24vw] font-black leading-[1.15] lg:text-[230px]" style={{ fontFamily: 'var(--font-arabic)' }}>{parts.map((item) => item.letter).join('')}</span>}
        {parts.map((item, index) => (
          <button key={index} type="button" tabIndex={-1} onMouseEnter={() => setActive(index)} onClick={() => { setOpen(true); setActive(active === index ? null : index) }}
            style={{ '--i': index } as React.CSSProperties} data-active={active === index || undefined}
            className={`eldokan-letter relative flex flex-col items-center ${ar ? 'px-0' : 'px-[0.5vw] lg:px-2'}`}>
            <span className={`eldokan-glyph ${ar ? 'text-[24vw] font-black leading-[1.15] lg:text-[230px]' : 'font-serif text-[15vw] leading-[0.9] lg:text-[170px]'}`} style={ar ? { fontFamily: 'var(--font-arabic)' } : undefined}>{item.glyph}</span>
            {/* Joined Arabic letters keep their width, so only the active letter shows its word, as a pill placed below
                the lowest letter (the ن bowl) so it never sits on a glyph. */}
            <span className={`eldokan-word whitespace-nowrap text-[9px] font-bold uppercase tracking-[0.18em] sm:text-xs ${ar ? 'absolute top-full mt-[6vw] rounded-full bg-white px-2.5 py-0.5 tracking-normal text-black sm:text-sm lg:mt-12 lg:px-3.5 lg:py-1 lg:text-base' : 'mt-1 text-white'}`}>{item.word}</span>
          </button>
        ))}
      </div>
      <p aria-hidden="true" className={`min-h-6 text-center text-sm text-white/80 transition-opacity duration-300 sm:text-base ${ar ? 'mt-[11vw] lg:mt-20' : 'mt-4'} ${part ? 'opacity-100' : 'opacity-0'}`}>
        {part && <><span className={`text-lg font-bold text-white ${ar ? '' : 'font-serif'}`}>{part.letter}</span> · <span className="font-bold text-white">{part.word}</span> — {part.line}</>}
      </p>
    </div>
  )
}
