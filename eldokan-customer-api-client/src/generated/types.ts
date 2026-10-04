/* eslint-disable */
/**
 * AUTO-GENERATED from openapi/eldokan-customer-api-v1.openapi.json.
 * Contract: ElDokan Customer API v1.
 * Do not edit manually. Run: python scripts/generate-types.py
 */

export interface Money {
  /**
   * Minor units; 60000 = 600.00 EGP
   */
  amount: number;
  currency: string;
  decimals: number;
  formatted: string;
}

export interface Image {
  url: string;
  alt: string;
}

export interface Brand {
  id: `brd_${number}`;
  name: string;
  slug: string;
  /**
   * Normalized existing Brand taxonomy Thumbnail; null when no valid thumbnail is managed.
   */
  image: Image | null;
}

export interface Tag {
  id: `tag_${number}`;
  name: string;
  slug: string;
}

export interface Stock {
  status: "in_stock" | "out_of_stock" | "on_backorder";
  quantity: number | null;
  backorders_allowed: boolean;
}

export interface SellerPublic {
  id: `sel_${number}`;
  name: string;
  slug: string;
  /**
   * Customer-facing seller rating; null until the Phase 2 rating system is implemented.
   */
  rating: number | null;
  /**
   * Customer-facing seller rating count; null until the Phase 2 rating system is implemented.
   */
  rating_count: number | null;
}

export interface Pricing {
  price: Money | null;
  min_price: Money | null;
  max_price: Money | null;
  regular_price: Money | null;
  sale_price: Money | null;
  on_sale: boolean;
  discount_percent: number;
}

export interface ProductCard {
  id: `prd_${number}`;
  slug: string;
  name: string;
  type: "simple" | "variable" | "grouped" | "external" | "other";
  brand: Brand | null;
  pricing: Pricing;
  stock: Stock;
  image: Image | null;
  average_rating: number;
  rating_count: number;
  seller: SellerPublic | null;
}

export interface CategorySummary {
  id: `cat_${number}`;
  name: string;
  slug: string;
}

export interface Category {
  id: `cat_${number}`;
  name: string;
  slug: string;
  description: string;
  count: number;
  parent_id: `cat_${number}` | null;
  image: Image | null;
}

export interface CategoryDetail {
  id: `cat_${number}`;
  name: string;
  slug: string;
  description: string;
  count: number;
  parent_id: `cat_${number}` | null;
  image: Image | null;
  children: Array<Category>;
}

export interface AttributeOption {
  id: `atr_${number}` | null;
  name: string;
  slug: string;
}

export interface Attribute {
  id: `att_${number}` | null;
  name: string;
  slug: string;
  visible: boolean;
  variation: boolean;
  options: Array<AttributeOption>;
}

export interface FilterAttribute {
  id: `att_${number}`;
  name: string;
  slug: string;
  options: Array<AttributeOption>;
}

export interface CategoryFilters {
  category_id: `cat_${number}`;
  source: {
    mode: "unavailable" | "none" | "inferred_from_products" | "configured" | "inherited";
    category_id: `cat_${number}` | null;
  };
  attributes: Array<FilterAttribute>;
}

export interface VariationSelection {
  attribute_id: `att_${number}` | null;
  attribute_slug: string;
  option_id: `atr_${number}` | null;
  option_slug: string | null;
}

export interface Variation {
  id: `var_${number}`;
  attributes: Array<VariationSelection>;
  purchasable: boolean;
  pricing: Pricing;
  stock: Stock;
  image: Image | null;
}

export interface ProductDetail {
  id: `prd_${number}`;
  slug: string;
  name: string;
  type: "simple" | "variable" | "grouped" | "external" | "other";
  brand: Brand | null;
  pricing: Pricing;
  stock: Stock;
  image: Image | null;
  average_rating: number;
  rating_count: number;
  seller: SellerPublic | null;
  sku: string | null;
  categories: Array<CategorySummary>;
  delivery: {
    label: string | null;
    min_days: number | null;
    max_days: number | null;
  };
  warranty: {
    label: string | null;
    duration: number | null;
    unit: string | null;
    type: string | null;
  };
  images: Array<Image>;
  attributes: Array<Attribute>;
  variations: Array<Variation>;
  short_description_html: string;
  description_html: string;
}

export type HomeSection = HeroSliderSection | BannerGridSection | CategoryGridSection | ProductCarouselSection | BrandGridSection;

export interface HeroSliderSection {
  id: "hero_slider";
  type: "hero_slider";
  enabled: boolean;
  order: number;
  title: string;
  items: Array<HeroSlide>;
}

export interface BannerGridSection {
  id: "banner_grid";
  type: "banner_grid";
  enabled: boolean;
  order: number;
  title: string;
  items: Array<PromoBanner>;
}

