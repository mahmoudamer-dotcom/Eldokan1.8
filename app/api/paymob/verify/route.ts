import { createHmac, timingSafeEqual } from 'node:crypto'
import { inflateRawSync } from 'node:zlib'
import { NextResponse } from 'next/server'
import { ProductsDetails } from '@/services/productdetails'

type PaymentRecord = {
  success?: boolean
  pending?: boolean
  is_refunded?: boolean
  amount_cents?: number
  currency?: string
  id?: number
  created_at?: string
  paid_at?: string
  integration_id?: number
  order?: { id?: number; merchant_order_id?: string; created_at?: string }
  billing_data?: Record<string, unknown>
}
type SignedLine = { productId: string; variationId: string | null; quantity: number; unitAmountCents: number }

function verifyReference(reference: string, signingSecret: string) {
  const match = /^eldokan\.([A-Za-z0-9_-]{1,3000})\.([a-f0-9]{64})$/.exec(reference)
  if (!match) return null
  const expected = createHmac('sha256', signingSecret).update(match[1]).digest()
  const supplied = Buffer.from(match[2], 'hex')
  if (expected.length !== supplied.length || !timingSafeEqual(expected, supplied)) return null
  try {
    const decoded: unknown = JSON.parse(inflateRawSync(Buffer.from(match[1], 'base64url'), { maxOutputLength: 16_384 }).toString('utf8'))
    if (!decoded || typeof decoded !== 'object' || !('amountCents' in decoded) || typeof decoded.amountCents !== 'number' || !Number.isSafeInteger(decoded.amountCents) || decoded.amountCents < 1 || !('lines' in decoded) || !Array.isArray(decoded.lines) || decoded.lines.length < 1 || decoded.lines.length > 50) return null
    const lines: SignedLine[] = []
    for (const value of decoded.lines) {
      if (!Array.isArray(value) || value.length !== 4) return null
      const [productId, variationId, quantity, unitAmountCents] = value
      if (typeof productId !== 'string' || !/^prd_[1-9][0-9]*$/.test(productId)) return null
      if (variationId !== null && (typeof variationId !== 'string' || !/^var_[1-9][0-9]*$/.test(variationId))) return null
      if (typeof quantity !== 'number' || !Number.isInteger(quantity) || quantity < 1 || quantity > 999) return null
      if (typeof unitAmountCents !== 'number' || !Number.isSafeInteger(unitAmountCents) || unitAmountCents < 1) return null
      lines.push({ productId, variationId, quantity, unitAmountCents })
    }
    const calculatedTotal = lines.reduce((total, line) => total + line.quantity * line.unitAmountCents, 0)
    if (calculatedTotal !== decoded.amountCents || !Number.isSafeInteger(calculatedTotal)) return null
    return { lines, amountCents: decoded.amountCents }
  } catch { return null }
}

export async function GET(request: Request) {
  const params = new URL(request.url).searchParams
  const reference = params.get('reference') ?? ''
  const secretKey = process.env.PAYMOB_SECRET_KEY?.trim()
  const apiKey = process.env.PAYMOB_API_KEY?.trim()
  const signingSecret = process.env.PAYMOB_CHECKOUT_SIGNING_SECRET?.trim()
  if (!secretKey || !apiKey || !signingSecret) return NextResponse.json({ error: 'Payment verification is not configured.' }, { status: 503 })
  const order = verifyReference(reference, signingSecret)
  if (!order) return NextResponse.json({ error: 'Invalid checkout reference.' }, { status: 400 })

  try {
    const authResponse = await fetch('https://accept.paymob.com/api/auth/tokens', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ api_key: apiKey }), cache: 'no-store',
    })
    const authData: unknown = await authResponse.json()
    const token = authData && typeof authData === 'object' && 'token' in authData && typeof authData.token === 'string' ? authData.token : ''
    if (!authResponse.ok || !token) throw new Error('Paymob authentication failed')

    const inquiryResponse = await fetch('https://accept.paymob.com/api/ecommerce/orders/transaction_inquiry', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ auth_token: token, merchant_order_id: reference }), cache: 'no-store',
    })
    const payment: unknown = await inquiryResponse.json()
    if (!inquiryResponse.ok || !payment || typeof payment !== 'object') throw new Error('Paymob inquiry failed')
    const record = payment as PaymentRecord
    if (record.order?.merchant_order_id !== reference) return NextResponse.json({ status: 'pending' })
    if (record.pending) return NextResponse.json({ status: 'pending' })
    if (record.success !== true || record.is_refunded === true || record.currency !== 'EGP' || record.amount_cents !== order.amountCents) {
      return NextResponse.json({ status: 'failed' })
    }

    const products = await Promise.all(order.lines.map(async (line) => {
      const productResponse = await ProductsDetails(line.productId)
      const product = productResponse.data
      if (!product?.id) throw new Error('Product record not found')
      return {
        id: product.id,
        name: product.name,
        sku: product.sku,
        imageUrl: product.images[0]?.url,
        variationId: line.variationId,
        quantity: line.quantity,
        unitAmountCents: line.unitAmountCents,
        lineAmountCents: line.unitAmountCents * line.quantity,
      }
    }))
    return NextResponse.json({
      status: 'paid',
      invoice: {
        transactionId: record.id,
        orderId: record.order?.id,
        paidAt: record.paid_at || record.created_at || record.order?.created_at,
        amountCents: record.amount_cents,
        currency: record.currency,
        items: products,
        customer: record.billing_data ?? {},
      },
    })
  } catch (cause) {
    console.error('Paymob payment verification failed:', cause instanceof Error ? cause.message : 'Unknown error')
    return NextResponse.json({ error: 'Could not verify payment yet.' }, { status: 502 })
  }
}
