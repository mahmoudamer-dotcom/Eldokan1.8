import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type { CustomerSessionState } from '../session.js';
import type {
  LanguageOptions,
  ProductId,
  WishlistMutationResponse,
  WishlistResponse,
} from '../types.js';

const PRODUCT_ID = /^prd_[1-9][0-9]*$/;

function productIdOrThrow(productId: ProductId): ProductId {
  if (!PRODUCT_ID.test(productId)) {
    throw new EldokanClientError({
      message: 'Invalid product ID.',
      kind: 'validation',
      code: 'invalid_product_id',
    });
  }
  return productId;
}

export class WishlistResource {
  constructor(
    private readonly http: EldokanHttpClient,
    private readonly sessionState: CustomerSessionState,
  ) {}

  get(options: LanguageOptions = {}): Promise<WishlistResponse> {
    return this.http.get<WishlistResponse>('/wishlist', {
      lang: options.lang,
      credentials: 'include',
    });
  }

  add(productId: ProductId, options: LanguageOptions = {}): Promise<WishlistMutationResponse> {
    return this.http.post<WishlistMutationResponse>('/wishlist/items', {
      body: { product_id: productIdOrThrow(productId) },
      lang: options.lang,
      credentials: 'include',
      csrfToken: this.sessionState.requireCsrfToken(),
    });
  }

  remove(productId: ProductId, options: LanguageOptions = {}): Promise<WishlistMutationResponse> {
    return this.http.delete<WishlistMutationResponse>(
      `/wishlist/items/${encodeURIComponent(productIdOrThrow(productId))}`,
      {
        lang: options.lang,
        credentials: 'include',
        csrfToken: this.sessionState.requireCsrfToken(),
      },
    );
  }
}
