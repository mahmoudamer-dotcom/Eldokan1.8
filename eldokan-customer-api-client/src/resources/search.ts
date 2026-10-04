import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type { SearchSuggestionParams, SuggestionListResponse } from '../types.js';

export class SearchResource {
  constructor(private readonly http: EldokanHttpClient) {}

  suggestions(q: string, params: SearchSuggestionParams = {}): Promise<SuggestionListResponse> {
    const clean = q.trim();
    if ([...clean].length < 2) {
      throw new EldokanClientError({
        message: 'Search query must be at least 2 characters.',
        kind: 'validation',
        code: 'search_too_short',
      });
    }
    if (params.limit !== undefined && (!Number.isInteger(params.limit) || params.limit < 1 || params.limit > 20)) {
      throw new EldokanClientError({
        message: 'Search suggestion limit must be between 1 and 20.',
        kind: 'validation',
        code: 'invalid_suggestion_limit',
      });
    }

    return this.http.get<SuggestionListResponse>('/search/suggestions', {
      query: { q: clean, limit: params.limit },
      lang: params.lang,
    });
  }
}
