import { createEldokanCustomerApiClient, EldokanClientError } from '@eldokan/customer-api-client'
import type { NextRequest } from 'next/server'

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
    const status = error.status ?? (error.kind === 'network' || error.kind === 'timeout' ? 502 : 500)
    const message = error.kind === 'network' || error.kind === 'timeout'
      ? 'The storefront server could not reach the Eldokan customer API. Check ELDOKAN_API_BASE_URL and server connectivity.'
      : error.message
    return Response.json({
      success: false,
      error: { code: error.code, message },
      meta: { request_id: error.requestId },
    }, { status, headers: { 'Cache-Control': 'no-store' } })
  }

  return Response.json({
    success: false,
    error: { code: 'customer_api_error', message: 'Unable to complete the customer API request.' },
    meta: { request_id: null },
  }, { status: 500, headers: { 'Cache-Control': 'no-store' } })
}

async function proxy(request: NextRequest, context: RouteContext) {
  const { path } = await context.params
  const endpoint = path.join('/')
  const method = request.method
  const isMutation = method === 'POST' || method === 'PATCH' || method === 'DELETE'

  if (isMutation && request.headers.get('origin') !== new URL(request.url).origin) {
    return Response.json({ success: false, error: { code: 'invalid_origin', message: 'Request origin is not allowed.' }, meta: { request_id: null } }, { status: 403 })
  }

  const cookies: string[] = []
  const api = createEldokanCustomerApiClient({
    baseUrl: process.env.ELDOKAN_API_BASE_URL?.trim() || DEFAULT_API_BASE_URL,
    fetch: async (input, init) => {
      const headers = new Headers(init?.headers)
      const cookie = request.headers.get('cookie')
      if (cookie) headers.set('cookie', cookie)

      const response = await fetch(input, { ...init, headers, cache: 'no-store' })
      cookies.push(...response.headers.getSetCookie())
      return response
    },
  })

  try {
    let result: unknown
    if (endpoint === 'auth/register' && method === 'POST') {
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
    } else {
      return Response.json({ success: false, error: { code: 'not_found', message: 'Customer API endpoint not found.' }, meta: { request_id: null } }, { status: 404 })
    }

    const headers = new Headers({ 'Cache-Control': 'no-store' })
    for (const cookie of cookies) {
      const localCookie = cookie
        .replace(/;\s*domain=[^;]*/i, '')
        .replace(/;\s*path=[^;]*/i, '')
      headers.append('set-cookie', `${localCookie}; Path=/`)
    }
    return Response.json(result, { status: endpoint === 'auth/register' ? 201 : 200, headers })
  } catch (error) {
    return errorResponse(error)
  }
}

export const GET = proxy
export const POST = proxy
export const PATCH = proxy
export const DELETE = proxy
