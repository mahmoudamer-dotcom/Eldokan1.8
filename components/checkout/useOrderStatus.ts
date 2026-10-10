'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import type { OrderDetail } from '@eldokan/customer-api-client'
import { shouldWatchPayment } from '@/lib/order-status'

/** Read-only polling: payment confirmation always comes from the order API. */
export function useOrderStatus(read: (signal: AbortSignal) => Promise<OrderDetail>, paused = false) {
  const [order, setOrder] = useState<OrderDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [watching, setWatching] = useState(false)
  const [error, setError] = useState('')
  const sequence = useRef(0)
  const request = useRef<AbortController | null>(null)

  const refresh = useCallback(async () => {
    request.current?.abort()
    const controller = new AbortController()
    request.current = controller
    const current = ++sequence.current
    setRefreshing(true)
    try {
      const next = await read(controller.signal)
      if (!controller.signal.aborted && current === sequence.current) {
        setOrder(next)
        setError('')
      }
    } catch (cause) {
      if (!controller.signal.aborted && current === sequence.current) {
        setError(cause instanceof Error ? cause.message : 'Order status is unavailable.')
      }
    } finally {
      if (!controller.signal.aborted && current === sequence.current) {
        setLoading(false)
        setRefreshing(false)
        request.current = null
      }
    }
  }, [read])

  useEffect(() => {
    let active = true
    queueMicrotask(() => {
      if (!active) return
      setOrder(null)
      setLoading(true)
      void refresh()
    })
    return () => {
      active = false
      sequence.current += 1
      request.current?.abort()
    }
  }, [refresh])

  const pending = shouldWatchPayment(order)
  const orderId = order?.id
  useEffect(() => {
    let active = true
    let attempts = 0
    let timer: ReturnType<typeof setTimeout>
    const enabled = pending && !paused
    queueMicrotask(() => { if (active) setWatching(enabled) })
    if (enabled) {
      const tick = async () => {
        if (!active) return
        attempts += 1
        if (document.visibilityState === 'visible' && !request.current) await refresh()
        if (!active) return
        if (attempts < 20) timer = setTimeout(() => { void tick() }, 3000)
        else setWatching(false)
      }
      timer = setTimeout(() => { void tick() }, 3000)
    }
    return () => { active = false; clearTimeout(timer) }
  }, [pending, orderId, paused, refresh])

  return { order, loading, refreshing, watching, error, refresh }
}
