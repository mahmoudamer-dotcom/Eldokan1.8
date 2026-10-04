import type { EldokanHttpClient } from '../client.js';
import type { HomeResponse, LanguageOptions } from '../types.js';

export class HomeResource {
  constructor(private readonly http: EldokanHttpClient) {}
  get(options: LanguageOptions = {}): Promise<HomeResponse> {
    return this.http.get<HomeResponse>('/home', { lang: options.lang });
  }
}
