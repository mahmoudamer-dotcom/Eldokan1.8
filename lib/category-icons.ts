import { BatteryFull, Cable, Car, Cpu, Gamepad2, HardDrive, Headphones, Laptop, Mouse, Package, PlugZap, Refrigerator, ScanBarcode, Scissors, ShieldCheck, Shirt, Smartphone, Sofa, Sparkles, Tag, Wifi, Wrench, type LucideIcon } from 'lucide-react'

/** Keyword (matched in the English slug or name) → icon. More specific words come first. */
const ICONS: Array<[RegExp, LucideIcon]> = [
  [/audio|headphone|speaker|sound/, Headphones],
  [/batter/, BatteryFull],
  [/cable|charger|adapter/, Cable],
  [/\bcar[\s-]/, Car],
  [/computer-access|computer accessor|mouse|keyboard/, Mouse],
  [/computer|laptop/, Laptop],
  [/storage|memory|flash|disk/, HardDrive],
  [/gaming|game/, Gamepad2],
  [/mobile|phone|tablet/, Smartphone],
  [/network|router|wifi/, Wifi],
  [/point-of-sale|point of sale|pos\b|barcode/, ScanBarcode],
  [/power|extension|strip/, PlugZap],
  [/security|camera|alarm/, ShieldCheck],
  [/tool|improvement|hardware/, Wrench],
  [/home-appliance|home appliance|fridge|kitchen/, Refrigerator],
  [/home/, Sofa],
  [/beauty|personal|care|cosmetic/, Sparkles],
  [/electronic/, Cpu],
  [/fashion|women|men|cloth|wear/, Shirt],
  [/handmade|craft/, Scissors],
  [/other/, Package],
]

export function categoryIconFor(category: { slug?: string | null; name?: string | null }): LucideIcon {
  const key = `${category.slug ?? ''} ${category.name ?? ''}`.toLowerCase()
  return ICONS.find(([pattern]) => pattern.test(key))?.[1] ?? Tag
}
