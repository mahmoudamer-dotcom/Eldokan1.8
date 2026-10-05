import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type { CustomerSessionState } from '../session.js';
import type {
  CartAddInput,
  CartItemId,
  CartMutationResponse,
  CartQuantityInput,
  CartResponse,
  LanguageOptions,
  ProductId,
  VariationId,
} from '../types.js';

const PRODUCT_ID = /^prd_[1-9][0-9]*$/;
const VARIATION_ID = /^var_[1-9][0-9]*$/;
const CART_ITEM_ID = /^cit_[a-f0-9]{32}$/;

function quantityOrThrow(quantity: number): number {
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > 999) {
    throw new EldokanClientError({
      message: 'Quantity must be an integer between 1 and 999.',
      kind: 'validation',
      code: 'invalid_quantity',
    });
  }
  return quantity;
}

function publicIdOrThrow<T extends ProductId | VariationId | CartItemId>(
  value: T,
  pattern: RegExp,
  code: string,
): T {
  if (!pattern.test(value)) {
    throw new EldokanClientError({message: 'Invalid public ID.', kind: 'validation', code});
  }
  return value;
}

export class CartResource {
  private cartRequest: Promise<CartResponse> | null = null;

  constructor(
    private readonly http: EldokanHttpClient,
    private readonly sessionState: CustomerSessionState,
  ) {}

  get(options: LanguageOptions = {}): Promise<CartResponse> {
    if (this.cartRequest) return this.cartRequest;

    const request = this.http.get<CartResponse>('/cart', {
      lang: options.lang,
      credentials: 'include',
    }).then((response) => {
      this.sessionState.setCsrfToken(response.data.csrf_token);
      return response;
    });
    this.cartRequest = request;
    void request.finally(() => {
      if (this.cartRequest === request) this.cartRequest = null;
    }).catch(() => {});
    return request;
  }

  async add(input: CartAddInput, options: LanguageOptions = {}): Promise<CartMutationResponse> {
    const productId = publicIdOrThrow(input.productId, PRODUCT_ID, 'invalid_product_id');
    const variationId = input.variationId
      ? publicIdOrThrow(input.variationId, VARIATION_ID, 'invalid_variation_id')
      : null;
    const quantity = quantityOrThrow(input.quantity);
    const csrfToken = await this.ensureCsrf(options);
    return this.http.post<CartMutationResponse>('/cart/items', {
      body: {
        product_id: productId,
        ...(variationId ? { variation_id: variationId } : {}),
        quantity,
      },
      lang: options.lang,
      credentials: 'include',
      csrfToken,
    });
  }

  async update(
    itemId: CartItemId,
    input: CartQuantityInput,
    options: LanguageOptions = {},
  ): Promise<CartMutationResponse> {
    const id = publicIdOrThrow(itemId, CART_ITEM_ID, 'invalid_cart_item_id');
    const quantity = quantityOrThrow(input.quantity);
    const csrfToken = await this.ensureCsrf(options);
    return this.http.patch<CartMutationResponse>(`/cart/items/${encodeURIComponent(id)}`, {
      body: {quantity},
      lang: options.lang,
      credentials: 'include',
      csrfToken,
    });
  }

  async remove(itemId: CartItemId, options: LanguageOptions = {}): Promise<CartMutationResponse> {
    const id = publicIdOrThrow(itemId, CART_ITEM_ID, 'invalid_cart_item_id');
    const csrfToken = await this.ensureCsrf(options);
    return this.http.delete<CartMutationResponse>(`/cart/items/${encodeURIComponent(id)}`, {
      lang: options.lang,
      credentials: 'include',
      csrfToken,
    });
  }

  private async ensureCsrf(options: LanguageOptions): Promise<string> {
    if (!this.sessionState.getCsrfToken()) {
      await this.get(options);
    }
    return this.sessionState.requireCsrfToken();
  }
}
