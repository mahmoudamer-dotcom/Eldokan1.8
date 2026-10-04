import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type {
  AuthSessionResponse,
  LoginRequest,
  LogoutResponse,
  RegisterRequest,
} from '../types.js';

export class AuthResource {
  constructor(
    private readonly http: EldokanHttpClient,
    private readonly sessionState: CustomerSessionState,
  ) {}

  async register(input: RegisterRequest): Promise<AuthSessionResponse> {
    const response = await this.http.post<AuthSessionResponse>('/auth/register', {
      body: input,
      credentials: 'include',
      includeLanguage: false,
    });
    this.sessionState.setCsrfToken(response.data.csrf_token);
    return response;
  }

  async login(input: LoginRequest): Promise<AuthSessionResponse> {
    const response = await this.http.post<AuthSessionResponse>('/auth/login', {
      body: input,
      credentials: 'include',
      includeLanguage: false,
    });
    this.sessionState.setCsrfToken(response.data.csrf_token);
    return response;
  }

  async session(): Promise<AuthSessionResponse> {
    const response = await this.http.get<AuthSessionResponse>('/auth/session', {
      credentials: 'include',
      includeLanguage: false,
    });
    this.sessionState.setCsrfToken(response.data.csrf_token);
    return response;
  }

  async logout(): Promise<LogoutResponse> {
    const response = await this.http.post<LogoutResponse>('/auth/logout', {
      credentials: 'include',
      csrfToken: this.sessionState.requireCsrfToken(),
      includeLanguage: false,
    });
    this.sessionState.clear();
    return response;
  }
}
