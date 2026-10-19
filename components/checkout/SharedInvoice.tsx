'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import { Download } from 'lucide-react'
import type { OrderDetail } from '@eldokan/customer-api-client'
import { useLocale } from '@/components/i18n/LocaleProvider'
import { orderNumber } from '@/lib/order-status'
import OrderInvoice from './OrderInvoice'

/**
 * The invoice opened from its QR code. The PDF is drawn from the rendered page,
 * so Arabic text keeps the browser's shaping, and it downloads once on arrival.
 */
export default function SharedInvoice({ order }: { order: OrderDetail }) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const [busy, setBusy] = useState(false)
  const [failed, setFailed] = useState(false)
  const started = useRef(false)

  const download = useCallback(async () => {
    const node = document.querySelector<HTMLElement>('.invoice-print-root')
    if (!node) return
    setBusy(true); setFailed(false)
    try {
      const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas-pro'), import('jspdf')])
      // Let the QR codes render before taking the picture.
      await new Promise((resolve) => setTimeout(resolve, 400))
      const canvas = await html2canvas(node, { scale: 2, backgroundColor: '#ffffff', useCORS: true })
      const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' })
      const pageWidth = pdf.internal.pageSize.getWidth(), pageHeight = pdf.internal.pageSize.getHeight()
      const ratio = Math.min(pageWidth / canvas.width, pageHeight / canvas.height)
      const width = canvas.width * ratio, height = canvas.height * ratio
      pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', (pageWidth - width) / 2, 0, width, height)
      pdf.save(`eldokan-invoice-${orderNumber(order).replace(/[^0-9A-Za-z-]/g, '') || 'order'}.pdf`)
    } catch {
      setFailed(true)
    } finally { setBusy(false) }
  }, [order])

  useEffect(() => {
    if (started.current) return
    started.current = true
    void download()
  }, [download])

  return <>
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
      <p className="text-sm text-muted-foreground">{busy ? (ar ? 'جارٍ تجهيز ملف PDF…' : 'Preparing your PDF…') : (ar ? 'نسخة الفاتورة من رابط الـ QR.' : 'Invoice copy from the QR code.')}</p>
      <button type="button" disabled={busy} onClick={() => void download()} className="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-5 font-semibold text-primary-foreground disabled:opacity-60">
        <Download aria-hidden="true" className="size-4" />{ar ? 'تحميل PDF' : 'Download PDF'}
      </button>
    </div>
    {failed && <p role="alert" className="mb-4 text-sm text-danger-foreground">{ar ? 'مقدرناش نعمل ملف PDF على الجهاز ده. استخدم زرار الطباعة في المتصفح واختار "حفظ كـ PDF".' : 'Could not create the PDF on this device. Use your browser’s print option and choose “Save as PDF”.'}</p>}
    <OrderInvoice order={order} shared />
  </>
}
