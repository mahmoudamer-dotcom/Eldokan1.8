export * from './generated/types.js';
export type Language = 'ar' | 'en';
export type StockStatus = 'in_stock' | 'out_of_stock' | 'on_backorder';
export type ProductSort = 'newest' | 'price_asc' | 'price_desc' | 'best_selling' | 'rating' | 'relevance';
export type ProductId = `prd_${number}`;
export type CustomerId = `cus_${string}`;
export type CategoryId = `cat_${number}`;
export type SellerId = `sel_${number}`;
export type BrandId = `brd_${number}`;
export type TagId = `tag_${number}`;
export type VariationId = `var_${number}`;
export type CartItemId = `cit_${string}`;
export type AttributeId = `att_${number}`;
export type AttributeOptionId = `atr_${number}`;
export interface LanguageOptions {
    lang?: Language;
}
export interface AttributeFilterGroup {
    attributeId: AttributeId;
    optionIds: readonly AttributeOptionId[];
}
export interface ProductListParams extends LanguageOptions {
    page?: number;
    perPage?: number;
    search?: string;
    /** Category slug, not cat_* ID. */
    category?: string;
    /** Brand slug, not brd_* ID. */
    brand?: string;
    /** Product tag slug, not tag_* ID. */
    tag?: string;
    /** Major currency units, e.g. 600.00 EGP. */
    minPrice?: number;
    /** Major currency units, e.g. 600.00 EGP. */
    maxPrice?: number;
    stockStatus?: StockStatus;
    onSale?: boolean;
    featured?: boolean;
    sort?: ProductSort;
    /** Structured form preferred. Raw string is supported only for controlled compatibility. */
    attributes?: readonly AttributeFilterGroup[] | string;
}
export interface CategoryListParams extends LanguageOptions {
    parent?: string;
}
export interface CatalogTermListParams extends LanguageOptions {
    page?: number;
    perPage?: number;
    search?: string;
}
export interface SearchSuggestionParams extends LanguageOptions {
    limit?: number;
}
export interface CartAddInput {
    productId: ProductId;
    variationId?: VariationId | null;
    quantity: number;
}
export interface CartQuantityInput {
    quantity: number;
}
export interface EldokanResponseContext {
    url: string;
    status: number;
    requestId: string | null;
    apiVersion: string | null;
    contentLanguage: string | null;
    cacheStatus: string | null;
    cacheTtl: string | null;
    cacheGeneration: string | null;
    cacheKey: string | null;
    cacheBackend: string | null;
    cacheStore: string | null;
    serverTiming: string | null;
}
//# sourceMappingURL=types.d.ts.map