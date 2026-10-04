import type { EldokanHttpClient } from '../client.js';
import { EldokanClientError } from '../errors.js';
import type {
  CategoryDetailResponse,
  CategoryFiltersResponse,
  CategoryListParams,
  CategoryListResponse,
  LanguageOptions,
} from '../types.js';

function slugOrThrow(slug: string): string {
  const clean = slug.trim();
  if (!clean) {
    throw new EldokanClientError({
      message: 'Category slug is required.',
      kind: 'validation',
      code: 'missing_category_slug',
    });
  }
  return clean;
}

export class CategoriesResource {
  constructor(private readonly http: EldokanHttpClient) {}

  list(params: CategoryListParams = {}): Promise<CategoryListResponse> {
    return this.http.get<CategoryListResponse>('/categories', {
      query: { parent: params.parent },
      lang: params.lang,
    });
  }

  get(slug: string, options: LanguageOptions = {}): Promise<CategoryDetailResponse> {
    return this.http.get<CategoryDetailResponse>(
      `/categories/${encodeURIComponent(slugOrThrow(slug))}`,
      { lang: options.lang },
    );
  }

  filters(slug: string, options: LanguageOptions = {}): Promise<CategoryFiltersResponse> {
    return this.http.get<CategoryFiltersResponse>(
      `/categories/${encodeURIComponent(slugOrThrow(slug))}/filters`,
      { lang: options.lang },
    );
  }
}
