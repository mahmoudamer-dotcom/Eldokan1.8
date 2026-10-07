import { createEldokanCustomerApiClient, EldokanClientError } from '@eldokan/customer-api-client'
import type { AddressId, AddressCreate, AddressUpdate, CartItemId, ProductId, ProductListParams, VariationId, PurchaseInput, QuoteInput, OrderId, PaymentInput, SellerId, Language } from '@eldokan/customer-api-client'
import type { NextRequest } from 'next/server'
import { appendCustomerApiCookies, createCustomerApiTransport } from '@/lib/customer-api-transport'

const DEFAULT_API_BASE_URL = 'https://www.eldokan.com/wp-json/eldokan-customer/v1'
export const runtime = 'nodejs'

type RouteContext = { params: Promise<{ path: string[] }> }

function errorResponse(error: unknown) {
  if (error instanceof SyntaxError) {
    return Response.json({
      success: false,
      error: { code: 'invalid_json', message: 'Request body must contain valid JSON.' },
      meta: { request_id: null },
    }, { status: 400, headers: { 'Cache-Control': 'no-store' } })
  }

  if (error instanceof EldokanClientError) {
    const status = error.status ?? (error.kind === 'network' || error.kind === 'timeout' ? 502 : error.kind === 'validation' ? 400 : 500)
    const message = error.kind === 'network' || error.kind === 'timeout'
      ? 'The storefront server could not reach the Eldokan customer API. Check ELDOKAN_API_BASE_URL and server connectivity.'
      : error.message
    return Response.json({
      success: false,
      error: { code: error.code, message, ...(error.issues.length ? { issues: error.issues } : {}) },
      meta: { request_id: error.requestId },
    }, { status, headers: { 'Cache-Control': 'no-store' } })
  }

  return Response.json({
    success: false,
    error: { code: 'customer_api_error', message: 'Unable to complete the customer API request.' },
    meta: { request_id: null },
  }, { status: 500, headers: { 'Cache-Control': 'no-store' } })
}

