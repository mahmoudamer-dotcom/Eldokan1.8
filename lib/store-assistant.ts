import type { ProductCard, ProductListParams } from '@eldokan/customer-api-client'
import { createEldokanApi } from '@/lib/eldokan-api'
import { BUSINESS } from '@/lib/legal-policies'
import { lookupOrder, requestedOrderNumber } from '@/lib/order-lookup'

// Rule-based store assistant: answers common questions from store policies and
// searches the live catalog. No AI service and no per-message cost.

export type AssistantReply = { text: string; products: ProductCard[]; links: Array<{ href: string; label: string }> }
type Locale = 'ar' | 'en'
type Topic = { id: string; words: RegExp; answer: Record<Locale, string>; links?: Array<{ href: string; label: Record<Locale, string> }> }

const policy = (slug: string, ar: string, en: string) => ({ href: `/policies/${slug}`, label: { ar, en } })

const TOPICS: Topic[] = [
  { id: 'greeting', words: /^(hi|hello|hey|السلام|سلام|اهلا|أهلا|هاي|ازيك|إزيك|صباح|مساء)/i,
    answer: { ar: 'أهلًا بيك في الدكان! اسألني عن أي منتج (مثلًا: "لابتوب تحت 15000")، أو عن الشحن والدفع والإرجاع.', en: 'Welcome to ElDokan! Ask me about any product (e.g. "laptop under 15000"), or about shipping, payment and returns.' } },
  { id: 'thanks', words: /^(شكرا|شكرًا|متشكر|تسلم|thanks|thank you|thx)/i,
    answer: { ar: 'العفو! لو محتاج أي حاجة تانية أنا موجود.', en: 'You’re welcome! I’m here if you need anything else.' } },
  { id: 'return', words: /ترجيع|رجع|ارجاع|إرجاع|استرجاع|استبدال|استرداد|بدل|return|refund|exchange/i,
    answer: { ar: 'من حقك ترجّع أو تستبدل المنتج خلال ١٤ يوم من الاستلام، ولو فيه عيب خلال ٣٠ يوم، حسب قانون حماية المستهلك. ابعت للدعم رقم الطلب والمنتج وسبب الإرجاع.', en: 'You can return or exchange within 14 days of delivery, or 30 days for a defective item, under Egyptian consumer law. Send support your order number, the item and the reason.' },
    links: [policy('returns', 'سياسة الإرجاع', 'Returns policy')] },
  { id: 'cancel', words: /الغاء|إلغاء|الغي|ألغي|cancel/i,
    answer: { ar: `لإلغاء طلب كلم الدعم برقم الطلب في أسرع وقت قبل ما يتشحن: ${BUSINESS.phone} أو ${BUSINESS.email}.`, en: `To cancel, contact support with your order number before it ships: ${BUSINESS.phone} or ${BUSINESS.email}.` },
    links: [policy('buyer-terms', 'شروط الشراء', 'Purchase terms')] },
  { id: 'invoice', words: /فاتور|ايصال|وصل الدفع|invoice|receipt|bill/i,
    answer: { ar: 'الفاتورة موجودة في صفحة الطلب: افتح "طلباتي" (أو حسابك لو مسجل) واختار الطلب، هتلاقي الفاتورة فيها المنتجات والإجمالي ورقم الطلب. لو الطلب مدفوع هتلاقي زرار "طباعة / حفظ الفاتورة" تطبعها أو تحفظها PDF. ولو الدفع عند الاستلام، الفاتورة بتبقى جاهزة للطباعة بعد الدفع.', en: 'Your invoice is on the order page: open "My orders" (or your account if signed in) and choose the order to see the items, totals and order number. Once the order is paid, use "Print / save invoice" to print it or save it as PDF. For cash on delivery, it becomes printable after payment.' },
    links: [{ href: '/orders', label: { ar: 'طلباتي', en: 'My orders' } }, { href: '/account', label: { ar: 'حسابي', en: 'My account' } }] },
  { id: 'order', words: /طلبي|طلبى|الطلب بتاعي|اوردر|أوردر|الاوردر|تتبع|اتابع|وصل فين|فين الطلب|my order|order status|track/i,
    answer: { ar: 'تقدر تتابع حالة طلبك من صفحة "طلباتي". ولو عامل حساب هتلاقي كل طلباتك في حسابك. لو فيه مشكلة كلم الدعم برقم الطلب.', en: 'Track your order from "My orders". If you have an account, all your orders are in your account. For a problem, contact support with your order number.' },
    links: [{ href: '/orders', label: { ar: 'طلباتي', en: 'My orders' } }, { href: '/account', label: { ar: 'حسابي', en: 'My account' } }] },
  { id: 'shipping', words: /شحن|توصيل|يوصل|توصل|بيوصل|مصاريف الشحن|محافظ|delivery|shipping|ship|deliver/i,
    answer: { ar: 'بنوصّل لكل محافظات مصر. تكلفة الشحن بتظهر في صفحة إتمام الشراء بعد ما تكتب عنوانك، ومدة التوصيل التقديرية مكتوبة في صفحة كل منتج.', en: 'We deliver across Egypt. Shipping cost appears at checkout once you enter your address, and each product page shows an estimated delivery time.' },
    links: [policy('shipping', 'سياسة الشحن', 'Shipping policy')] },
  { id: 'payment', words: /دفع|ادفع|أدفع|فيزا|كارت|بطاقة|تقسيط|قسط|كاش|فلوس|ماستر|ميزة|pay|card|visa|installment|cash|cod/i,
    answer: { ar: 'تقدر تدفع كاش عند الاستلام، أو بالكارت (فيزا/ماستر/ميزة) أو تقسيط بنكي عن طريق Paymob لو متاح للطلب. بتختار طريقة الدفع في صفحة إتمام الشراء، وبيانات الكارت بتتكتب في صفحة Paymob الآمنة بس.', en: 'Pay cash on delivery, or by card (Visa/Mastercard/Meeza) or bank installments through Paymob when available. You choose at checkout; card details are entered only on Paymob’s secure page.' },
    links: [policy('buyer-terms', 'شروط الشراء', 'Purchase terms')] },
  { id: 'warranty', words: /ضمان|warranty|guarantee/i,
    answer: { ar: 'الضمان بيختلف حسب المنتج والبائع، ومكتوب في صفحة المنتج لو متاح. اكتبلي اسم المنتج وأجيبهولك.', en: 'Warranty depends on the product and seller and is shown on the product page when available. Tell me the product name and I’ll find it.' } },
  { id: 'original', words: /اصلي|أصلي|اورجينال|أورجينال|مضروب|تقليد|original|genuine|fake/i,
    answer: { ar: 'ممنوع على البائعين يعرضوا منتجات مقلدة. لو وصلك منتج مش مطابق للوصف من حقك ترجّعه.', en: 'Sellers may not list counterfeit products. If an item doesn’t match its description, you can return it.' },
    links: [policy('returns', 'سياسة الإرجاع', 'Returns policy')] },
  { id: 'account', words: /حساب|تسجيل|اسجل|أسجل|باسورد|كلمة السر|login|sign up|register|account|password/i,
    answer: { ar: 'تقدر تشتري من غير حساب. ولو عملت حساب هتلاقي طلباتك وعناوينك محفوظة. لو نسيت كلمة السر استخدم "نسيت كلمة المرور" في صفحة الدخول.', en: 'You can buy without an account. With an account, your orders and addresses are saved. Forgot your password? Use "Forgot password" on the sign-in page.' },
    links: [{ href: '/account', label: { ar: 'حسابي', en: 'My account' } }] },
  { id: 'seller', words: /ابيع|أبيع|بائع|تاجر|اعرض منتجات|sell|seller|vendor|merchant/i,
    answer: { ar: `عايز تبيع على الدكان؟ كلمنا على ${BUSINESS.phone} أو ابعت على ${BUSINESS.email} وهنبعتلك خطوات التسجيل.`, en: `Want to sell on ElDokan? Contact ${BUSINESS.phone} or ${BUSINESS.email} and we’ll send you the onboarding steps.` },
    links: [policy('seller-terms', 'شروط البائعين', 'Seller terms')] },
  { id: 'contact', words: /تواصل|اكلم|أكلم|كلمكم|رقمكم|تليفون|موبايل|واتس|ايميل|إيميل|دعم|خدمة العملاء|عنوانكم|مكانكم|فرع|contact|phone|whatsapp|email|support|address|location/i,
    answer: { ar: `تقدر تكلمنا على ${BUSINESS.phone} أو تبعت إيميل على ${BUSINESS.email}.`, en: `Call ${BUSINESS.phone} or email ${BUSINESS.email}.` } },
  { id: 'privacy', words: /خصوصية|بياناتي|privacy|my data/i,
    answer: { ar: 'بياناتك بتستخدم بس لتنفيذ طلبك وتوصيله ودعمك. ولو عايز تعدل أو تمسح بياناتك كلم الدعم.', en: 'Your data is used only to fulfill, deliver and support your order. Contact support to correct or delete it.' },
    links: [policy('privacy', 'إشعار الخصوصية', 'Privacy notice')] },
]

