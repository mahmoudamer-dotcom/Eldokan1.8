import type { CategoryDetail } from '@eldokan/customer-api-client'

export type CategoryWorld = 'technology' | 'beauty' | 'fashion' | 'handmade' | 'home' | 'tools' | 'explore'
type CategoryNode = { id: string; slug: string; children: Array<{ id: string; slug: string }> }

// Canonical slugs, rather than translated names, keep the same design in both languages.
export function categoryWorld(slug: string, category: CategoryDetail | null | undefined, roots: CategoryNode[]): CategoryWorld {
  const parent = roots.find((root) => root.slug === slug || root.id === category?.parent_id
    || root.children.some((child) => child.slug === slug || child.id === category?.parent_id))
  const identity = `${parent?.slug ?? ''} ${slug}`.toLowerCase().replace(/[_-]+/g, ' ')
  if (/beauty|personal care|cosmetic|skincare|perfume/.test(identity)) return 'beauty'
  if (/fashion|clothing|women|men wear|shoes/.test(identity)) return 'fashion'
  if (/handmade|hand made|craft/.test(identity)) return 'handmade'
  if (/tools|home improvement|hardware/.test(identity)) return 'tools'
  if (/home|appliance|furniture|kitchen/.test(identity)) return 'home'
  if (/electronic|computer|audio|batter|cable|charger|mobile|tablet|gaming|network|security system|data storage|power strip|point of sale|car accessories/.test(identity)) return 'technology'
  return 'explore'
}

export const categoryWorldCopy: Record<CategoryWorld, { eyebrow: [string, string]; heading: [string, string]; description: [string, string]; guide: [string, string] }> = {
  technology: {
    eyebrow: ['Built around your next upgrade', 'اختيارات تواكب احتياجك'],
    heading: ['Find the right upgrade.', 'اختار التجربة اللي تناسبك.'],
    description: ['Explore your options, compare the specifications, and choose what fits your setup.', 'استكشف الاختيارات، قارن المواصفات، واختار اللي يناسب استخدامك وأجهزتك.'],
    guide: ['Compare compatibility, specifications and seller details.', 'قارن التوافق والمواصفات وبيانات البائع قبل الاختيار.'],
  },
  beauty: {
    eyebrow: ['A little space for your routine', 'مساحة لروتينك اليومي'],
    heading: ['Make your routine yours.', 'روتينك يبدأ من اختيارك.'],
    description: ['Discover care and beauty products at your own pace, with the details that help you choose.', 'استكشف منتجات العناية والجمال على راحتك، وراجع التفاصيل اللي تساعدك تختار.'],
    guide: ['Check ingredients, size and product directions.', 'راجع المكونات والحجم وطريقة استخدام المنتج.'],
  },
  fashion: {
    eyebrow: ['Your next edit', 'اختيارات على ذوقك'],
    heading: ['Your style. Your choices.', 'ستايلك يبدأ من اختيارك.'],
    description: ['Build your own edit. Explore the pieces, then choose the size and details that suit you.', 'كوّن اختياراتك بنفسك. شوف القطع، وبعدها اختار المقاس والتفاصيل اللي تناسبك.'],
    guide: ['Compare size, material and available variations.', 'قارن المقاس والخامة والاختيارات المتاحة لكل قطعة.'],
  },
  handmade: {
    eyebrow: ['Made with a personal touch', 'تفاصيل بلمسة مختلفة'],
    heading: ['Find a piece with character.', 'قطع لها طابعها الخاص.'],
    description: ['Explore handmade finds and the sellers behind them. Look closer at the materials and finishing.', 'اكتشف المنتجات اليدوية والبائعين وراها. شوف الخامات والتشطيب والتفاصيل عن قرب.'],
    guide: ['Look at materials, dimensions and seller information.', 'راجع الخامة والمقاسات وبيانات البائع.'],
  },
  home: {
    eyebrow: ['For the spaces you live in', 'للمكان اللي بتحبه'],
    heading: ['Make room for better living.', 'تفاصيل تخلي بيتك أريح.'],
    description: ['Find practical pieces for your home, with the dimensions and details that matter to your space.', 'اختار منتجات عملية لبيتك، وراجع المقاسات والتفاصيل اللي تناسب مساحتك.'],
    guide: ['Check dimensions, capacity and installation requirements.', 'راجع المقاسات والسعة ومتطلبات التركيب عند توفرها.'],
  },
  tools: {
    eyebrow: ['Ready for your next project', 'جاهز لخطوتك الجاية'],
    heading: ['The right tool for the job.', 'كل شغلانة ليها عدتها.'],
    description: ['Choose by the job you need to do. Compare power, materials and the contents of each kit.', 'اختار حسب الشغل اللي محتاج تعمله. قارن القدرة والخامات ومحتويات كل طقم.'],
    guide: ['Check specifications, accessories and intended use.', 'راجع المواصفات والملحقات والاستخدام المناسب للأداة.'],
  },
  explore: {
    eyebrow: ['Discover your next find', 'اكتشف اختيارك الجاي'],
    heading: ['Something worth discovering.', 'اختيارات تستاهل تشوفها.'],
    description: ['Browse this collection, save what you like, and compare the details before making your choice.', 'تصفح المجموعة، احفظ اللي يعجبك، وقارن التفاصيل قبل ما تختار.'],
    guide: ['Compare the product details and available options.', 'قارن تفاصيل المنتج والاختيارات المتاحة.'],
  },
}
