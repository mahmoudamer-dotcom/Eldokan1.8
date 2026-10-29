export type ToastTone = 'success' | 'error' | 'info'
export type ToastDetail = { message: string; tone: ToastTone }

export const TOAST_EVENT = 'eldokan:toast'

/** Shows a short message at the top of the screen; any client component can call it. */
export function toast(message: string, tone: ToastTone = 'success') {
  if (typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent<ToastDetail>(TOAST_EVENT, { detail: { message, tone } }))
}