const ARABIC_DIGITS = /[٠-٩۰-۹]/g
function normalize(text: string) {
  return text.replace(ARABIC_DIGITS, (digit) => String(digit.charCodeAt(0) - (digit >= '۰' ? 0x06f0 : 0x0660)))
    .replace(/[إأآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ـ/g, '').replace(/\s+/g, ' ').trim()
}

/** Budget phrases like "تحت 500", "أقل من 2000 جنيه", "under 1500", "من 500 لـ 1000". */
function priceRange(text: string): Pick<ProductListParams, 'minPrice' | 'maxPrice'> {
  const amount = (value: string, thousand?: string) => Number(value.replace(/,/g, '')) * (thousand ? 1000 : 1)
  const between = text.match(/(?:من|between|from)\s*(\d[\d,]*)\s*(الف|k)?\s*(?:ل|لـ|الي|الى|to|and|-)\s*(\d[\d,]*)\s*(الف|k)?/i)
  if (between) return { minPrice: amount(between[1], between[2]), maxPrice: amount(between[3], between[4]) }
  const max = text.match(/(?:تحت|اقل من|ميزانيتي|ميزانية|لحد|حتي|حدود|بحدود|under|below|less than|max|up to|budget)\s*(\d[\d,]*)\s*(الف|k)?/i)
  if (max) return { maxPrice: amount(max[1], max[2]) }
  const min = text.match(/(?:فوق|اكتر من|اكثر من|اعلي من|over|above|more than)\s*(\d[\d,]*)\s*(الف|k)?/i)
  if (min) return { minPrice: amount(min[1], min[2]) }
  return {}
}

const FILLER = new Set(('عايز عاوز عايزة محتاج محتاجة ابحث ادور دور دورلي شوفلي وريني اعرضلي هات عندكم عندك عندكو فيه في هل ممكن لو سمحت من فضلك عن على علي ال لـ ل و او يا ' +
  'تحت اقل اكتر اكثر فوق حدود بحدود لحد حتي ميزانيتي ميزانية جنيه ج الف سعر بسعر ارخص رخيص كويس حلو احسن افضل منتج منتجات ' +
  'i want need looking for search find show me do you have any a an the some please under below less than over above more max up to budget egp pounds price cheap cheapest best good product products').split(' '))

function keywords(text: string) {
  return text.toLowerCase().replace(/[^\p{L}\p{N}\s-]/gu, ' ').split(' ')
    .filter((word) => word && !FILLER.has(word) && !/^\d+k?$/.test(word)).join(' ').slice(0, 100)
}

/** Product names are mostly English: common Arabic shopping words and their catalog terms. */
const ARABIC_TERMS: Array<[RegExp, string]> = [
  [/مفك/, 'screwdriver'], [/لاصق|شريط عازل|عازل/, 'tape'], [/غراء|جلو|لزق|صمغ/, 'glue'], [/مفتاح|مفاتيح/, 'wrench'], [/ترمومتر|ثرمومتر|حرار/, 'thermometer'], [/قلم|اقلام|رصاص/, 'pencil'], [/شنيور|دريل|مثقاب/, 'drill'],
  [/عده|عدة|ادوات|عدد/, 'tool'], [/لابتوب|لاب توب|كمبيوتر محمول/, 'laptop'], [/كمبيوتر|بي سي/, 'computer'], [/موبايل|تليفون|هاتف|جوال/, 'phone'],
  [/سماع|هيدفون|ايربودز/, 'headphone'], [/شاحن|شحن موبايل/, 'charger'], [/كابل|سلك|وصله/, 'cable'], [/ماوس/, 'mouse'], [/كيبورد|لوحه مفاتيح/, 'keyboard'],
  [/شاشه|شاشة|مونيتور/, 'monitor'], [/طابع|برنتر/, 'printer'], [/ساعه|ساعة/, 'watch'], [/شنط|حقيب/, 'bag'], [/هدوم|ملابس|تيشيرت|قميص/, 'shirt'],
  [/جزم|حذاء|كوتشي/, 'shoes'], [/كشاف|لمبه|اضاء/, 'light'], [/بطاري/, 'battery'], [/منشار/, 'saw'], [/شاكوش|مطرقه/, 'hammer'], [/متر|شريط قياس/, 'tape measure'],
  [/مكواه|مكواة/, 'iron'], [/خلاط/, 'blender'], [/غساله/, 'washing machine'], [/تلاجه|ثلاجه/, 'refrigerator'], [/تكييف|مكيف/, 'air conditioner'], [/مروحه/, 'fan'],
  [/عطر|برفان/, 'perfume'], [/هاند ميد|يدوي/, 'handmade'], [/اكسسوار/, 'accessories'], [/لعب|لعبه|العاب/, 'toy'],
]
function englishTerms(terms: string) {
  return [...new Set(ARABIC_TERMS.filter(([pattern]) => pattern.test(terms)).map(([, english]) => english))]
}

export async function answerCustomer(question: string, locale: Locale, request: Request, onCookie: (cookie: string) => void): Promise<AssistantReply> {
  const text = normalize(question)
  const reply = (answer: string, extra: Partial<AssistantReply> = {}): AssistantReply => ({ text: answer, products: [], links: [], ...extra })
  const number = requestedOrderNumber(text)
  if (number) return reply('', await lookupOrder(request, number, locale, onCookie))
  const topic = TOPICS.find((item) => item.words.test(text))
  const range = priceRange(text)
  const terms = keywords(text)
  const productQuestion = Boolean(range.maxPrice || range.minPrice || /ارخص|cheap|خصم|عروض|تخفيض|sale|offer|discount/i.test(text))

  // Policy and service questions answer directly unless the customer is clearly shopping.
  const smallTalk = topic && ['greeting', 'thanks'].includes(topic.id) && text.split(' ').length > 3
  if (topic && !smallTalk && !productQuestion) {
    return reply(topic.answer[locale], { links: (topic.links ?? []).map((link) => ({ href: link.href, label: link.label[locale] })) })
  }

  const api = createEldokanApi(locale)
  const onSale = /خصم|عروض|عرض|تخفيض|sale|offer|discount/i.test(text) || undefined
  const sort = /ارخص|رخيص|cheap/i.test(text) ? 'price_asc' as const : /اغلي|expensive/i.test(text) ? 'price_desc' as const : undefined
  try {
    let result = await api.products.list({ search: terms || undefined, ...range, onSale, sort, stockStatus: 'in_stock', perPage: 6 })
    // Arabic words for products that are listed under English names.
    for (const english of englishTerms(terms)) {
      if (result.data.length) break
      result = await api.products.list({ search: english, ...range, onSale, sort, stockStatus: 'in_stock', perPage: 6 })
    }
    // A multi-word query with no match: try the most specific single word.
    if (!result.data.length && terms.includes(' ')) {
      const longest = terms.split(' ').sort((a, b) => b.length - a.length)[0]
      result = await api.products.list({ search: longest, ...range, onSale, sort, stockStatus: 'in_stock', perPage: 6 })
    }
    if (result.data.length) {
      const budget = range.maxPrice ? (locale === 'ar' ? ` في حدود ${range.maxPrice.toLocaleString('ar-EG')} جنيه` : ` up to ${range.maxPrice.toLocaleString('en-EG')} EGP`) : ''
      return reply(locale === 'ar' ? `لقيتلك المنتجات دي${budget}. اضغط على أي منتج تشوف تفاصيله:` : `Here’s what I found${budget}. Tap a product for details:`, {
        products: result.data,
        links: result.meta.total > result.data.length && terms ? [{ href: `/search?q=${encodeURIComponent(terms)}`, label: locale === 'ar' ? `شوف كل النتائج (${result.meta.total})` : `See all ${result.meta.total} results` }] : [],
      })
    }
  } catch {
    return reply(locale === 'ar' ? 'مش قادر أوصل للمنتجات دلوقتي. جرّب تاني بعد شوية.' : 'I can’t reach the catalog right now. Please try again shortly.')
  }
  if (topic) return reply(topic.answer[locale], { links: (topic.links ?? []).map((link) => ({ href: link.href, label: link.label[locale] })) })
  return reply(locale === 'ar'
    ? `مالقيتش حاجة مطابقة${terms ? ` لـ "${terms}"` : ''}. جرّب اسم تاني للمنتج أو كلمة أبسط (مثلًا "سماعة" أو "laptop")، أو اسألني عن الشحن والدفع والإرجاع. ولو محتاج حد من فريقنا كلمنا على ${BUSINESS.phone}.`
    : `I couldn’t find a match${terms ? ` for "${terms}"` : ''}. Try another or simpler product name (e.g. "headphones"), or ask about shipping, payment or returns. To reach our team, call ${BUSINESS.phone}.`)
}
