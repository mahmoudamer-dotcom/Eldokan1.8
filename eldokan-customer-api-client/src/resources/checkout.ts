import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { CartResponse, CheckoutResponse, LanguageOptions, PlacementInput, PlacementResponse, PurchaseInput, CheckoutAttemptResponse, QuoteInput } from '../types.js';

export class CheckoutResource {
  constructor(private readonly http: EldokanHttpClient, private readonly sessionState: CustomerSessionState) {}

  async get(options: LanguageOptions = {}): Promise<CheckoutResponse> {
    const result = await this.http.get<CheckoutResponse>('/checkout', { lang: options.lang, credentials: 'include' });
    this.sessionState.setCsrfToken(result.data.cart.csrf_token);
    return result;
  }

  async quote(input: QuoteInput, options: LanguageOptions = {}): Promise<CheckoutResponse> {
    const csrfToken = await this.ensureCsrf(options);
    return this.http.post('/checkout/quote', { body: input, lang: options.lang, credentials: 'include', csrfToken });
  }

  async createAttempt(input: PurchaseInput, options: LanguageOptions = {}): Promise<CheckoutAttemptResponse> {
    const csrfToken = await this.ensureCsrf(options);
    return this.http.post('/checkout/attempts', { body: input, lang: options.lang, credentials: 'include', csrfToken });
  }

  async placeOrder(input: PlacementInput, options: LanguageOptions = {}): Promise<PlacementResponse> {
    const csrfToken = await this.ensureCsrf(options);
    return this.http.post('/checkout/orders', { body: input, lang: options.lang, credentials: 'include', csrfToken });
  }

  private async ensureCsrf(options: LanguageOptions): Promise<string> {
    if (!this.sessionState.getCsrfToken()) {
      const response = await this.http.get<CartResponse>('/cart', { lang: options.lang, credentials: 'include' });
      this.sessionState.setCsrfToken(response.data.csrf_token);
    }
    return this.sessionState.requireCsrfToken();
  }
}
