import { translate, type Locale } from './i18n'

export function customerError(cause: unknown, locale: Locale, fallback: string): string {
  const message = cause instanceof Error ? cause.message : fallback
  if (locale !== 'ar' || /[\u0600-\u06ff]/.test(message)) return message
  const translated = translate(message, locale)
  if (translated !== message) return translated
  const code = cause && typeof cause === 'object' && 'code' in cause ? String(cause.code) : ''
  const messages: Record<string, string> = {
    invalid_credentials: 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
    email_exists: 'البريد الإلكتروني مستخدم بالفعل. سجّل الدخول بحسابك.',
    email_already_exists: 'البريد الإلكتروني مستخدم بالفعل. سجّل الدخول بحسابك.',
    invalid_email: 'أدخل بريدًا إلكترونيًا صحيحًا.',
    invalid_phone: 'أدخل رقم هاتف صحيحًا.',
    not_authenticated: 'سجّل الدخول للمتابعة.',
    authentication_required: 'سجّل الدخول للمتابعة.',
    out_of_stock: 'المنتج غير متاح حاليًا. راجع المنتجات في سلتك.',
    insufficient_stock: 'الكمية المطلوبة أكبر من المخزون المتاح.',
    rate_limited: 'هناك محاولات كثيرة. انتظر قليلًا ثم حاول مرة أخرى.',
    csrf_invalid: 'انتهت صلاحية الجلسة. حدّث الصفحة وحاول مرة أخرى.',
  }
  return messages[code] ?? translate(fallback, locale)
}
