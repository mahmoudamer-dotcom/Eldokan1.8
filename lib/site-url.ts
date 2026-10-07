export function getStorefrontUrl(): URL | undefined {
  const value = process.env.STOREFRONT_SITE_URL?.trim()
  if (!value) return undefined
  try {
    const url = new URL(value)
    if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return undefined
    return new URL(url.origin)
  } catch { return undefined }
}
