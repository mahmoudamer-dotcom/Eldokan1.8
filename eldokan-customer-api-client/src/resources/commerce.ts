import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { ProductId } from '../types.js';
import type { OrderId } from './orders.js';

export type CommerceResponse<T> = { success: true; data: T; meta: { request_id: string } };
export type ReturnRequest = { id: string; state: 'requested' | 'approved' | 'received' | 'closed' | 'rejected'; reason: string; details: string; staff_note?: string; lines: { item_id: number; quantity: number }[]; created_at: string; updated_at: string };
export type ReturnSummary = { items: { item_id: number; name: string; quantity: number }[]; available: boolean; request: ReturnRequest | null; refunded_amount: string; currency: string };
export type ProductAlert = { product_id: ProductId; name: string; kind: 'stock' | 'price'; target_price: number | null; notified_at: string | null };
export type DecisionProfile = { category: string; budget: string; keywords: string; priority: string; compare_ids: string[] };
export type ReviewFeedback = { helpful_count: number; images: { url: string; alt: string }[] };
export type SitemapPage = { categories: { slug: string }[]; items: { id: ProductId; updated_at: string | null }[]; total_pages: number };

/** Customer commerce extensions. Provider secrets and shipping APIs stay on the server. */
export class CommerceResource {
  constructor(private readonly http: EldokanHttpClient, private readonly session: CustomerSessionState) {}
  private write<T>(path: string, body: unknown): Promise<CommerceResponse<T>> {
    return this.http.post(path, { body, credentials: 'include', csrfToken: this.session.requireCsrfToken(), includeLanguage: false });
  }
  forgotPassword(email: string): Promise<CommerceResponse<{ accepted: true }>> {
    return this.http.post('/auth/forgot-password', { body: { email }, credentials: 'include', includeLanguage: false });
  }
  resetPassword(input: { login: string; key: string; password: string }): Promise<CommerceResponse<{ reset: true }>> {
    return this.http.post('/auth/reset-password', { body: input, credentials: 'include', includeLanguage: false });
  }
  returns(id: OrderId): Promise<CommerceResponse<ReturnSummary>> {
    return this.http.get(`/orders/${encodeURIComponent(id)}/returns`, { credentials: 'include', includeLanguage: false });
  }
  requestReturn(id: OrderId, input: { reason: string; details: string; lines: { item_id: number; quantity: number }[] }): Promise<CommerceResponse<ReturnSummary>> {
    return this.write(`/orders/${encodeURIComponent(id)}/returns`, input);
  }
  alerts(): Promise<CommerceResponse<{ items: ProductAlert[] }>> {
    return this.http.get('/me/product-alerts', { credentials: 'include', includeLanguage: false });
  }
  saveAlert(input: { product_id: ProductId; kind: 'stock' | 'price'; target_price?: number }): Promise<CommerceResponse<{ items: ProductAlert[] }>> {
    return this.write('/me/product-alerts', input);
  }
  removeAlert(productId: ProductId): Promise<CommerceResponse<{ items: ProductAlert[] }>> {
    return this.http.delete(`/me/product-alerts/${encodeURIComponent(productId)}`, { credentials: 'include', csrfToken: this.session.requireCsrfToken(), includeLanguage: false });
  }
  decision(): Promise<CommerceResponse<DecisionProfile>> {
    return this.http.get('/me/decision', { credentials: 'include', includeLanguage: false });
  }
  saveDecision(input: DecisionProfile): Promise<CommerceResponse<DecisionProfile>> { return this.write('/me/decision', input); }
  feedback(reviewId: string, action: 'helpful' | 'report', reason = ''): Promise<CommerceResponse<{ accepted: true; helpful_count: number }>> {
    return this.write(`/reviews/${encodeURIComponent(reviewId)}/feedback`, { action, reason });
  }
  addReviewImage(reviewId: string, image: string): Promise<CommerceResponse<ReviewFeedback>> {
    return this.write(`/reviews/${encodeURIComponent(reviewId)}/images`, { image });
  }
  sitemap(page = 1): Promise<CommerceResponse<SitemapPage>> { return this.http.get('/catalog/sitemap', { query: { page }, includeLanguage: false }); }
}
