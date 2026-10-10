import { createEldokanCustomerApiClient, EldokanClientError } from '@eldokan/customer-api-client'
import type { AddressId, AddressCreate, AddressUpdate, CartItemId, ProductId, ProductListParams, VariationId, PurchaseInput, QuoteInput, OrderId, PaymentInput, SellerId, Language, ReviewId, ReviewInput, ReviewListParams } from '@eldokan/customer-api-client'
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
    console.error('[eldokan/customer-api]', { code: error.code, kind: error.kind, status: error.status, requestId: error.requestId })
    const status = error.status ?? (error.kind === 'network' || error.kind === 'timeout' ? 502 : error.kind === 'validation' ? 400 : 500)
    const message = error.kind === 'timeout'
      ? 'The store took too long to respond. Refresh shipping and payment to try again.'
      : error.kind === 'network'
        ? 'Could not connect to the store. Please try again shortly.'
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

  const transport = createCustomerApiTransport(request, (cookie) => cookies.push(cookie))
  const api = createEldokanCustomerApiClient({
    baseUrl: process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
    fetch: transport,
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
    const reviewRoute = /^(products|sellers)\/(prd_[1-9][0-9]*|sel_[1-9][0-9]*)\/reviews(?:\/(mine|rev_[1-9][0-9]*))?$/.exec(endpoint)
    const sellerProductsRoute = /^sellers\/(sel_[1-9][0-9]*)\/products$/.exec(endpoint)
    const returnRoute = /^orders\/(ord_[a-f0-9]{64})\/returns$/.exec(endpoint)
    const feedbackRoute = /^reviews\/(rev_[1-9][0-9]*)\/(feedback|images)$/.exec(endpoint)
    if (endpoint === 'auth/forgot-password' && method === 'POST') result = await api.commerce.forgotPassword((await request.json()).email)
    else if (endpoint === 'auth/reset-password' && method === 'POST') result = await api.commerce.resetPassword(await request.json())
    else if (endpoint === 'catalog/sitemap' && method === 'GET') result = await api.commerce.sitemap(Number(request.nextUrl.searchParams.get('page') ?? 1))
    else if (returnRoute || feedbackRoute || endpoint === 'me/decision' || endpoint.startsWith('me/product-alerts')) {
      if (isMutation && !await verifyCsrf()) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'Refresh your session and try again.' } }, { status: 403 })
      if (returnRoute && method === 'GET') result = await api.commerce.returns(returnRoute[1] as OrderId)
      else if (returnRoute && method === 'POST') result = await api.commerce.requestReturn(returnRoute[1] as OrderId, await request.json())
      else if (feedbackRoute && method === 'POST') {
        const input = await request.json()
        result = feedbackRoute[2] === 'images' ? await api.commerce.addReviewImage(feedbackRoute[1], input.image) : await api.commerce.feedback(feedbackRoute[1], input.action, input.reason)
      } else if (endpoint === 'me/decision' && method === 'GET') result = await api.commerce.decision()
      else if (endpoint === 'me/decision' && method === 'POST') result = await api.commerce.saveDecision(await request.json())
      else if (endpoint === 'me/product-alerts' && method === 'GET') result = await api.commerce.alerts()
      else if (endpoint === 'me/product-alerts' && method === 'POST') result = await api.commerce.saveAlert(await request.json())
      else if (/^me\/product-alerts\/prd_[1-9][0-9]*$/.test(endpoint) && method === 'DELETE') result = await api.commerce.removeAlert(endpoint.split('/')[2] as ProductId)
      else return Response.json({ success: false, error: { code: 'not_found', message: 'Route not found.' } }, { status: 404 })
    } else if (reviewRoute) {
      const target = reviewRoute[2] as ProductId | SellerId
      const reviewId = reviewRoute[3]
      if ((reviewRoute[1] === 'products') !== target.startsWith('prd_')) {
        return Response.json({ success: false, error: { code: 'invalid_review_target', message: 'Invalid review target.' } }, { status: 400 })
      }
      if (method === 'GET' && reviewId === 'mine') result = await api.reviews.mine(target)
      else if (method === 'GET' && !reviewId) result = await api.reviews.list(target, {
        page: request.nextUrl.searchParams.has('page') ? Number(request.nextUrl.searchParams.get('page')) : undefined,
        perPage: request.nextUrl.searchParams.has('per_page') ? Number(request.nextUrl.searchParams.get('per_page')) : undefined,
        rating: request.nextUrl.searchParams.has('rating') ? Number(request.nextUrl.searchParams.get('rating')) : undefined,
        sort: (request.nextUrl.searchParams.get('sort') ?? 'newest') as ReviewListParams['sort'], lang,
      })
      else if ((method === 'POST' && !reviewId) || ((method === 'PATCH' || method === 'DELETE') && reviewId?.startsWith('rev_'))) {
        const session = await api.auth.session()
        if (request.headers.get('x-eldokan-csrf') !== session.data.csrf_token) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'Refresh your session and try again.' } }, { status: 403 })
        if (method === 'DELETE') result = await api.reviews.remove(target, reviewId as ReviewId)
        else {
          const input = await request.json() as ReviewInput
          result = method === 'POST' ? await api.reviews.create(target, input) : await api.reviews.update(target, reviewId as ReviewId, input)
        }
      } else return Response.json({ success: false, error: { code: 'method_not_allowed', message: 'Method not allowed.' } }, { status: 405 })
    } else if (sellerProductsRoute && method === 'GET') {
      result = await api.sellers.products(sellerProductsRoute[1] as SellerId, {
        page: request.nextUrl.searchParams.has('page') ? Number(request.nextUrl.searchParams.get('page')) : undefined,
        perPage: request.nextUrl.searchParams.has('per_page') ? Number(request.nextUrl.searchParams.get('per_page')) : undefined,
        sort: (request.nextUrl.searchParams.get('sort') ?? 'newest') as ProductListParams['sort'], lang,
      })
    } else if (endpoint === 'health' && method === 'GET') {
      result = await api.health.get()
    } else if (endpoint === 'home' && method === 'GET') {
      result = await api.home.get({ lang })
    } else if (endpoint === 'stories' && method === 'GET') {
      result = await api.stories.list({ lang })
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
    } else if (/^orders\/ord_[a-f0-9]{64}\/cancel$/.test(endpoint) && method === 'POST') {
      if (!(await verifyCsrf())) return Response.json({ success: false, error: { code: 'invalid_csrf', message: 'Refresh your session and try again.' }, meta: { request_id: null } }, { status: 403 })
      const input = await request.json() as { reason_code?: unknown; details?: unknown }
      if (!input || typeof input !== 'object' || Array.isArray(input) || Object.keys(input).some((key) => !['reason_code', 'details'].includes(key)) || typeof input.reason_code !== 'string' || !['changed_mind', 'ordered_by_mistake', 'delivery_time', 'found_better_price', 'other'].includes(input.reason_code) || (input.details !== undefined && (typeof input.details !== 'string' || [...input.details].length > 500))) return Response.json({ success: false, error: { code: 'cancellation_reason_invalid', message: 'Choose a cancellation reason and use at most 500 characters.' }, meta: { request_id: null } }, { status: 422 })
      const base = process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL
      const upstream = await transport(`${base.replace(/\/$/, '')}/${endpoint}${lang ? `?lang=${lang}` : ''}`, {
        // Like the SDK's other server requests, do not forward the browser Origin
        // to WordPress. Browser origin and CSRF were verified above; native order
        // ownership and CSRF are verified again by the Customer API.
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-ElDokan-CSRF': request.headers.get('x-eldokan-csrf')! },
        body: JSON.stringify(input), signal: AbortSignal.timeout(15000),
      })
      return Response.json(await upstream.json(), { status: upstream.status, headers: { 'Cache-Control': 'private, no-store' } })
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