async function handleRequest(request: NextRequest, context: RouteContext, cookies: string[]) {
  const { path } = await context.params
  const endpoint = path.join('/')
  const method = request.method
  const isMutation = method === 'POST' || method === 'PATCH' || method === 'DELETE'

  // Placement and Guest Order access stay in the HttpOnly recovery BFF so a
  // Guest capability can never be returned to browser JavaScript.
  if (endpoint === 'checkout/orders' && method === 'POST') {
    return Response.json({ success: false, error: { code: 'protected_checkout_required', message: 'Use the protected checkout recovery flow to place an order.' }, meta: { request_id: null } }, { status: 403, headers: { 'Cache-Control': 'no-store' } })
  }
  if (endpoint.startsWith('orders/') && request.headers.has('x-eldokan-order-access')) {
    return Response.json({ success: false, error: { code: 'protected_guest_order_required', message: 'Guest Order access is available only through protected recovery.' }, meta: { request_id: null } }, { status: 403, headers: { 'Cache-Control': 'no-store' } })
  }

  if (isMutation && request.headers.get('origin') !== new URL(request.url).origin) {
    return Response.json({ success: false, error: { code: 'invalid_origin', message: 'Request origin is not allowed.' }, meta: { request_id: null } }, { status: 403 })
  }

  const api = createEldokanCustomerApiClient({
    baseUrl: process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
    fetch: createCustomerApiTransport(request, (cookie) => cookies.push(cookie)),
  })

  const langValue = request.nextUrl.searchParams.get('lang')
  const lang: Language | undefined = langValue === 'ar' || langValue === 'en' ? langValue : undefined
  const verifyCsrf = async () => {
    let token: string
    try {
      token = (await api.auth.session()).data.csrf_token
    } catch {
      token = (await api.cart.get({ lang })).data.csrf_token
    }
    return request.headers.get('x-eldokan-csrf') === token
  }

  try {
    let result: unknown
    if (endpoint === 'health' && method === 'GET') {
      result = await api.health.get()
    } else if (endpoint === 'home' && method === 'GET') {
      result = await api.home.get({ lang })
    } else if (endpoint === 'categories' && method === 'GET') {
      result = await api.categories.list({ parent: request.nextUrl.searchParams.get('parent') ?? undefined, lang })
    } else if (endpoint.startsWith('categories/') && endpoint.endsWith('/filters') && method === 'GET') {
      const slug = decodeURIComponent(endpoint.slice('categories/'.length, -'/filters'.length))
      result = await api.categories.filters(slug, { lang })
    } else if (endpoint.startsWith('categories/') && method === 'GET') {
      result = await api.categories.get(decodeURIComponent(endpoint.slice('categories/'.length)), { lang })
    } else if ((endpoint === 'brands' || endpoint === 'tags') && method === 'GET') {
      const params = {
        page: request.nextUrl.searchParams.has('page') ? Number(request.nextUrl.searchParams.get('page')) : undefined,
        perPage: request.nextUrl.searchParams.has('per_page') ? Number(request.nextUrl.searchParams.get('per_page')) : undefined,
        search: request.nextUrl.searchParams.get('search') ?? undefined,
        lang,
      }
      result = endpoint === 'brands' ? await api.brands.list(params) : await api.tags.list(params)
    } else if (endpoint.startsWith('sellers/') && method === 'GET') {
      result = await api.sellers.get(decodeURIComponent(endpoint.slice('sellers/'.length)) as SellerId, { lang })
    } else if (endpoint === 'search/suggestions' && method === 'GET') {
      const limit = request.nextUrl.searchParams.has('limit') ? Number(request.nextUrl.searchParams.get('limit')) : undefined
      result = await api.search.suggestions(request.nextUrl.searchParams.get('q') ?? '', { limit, lang })
    } else if (endpoint === 'products/lookup' && method === 'GET') {
      result = await api.products.lookupBySlug(request.nextUrl.searchParams.get('slug') ?? '', { lang })
    } else if (endpoint === 'products' && method === 'GET') {
      const params = request.nextUrl.searchParams
      const lang = params.get('lang')
      const optionalNumber = (key: string) => {
        const value = params.get(key)
        return value === null || value === '' ? undefined : Number(value)
      }
      const query: ProductListParams = {
        page: optionalNumber('page'),
        perPage: optionalNumber('per_page'),
        search: params.get('search') ?? undefined,
        category: params.get('category') ?? undefined,
        brand: params.get('brand') ?? undefined,
        tag: params.get('tag') ?? undefined,
        minPrice: optionalNumber('min_price'),
        maxPrice: optionalNumber('max_price'),
        stockStatus: (params.get('stock_status') ?? undefined) as ProductListParams['stockStatus'],
        onSale: params.has('on_sale') ? params.get('on_sale') === 'true' : undefined,
        featured: params.has('featured') ? params.get('featured') === 'true' : undefined,
        sort: (params.get('sort') ?? undefined) as ProductListParams['sort'],
        attributes: params.get('attributes') ?? undefined,
        lang: lang === 'ar' || lang === 'en' ? lang : undefined,
      }
      result = await api.products.list(query)
    } else if (endpoint.startsWith('products/') && method === 'GET') {
      const productId = decodeURIComponent(endpoint.slice('products/'.length))
      result = await api.products.get(productId as ProductId)
    } else if (endpoint === 'auth/register' && method === 'POST') {
      result = await api.auth.register(await request.json())
    } else if (endpoint === 'auth/login' && method === 'POST') {
      result = await api.auth.login(await request.json())
    } else if (endpoint === 'auth/session' && method === 'GET') {
      result = await api.auth.session()
    } else if (endpoint === 'auth/logout' && method === 'POST') {
      const session = await api.auth.session()
      if (request.headers.get('x-eldokan-csrf') !== session.data.csrf_token) {
        return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      }
      result = await api.auth.logout()
    } else if (endpoint === 'me' && method === 'GET') {
      result = await api.account.me()
    } else if (endpoint === 'me' && method === 'PATCH') {
      const session = await api.auth.session()
      if (request.headers.get('x-eldokan-csrf') !== session.data.csrf_token) {
        return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      }
      result = await api.account.update(await request.json())
    } else if (endpoint === 'wishlist' && method === 'GET') {
      const lang = request.nextUrl.searchParams.get('lang')
      result = await api.wishlist.get({ lang: lang === 'ar' || lang === 'en' ? lang : undefined })
    } else if (endpoint === 'wishlist/items' && method === 'POST') {
      const session = await api.auth.session()
      if (request.headers.get('x-eldokan-csrf') !== session.data.csrf_token) {
        return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      }
      const body = await request.json() as { product_id?: string }
      const lang = request.nextUrl.searchParams.get('lang')
      result = await api.wishlist.add(body.product_id as `prd_${number}`, { lang: lang === 'ar' || lang === 'en' ? lang : undefined })
    } else if (endpoint.startsWith('wishlist/items/') && method === 'DELETE') {
      const session = await api.auth.session()
      if (request.headers.get('x-eldokan-csrf') !== session.data.csrf_token) {
        return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      }
      const productId = decodeURIComponent(endpoint.slice('wishlist/items/'.length))
      const lang = request.nextUrl.searchParams.get('lang')
      result = await api.wishlist.remove(productId as `prd_${number}`, { lang: lang === 'ar' || lang === 'en' ? lang : undefined })
    } else if (endpoint === 'cart' && method === 'GET') {
      const lang = request.nextUrl.searchParams.get('lang')
      result = await api.cart.get({ lang: lang === 'ar' || lang === 'en' ? lang : undefined })
    } else if (endpoint === 'cart/items' && method === 'POST') {
      const body = await request.json() as { product_id?: string; variation_id?: string | null; quantity?: number }
      const lang = request.nextUrl.searchParams.get('lang')
      result = await api.cart.add({
        productId: body.product_id as ProductId,
        variationId: body.variation_id as VariationId | null | undefined,
        quantity: body.quantity as number,
      }, { lang: lang === 'ar' || lang === 'en' ? lang : undefined })
    } else if (endpoint.startsWith('cart/items/') && method === 'PATCH') {
      const body = await request.json() as { quantity?: number }
      const cartItemId = decodeURIComponent(endpoint.slice('cart/items/'.length))
      const lang = request.nextUrl.searchParams.get('lang')
      result = await api.cart.update(cartItemId as CartItemId, { quantity: body.quantity as number }, { lang: lang === 'ar' || lang === 'en' ? lang : undefined })
    } else if (endpoint.startsWith('cart/items/') && method === 'DELETE') {
      const cartItemId = decodeURIComponent(endpoint.slice('cart/items/'.length))
      const lang = request.nextUrl.searchParams.get('lang')
      result = await api.cart.remove(cartItemId as CartItemId, { lang: lang === 'ar' || lang === 'en' ? lang : undefined })
    } else if (endpoint === 'me/addresses' && method === 'GET') {
      result = await api.addresses.list()
    } else if (endpoint === 'me/addresses' && method === 'POST') {
      if (!(await verifyCsrf())) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      result = await api.addresses.create(await request.json() as AddressCreate)
    } else if (endpoint.startsWith('me/addresses/') && method === 'PATCH') {
      if (!(await verifyCsrf())) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      const addressId = decodeURIComponent(endpoint.slice('me/addresses/'.length))
      result = await api.addresses.update(addressId as AddressId, await request.json() as AddressUpdate)
    } else if (endpoint.startsWith('me/addresses/') && method === 'DELETE') {
      if (!(await verifyCsrf())) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      const addressId = decodeURIComponent(endpoint.slice('me/addresses/'.length))
      result = await api.addresses.remove(addressId as AddressId)
    } else if (endpoint === 'checkout' && method === 'GET') {
      result = await api.checkout.get({ lang })
    } else if (endpoint === 'checkout/quote' && method === 'POST') {
      if (!(await verifyCsrf())) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      result = await api.checkout.quote(await request.json() as QuoteInput, { lang })
    } else if (endpoint === 'checkout/attempts' && method === 'POST') {
      if (!(await verifyCsrf())) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      result = await api.checkout.createAttempt(await request.json() as PurchaseInput, { lang })
    } else if (endpoint === 'checkout/orders' && method === 'POST') {
      if (!(await verifyCsrf())) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      result = await api.checkout.placeOrder(await request.json(), { lang })
    } else if (endpoint === 'orders' && method === 'GET') {
      result = await api.orders.list({ page: Number(request.nextUrl.searchParams.get('page') ?? 1), perPage: Number(request.nextUrl.searchParams.get('per_page') ?? 20), lang })
    } else if (endpoint.startsWith('orders/') && endpoint.endsWith('/payment') && method === 'POST') {
      const orderId = decodeURIComponent(endpoint.slice('orders/'.length, -'/payment'.length)) as OrderId
      const credential = request.headers.get('x-eldokan-order-access')
      const guestCsrf = request.headers.get('x-eldokan-csrf')
      if (!(await verifyCsrf()) && !(credential && guestCsrf)) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'The session token is invalid. Refresh the session and try again.' }, meta: { request_id: null } }, { status: 403 })
      result = await api.orders.payment(orderId, await request.json() as PaymentInput, {
        lang,
        ...(credential && guestCsrf ? { guestAccess: { credential, csrfToken: guestCsrf } } : {}),
      })
    } else if (endpoint.startsWith('orders/') && method === 'GET') {
      const orderId = decodeURIComponent(endpoint.slice('orders/'.length)) as OrderId
      const credential = request.headers.get('x-eldokan-order-access')
      result = await api.orders.get(orderId, { lang, ...(credential ? { guestAccess: { credential, csrfToken: '' } } : {}) })
    } else {
      return Response.json({ success: false, error: { code: 'not_found', message: 'Customer API endpoint not found.' }, meta: { request_id: null } }, { status: 404 })
    }

    const headers = new Headers({ 'Cache-Control': 'no-store' })
    const created = endpoint === 'auth/register' || endpoint === 'me/addresses' && method === 'POST' || endpoint === 'checkout/attempts'
    return Response.json(result, { status: created ? 201 : 200, headers })
  } catch (error) {
    return errorResponse(error)
  }
}

async function proxy(request: NextRequest, context: RouteContext) {
  const cookies: string[] = []
  try {
    return appendCustomerApiCookies(await handleRequest(request, context, cookies), cookies)
  } catch (error) {
    return appendCustomerApiCookies(errorResponse(error), cookies)
  }
}

export const GET = proxy
export const POST = proxy
export const PATCH = proxy
export const DELETE = proxy
