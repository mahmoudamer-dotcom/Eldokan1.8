import { EldokanClientError } from './errors.js';

export class CustomerSessionState {
  private csrfToken: string | null = null;

  setCsrfToken(value: string): void {
    this.csrfToken = value;
  }

  clear(): void {
    this.csrfToken = null;
  }

  getCsrfToken(): string | null {
    return this.csrfToken;
  }

  requireCsrfToken(): string {
    if (!this.csrfToken) {
      throw new EldokanClientError({
        message: 'Restore or create a customer session before this mutation.',
        kind: 'validation',
        code: 'missing_csrf_token',
      });
    }
    return this.csrfToken;
  }
}
