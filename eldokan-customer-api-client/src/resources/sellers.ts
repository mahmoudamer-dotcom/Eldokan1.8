import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type { LanguageOptions, SellerId, SellerPublicResponse } from '../types.js';

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

  get(sellerId: SellerId, options: LanguageOptions = {}): Promise<SellerPublicResponse> {
    return this.http.get<SellerPublicResponse>(
      `/sellers/${encodeURIComponent(idOrThrow(sellerId))}`,
      { lang: options.lang },
    );
  }
}
