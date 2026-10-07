export type ProductReview = {
  id: string
  productId: string
  author: string
  rating: number
  title: string
  comment: string
  createdAt: string
}

const LIMIT = 50
export function reviewStorageKey(productId: string) {
  return `eldokan.product-reviews.v1:${encodeURIComponent(productId)}`
}

export function readProductReviews(productId: string): ProductReview[] {
  const saved: unknown = JSON.parse(localStorage.getItem(reviewStorageKey(productId)) ?? '[]')
  if (!Array.isArray(saved)) return []
  return saved.filter((value): value is ProductReview => {
    if (!value || typeof value !== 'object') return false
    const review = value as Partial<ProductReview>
    return review.productId === productId && typeof review.id === 'string'
      && typeof review.author === 'string' && review.author.length > 0 && review.author.length <= 60
      && typeof review.rating === 'number' && Number.isInteger(review.rating) && review.rating >= 1 && review.rating <= 5
      && typeof review.title === 'string' && review.title.length <= 100
      && typeof review.comment === 'string' && review.comment.length >= 10 && review.comment.length <= 2000
      && typeof review.createdAt === 'string' && Number.isFinite(Date.parse(review.createdAt))
  }).slice(0, LIMIT)
}

export function saveProductReviews(productId: string, reviews: ProductReview[]) {
  if (reviews.length > LIMIT) throw new Error('review_limit')
  localStorage.setItem(reviewStorageKey(productId), JSON.stringify(reviews))
}
