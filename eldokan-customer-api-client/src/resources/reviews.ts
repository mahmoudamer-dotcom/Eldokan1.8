import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type { CustomerSessionState } from '../session.js';
import type { ReviewInput, ReviewListParams, ReviewListResponse, ReviewMineResponse, ReviewResponse, ReviewId, ProductId, SellerId } from '../types.js';

export class ReviewsResource {
  constructor(private readonly http: EldokanHttpClient, private readonly session: CustomerSessionState) {}
  private path(target: ProductId | SellerId): string {
    if (!/^(prd|sel)_[1-9][0-9]*$/.test(target)) throw new EldokanClientError({ message: 'Invalid review target.', kind: 'validation', code: 'invalid_review_target' });
    return `/${target.startsWith('prd_') ? 'products' : 'sellers'}/${encodeURIComponent(target)}/reviews`;
  }
  private id(id: ReviewId): string {
    if (!/^rev_[1-9][0-9]*$/.test(id)) throw new EldokanClientError({ message: 'Invalid review ID.', kind: 'validation', code: 'invalid_review_id' });
    return encodeURIComponent(id);
  }
  private input(input: ReviewInput): ReviewInput {
    if (!Number.isInteger(input.rating) || input.rating < 1 || input.rating > 5 || typeof input.comment !== 'string' || !input.comment.trim() || [...input.comment.trim()].length > 2000 || (input.title !== undefined && [...input.title].length > 100)) {
      throw new EldokanClientError({ message: 'Choose a rating and enter a non-empty review of at most 2000 characters.', kind: 'validation', code: 'invalid_review' });
    }
    return input;
  }
  list(target: ProductId | SellerId, params: ReviewListParams = {}): Promise<ReviewListResponse> {
    if ((params.page !== undefined && (!Number.isInteger(params.page) || params.page < 1 || params.page > 10000)) || (params.perPage !== undefined && (!Number.isInteger(params.perPage) || params.perPage < 1 || params.perPage > 30)) || (params.rating !== undefined && (!Number.isInteger(params.rating) || params.rating < 0 || params.rating > 5)) || (params.sort !== undefined && !['newest','highest','lowest'].includes(params.sort))) {
      throw new EldokanClientError({ message: 'Invalid review filters.', kind: 'validation', code: 'invalid_review_query' });
    }
    return this.http.get(this.path(target), { credentials: 'include', query: { page: params.page, per_page: params.perPage, rating: params.rating, sort: params.sort }, lang: params.lang });
  }
  mine(target: ProductId | SellerId): Promise<ReviewMineResponse> {
    return this.http.get(`${this.path(target)}/mine`, { credentials: 'include', includeLanguage: false });
  }
  create(target: ProductId | SellerId, input: ReviewInput): Promise<ReviewResponse> {
    return this.http.post(this.path(target), { body: this.input(input), credentials: 'include', csrfToken: this.session.requireCsrfToken(), includeLanguage: false });
  }
  update(target: ProductId | SellerId, id: ReviewId, input: ReviewInput): Promise<ReviewResponse> {
    return this.http.patch(`${this.path(target)}/${this.id(id)}`, { body: this.input(input), credentials: 'include', csrfToken: this.session.requireCsrfToken(), includeLanguage: false });
  }
  remove(target: ProductId | SellerId, id: ReviewId): Promise<{ success: true; data: { deleted: true }; meta: { request_id: string } }> {
    return this.http.delete(`${this.path(target)}/${this.id(id)}`, { credentials: 'include', csrfToken: this.session.requireCsrfToken(), includeLanguage: false });
  }
}