export interface CategoryGridSection {
  id: "categories";
  type: "category_grid";
  enabled: boolean;
  order: number;
  title: string;
  items: Array<Category>;
}

export interface ProductCarouselSection {
  id: "featured_deals" | "best_sellers" | "new_arrivals";
  type: "product_carousel";
  enabled: boolean;
  order: number;
  title: string;
  items: Array<ProductCard>;
}

export interface BrandGridSection {
  id: "brands";
  type: "brand_grid";
  enabled: boolean;
  order: number;
  title: string;
  items: Array<Brand>;
}

export interface HomeCta {
  text: string;
  type: "url" | "product" | "category" | "search";
  value: string;
}

export interface HeroSlide {
  id: `hsl_${number}`;
  kind: "hero";
  title: string;
  subtitle: string;
  desktop_image: Image | null;
  mobile_image: Image | null;
  cta: HomeCta | null;
}

export interface PromoBanner {
  id: `hbn_${number}`;
  kind: "banner";
  title: string;
  subtitle: string;
  desktop_image: Image | null;
  mobile_image: Image | null;
  cta: HomeCta | null;
}

export interface Home {
  sections: Array<HomeSection>;
}

export interface Suggestion {
  id: `prd_${number}`;
  name: string;
  slug: string;
  price: Money | null;
  image: Image | null;
}

export interface Health {
  service: string;
  version: string;
  status: "ok" | "degraded";
  ready: boolean;
}

export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface Meta {
  request_id: `req_${string}`;
}

export interface Error {
  code: string;
  message: string;
}

export interface ErrorEnvelope {
  success: false;
  error: Error;
  meta: Meta;
}

export interface HealthResponse {
  success: true;
  data: Health;
  meta: {
    request_id: `req_${string}`;
  };
}

export interface HomeResponse {
  success: true;
  data: Home;
  meta: {
    request_id: `req_${string}`;
  };
}

export interface ProductListResponse {
  success: true;
  data: Array<ProductCard>;
  meta: {
    request_id: `req_${string}`;
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

export interface ProductDetailResponse {
  success: true;
  data: ProductDetail;
  meta: {
    request_id: `req_${string}`;
  };
}

export interface CategoryListResponse {
  success: true;
  data: Array<Category>;
  meta: {
    request_id: `req_${string}`;
  };
}

export interface CategoryDetailResponse {
  success: true;
  data: CategoryDetail;
  meta: {
    request_id: `req_${string}`;
  };
}

export interface CategoryFiltersResponse {
  success: true;
  data: CategoryFilters;
  meta: {
    request_id: `req_${string}`;
  };
}

export interface SuggestionListResponse {
  success: true;
  data: Array<Suggestion>;
  meta: {
    request_id: `req_${string}`;
  };
}

export interface CatalogTermListMeta {
  request_id: `req_${string}`;
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface BrandListResponse {
  success: true;
  data: Array<Brand>;
  meta: CatalogTermListMeta;
}

export interface TagListResponse {
  success: true;
  data: Array<Tag>;
  meta: CatalogTermListMeta;
}

export interface SellerPublicResponse {
  success: true;
  data: SellerPublic;
  meta: Meta;
}

export interface CustomerAccount {
  /**
   * Random stable customer identifier; never a WordPress numeric user ID.
   */
  id: `cus_${string}`;
  first_name: string;
  last_name: string;
  display_name: string;
  email: string;
}

export interface RegisterRequest {
  email: string;
  password: string;
  first_name?: string;
  last_name?: string;
  display_name?: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface AuthSession {
  customer: CustomerAccount;
  /**
   * Session-bound CSRF value; keep in memory and send as X-ElDokan-CSRF for mutations.
   */
  csrf_token: string;
}

export interface AuthSessionResponse {
  success: true;
  data: AuthSession;
  meta: Meta;
}

export interface LogoutResult {
  logged_out: true;
}

export interface LogoutResponse {
  success: true;
  data: LogoutResult;
  meta: Meta;
}

export interface AccountUpdateRequest {
  first_name?: string;
  last_name?: string;
  display_name?: string;
}

export interface CustomerAccountResponse {
  success: true;
  data: CustomerAccount;
  meta: Meta;
}

export interface Wishlist {
  items: Array<ProductCard>;
  count: number;
}

export interface WishlistMutation {
  items: Array<ProductCard>;
  count: number;
  /**
   * False for a duplicate add or removal of an item that was not present.
   */
  changed: boolean;
}

export interface WishlistItemRequest {
  product_id: `prd_${number}`;
}

export interface WishlistResponse {
  success: true;
  data: Wishlist;
  meta: Meta;
}

export interface WishlistMutationResponse {
  success: true;
  data: WishlistMutation;
  meta: Meta;
}
