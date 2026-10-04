import { NextResponse } from 'next/server'
import { createHmac } from 'node:crypto'
import { ProductsDetails } from '@/services/productdetails'

type CheckoutRequest = {
  productId?: unknown
  firstName?: unknown
  lastName?: unknown
  email?: unknown
  phoneNumber?: unknown
  city?: unknown
  area?: unknown
  street?: unknown
  building?: unknown
  apartment?: unknown
  floor?: unknown
  landmark?: unknown
  postalCode?: unknown
  instructions?: unknown
  latitude?: unknown
  longitude?: unknown
}

function text(value: unknown, maxLength: number) {
  return typeof value === 'string' ? value.trim().slice(0, maxLength) : ''
}

export async function POST(request: Request) {
  const secretKey = process.env.PAYMOB_SECRET_KEY?.trim()
  const publicKey = process.env.PAYMOB_PUBLIC_KEY?.trim()
  const paymentMethodId = process.env.PAYMOB_CARD_INTEGRATION_ID?.trim()
  const configuredOrigin = process.env.NEXT_PUBLIC_SITE_URL?.trim()
  const signingSecret = process.env.PAYMOB_CHECKOUT_SIGNING_SECRET?.trim()
  if (!secretKey || !publicKey || !paymentMethodId || !configuredOrigin || !signingSecret) {
    return NextResponse.json({ error: 'Card payments are not configured yet.' }, { status: 503 })
  }

  let body: CheckoutRequest
  try {
    body = await request.json() as CheckoutRequest
  } catch {
    return NextResponse.json({ error: 'Invalid checkout details.' }, { status: 400 })
  }

  const productId = text(body.productId, 100)
  const firstName = text(body.firstName, 100)
  const lastName = text(body.lastName, 100)
  const email = text(body.email, 254)
  const phoneNumber = text(body.phoneNumber, 30)
  const city = text(body.city, 100)
  const area = text(body.area, 120)
  const street = text(body.street, 160)
  const building = text(body.building, 40)
  const apartment = text(body.apartment, 40) || 'NA'
  const floor = text(body.floor, 40) || 'NA'
  const landmark = text(body.landmark, 120)
  const postalCode = text(body.postalCode, 20) || 'NA'
  const instructions = text(body.instructions, 250)
  const latitude = typeof body.latitude === 'number' && Number.isFinite(body.latitude) && body.latitude >= -90 && body.latitude <= 90 ? body.latitude : null
  const longitude = typeof body.longitude === 'number' && Number.isFinite(body.longitude) && body.longitude >= -180 && body.longitude <= 180 ? body.longitude : null
  if (!productId || !firstName || !lastName || !/^\S+@\S+\.\S+$/.test(email) || phoneNumber.length < 7 || !city || !area || !street || !building) {
    return NextResponse.json({ error: 'Enter your contact details and complete delivery address.' }, { status: 400 })
  }
  if (!/^\d+$/.test(paymentMethodId)) {
    return NextResponse.json({ error: 'The Paymob card integration ID is invalid.' }, { status: 503 })
  }

  const locale = request.headers.get('x-eldokan-locale') === 'ar' ? 'ar' : 'en'
  const productResponse = await ProductsDetails(productId, locale)
  const product = productResponse.data
  if (!product?.id) return NextResponse.json({ error: 'Product not found.' }, { status: 404 })
  if (product.stock?.status === 'out_of_stock') return NextResponse.json({ error: 'This product is currently unavailable.' }, { status: 409 })

  const amount = product.pricing.on_sale ? product.pricing.sale_price.amount : product.pricing.regular_price.amount
  const amountCents = Math.round(amount * 100)
  if (!Number.isSafeInteger(amountCents) || amountCents < 1) {
    return NextResponse.json({ error: 'This product has an invalid price.' }, { status: 400 })
  }

  let redirectUrl: string
  const referencePayload = Buffer.from(JSON.stringify({ productId, amountCents, nonce: crypto.randomUUID() })).toString('base64url')
  const signature = createHmac('sha256', signingSecret).update(referencePayload).digest('hex')
  const specialReference = `eldokan.${referencePayload}.${signature}`
  try {
    const siteUrl = new URL(configuredOrigin)
    if (siteUrl.protocol !== 'https:' && siteUrl.hostname !== 'localhost') throw new Error('Invalid site URL protocol')
    const resultUrl = new URL('/checkout/result', siteUrl)
    resultUrl.searchParams.set('reference', specialReference)
    redirectUrl = resultUrl.toString()
  } catch {
    return NextResponse.json({ error: 'The site URL is not configured correctly.' }, { status: 503 })
  }
  let paymobResponse: Response
  try {
    paymobResponse = await fetch('https://accept.paymob.com/v1/intention/', {
      method: 'POST',
      headers: { Authorization: `Token ${secretKey}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        amount: amountCents,
        currency: 'EGP',
        payment_methods: [Number(paymentMethodId)],
        items: [{ name: product.name.slice(0, 255), amount: amountCents, description: `Eldokan product ${product.id}${product.sku ? ` SKU ${product.sku}` : ''}`, quantity: 1 }],
        billing_data: {
          first_name: firstName,
          last_name: lastName,
          email,
          phone_number: phoneNumber,
          apartment, building, floor, street: `${area}, ${street}${landmark ? `, near ${landmark}` : ''}`.slice(0, 200),
          city, state: city, country: 'EG', postal_code: postalCode,
          extra_description: [latitude !== null && longitude !== null ? `GPS ${latitude},${longitude}` : '', instructions].filter(Boolean).join(' | ').slice(0, 255) || 'NA',
        },
        special_reference: specialReference,
        expiration: 3600,
        redirection_url: redirectUrl,
      }),
      cache: 'no-store',
    })
  } catch {
    return NextResponse.json({ error: 'Could not connect to the payment provider.' }, { status: 502 })
  }

  const result: unknown = await paymobResponse.json().catch(() => null)
  if (!paymobResponse.ok || !result || typeof result !== 'object' || !('client_secret' in result) || typeof result.client_secret !== 'string') {
    console.error('Paymob intention creation failed with status:', paymobResponse.status)
    return NextResponse.json({ error: 'Could not start card payment. Please try again.' }, { status: 502 })
  }

  const checkoutUrl = new URL('https://accept.paymob.com/unifiedcheckout/')
  checkoutUrl.searchParams.set('publicKey', publicKey)
  checkoutUrl.searchParams.set('clientSecret', result.client_secret)
  return NextResponse.json({ checkoutUrl: checkoutUrl.toString() })
}
