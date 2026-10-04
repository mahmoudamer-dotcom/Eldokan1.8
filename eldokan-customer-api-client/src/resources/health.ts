import type { EldokanHttpClient } from '../client.js';
import type { HealthResponse } from '../types.js';

export class HealthResource {
  constructor(private readonly http: EldokanHttpClient) {}
  get(): Promise<HealthResponse> {
    return this.http.get<HealthResponse>('/health', { includeLanguage: false });
  }
}
