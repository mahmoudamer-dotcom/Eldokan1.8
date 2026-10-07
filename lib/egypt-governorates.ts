export const EGYPT_GOVERNORATES = [
  { code: 'EGALX', en: 'Alexandria', ar: 'الإسكندرية' },
  { code: 'EGASN', en: 'Aswan', ar: 'أسوان' },
  { code: 'EGAST', en: 'Asyut', ar: 'أسيوط' },
  { code: 'EGBA', en: 'Red Sea', ar: 'البحر الأحمر' },
  { code: 'EGBH', en: 'Beheira', ar: 'البحيرة' },
  { code: 'EGBNS', en: 'Beni Suef', ar: 'بني سويف' },
  { code: 'EGC', en: 'Cairo', ar: 'القاهرة' },
  { code: 'EGDK', en: 'Dakahlia', ar: 'الدقهلية' },
  { code: 'EGDT', en: 'Damietta', ar: 'دمياط' },
  { code: 'EGFYM', en: 'Faiyum', ar: 'الفيوم' },
  { code: 'EGGH', en: 'Gharbia', ar: 'الغربية' },
  { code: 'EGGZ', en: 'Giza', ar: 'الجيزة' },
  { code: 'EGIS', en: 'Ismailia', ar: 'الإسماعيلية' },
  { code: 'EGJS', en: 'South Sinai', ar: 'جنوب سيناء' },
  { code: 'EGKB', en: 'Qalyubia', ar: 'القليوبية' },
  { code: 'EGKFS', en: 'Kafr el-Sheikh', ar: 'كفر الشيخ' },
  { code: 'EGKN', en: 'Qena', ar: 'قنا' },
  { code: 'EGLX', en: 'Luxor', ar: 'الأقصر' },
  { code: 'EGMN', en: 'Minya', ar: 'المنيا' },
  { code: 'EGMNF', en: 'Monufia', ar: 'المنوفية' },
  { code: 'EGMT', en: 'Matrouh', ar: 'مطروح' },
  { code: 'EGPTS', en: 'Port Said', ar: 'بورسعيد' },
  { code: 'EGSHG', en: 'Sohag', ar: 'سوهاج' },
  { code: 'EGSHR', en: 'Al Sharqia', ar: 'الشرقية' },
  { code: 'EGSIN', en: 'North Sinai', ar: 'شمال سيناء' },
  { code: 'EGSUZ', en: 'Suez', ar: 'السويس' },
  { code: 'EGWAD', en: 'New Valley', ar: 'الوادي الجديد' },
] as const

export function getEgyptGovernorateOptions(apiStates: string[]) {
  return apiStates.length ? apiStates : EGYPT_GOVERNORATES.map(({ code }) => code)
}

export function getEgyptGovernorateLabel(state: string, locale: 'ar' | 'en') {
  const normalized = state.trim().toLocaleLowerCase()
  const governorate = EGYPT_GOVERNORATES.find(({ code, en, ar }) =>
    code.toLocaleLowerCase() === normalized || en.toLocaleLowerCase() === normalized || ar === state.trim(),
  )
  return governorate ? governorate[locale] : state
}
