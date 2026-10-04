import { EldokanClientError } from '@eldokan/customer-api-client'

const RETRYABLE_STATUS = new Set([408, 425, 429])

function canRetry(error: unknown) {
  if (!(error instanceof EldokanClientError)) return false
  return error.kind === 'network' || error.kind === 'timeout' ||
    (error.kind === 'api' && (RETRYABLE_STATUS.has(error.status ?? 0) || (error.status ?? 0) >= 500))
}

export async function retryApiRead<T>(read: () => Promise<T>, retries = 2): Promise<T> {
  for (let attempt = 0; ; attempt += 1) {
    try {
      return await read()
    } catch (error) {
      if (attempt >= retries || !canRetry(error)) throw error
      await new Promise((resolve) => setTimeout(resolve, 200 * (attempt + 1)))
    }
  }
}
