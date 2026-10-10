import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type { LanguageOptions, SellerId, SellerProfileResponse, ProductListParams, ProductListResponse } from '../types.js';

const SELLER_ID = /^sel_[1-9][0-9]*$/;

function idOrThrow(sellerId: SellerId): SellerId {
  if (!SELLER_ID.test(sellerId)) {
    throw new EldokanClientError({
      message: 'Invalid seller ID.',
      kind: 'validation',
      code: 'invalid_seller_id',
    });
  }
  return sellerId;
}

export class SellersResource {
  constructor(private readonly http: EldokanHttpClient) {}

  get(sellerId: SellerId, options: LanguageOptions = {}): Promise<SellerProfileResponse> {
    return this.http.get<SellerProfileResponse>(
      `/sellers/${encodeURIComponent(idOrThrow(sellerId))}`,
      { lang: options.lang },
    );
  }

  products(sellerId: SellerId, params: Pick<ProductListParams, 'page' | 'perPage' | 'sort' | 'lang'> = {}): Promise<ProductListResponse> {
    return this.http.get(`/sellers/${encodeURIComponent(idOrThrow(sellerId))}/products`, { query: { page: params.page, per_page: params.perPage, sort: params.sort }, lang: params.lang });
  }
}
