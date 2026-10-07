/* eslint-disable */
/**
 * AUTO-GENERATED from openapi/eldokan-customer-api-v1.openapi.json.
 * Contract: ElDokan Customer API v1.
 * Do not edit manually. Run: npm run generate:types
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

/**
 * Open service code string, including invalid_phone and phone_storage_unavailable; issues retain structured checkout problems. See normalized service symbol reference.
 */
export interface Error {
  code: string;
  message: string;
  issues?: Array<CheckoutIssue>;
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
  /**
   * Private canonical billing phone; always string, empty value is "". Independent of address phone.
   */
  phone: string;
}

export interface RegisterRequest {
  email: string;
  password: string;
  first_name?: string;
  last_name?: string;
  display_name?: string;
  /**
   * Optional; empty string clears. Nonempty ASCII digits, optional leading +, spaces, parentheses, hyphens; at least six digits. Null invalid. Trim surrounding spaces only. No identity/verification semantics.
   */
  phone?: string;
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
  /**
   * Optional; empty string clears. Nonempty ASCII digits, optional leading +, spaces, parentheses, hyphens; at least six digits. Null invalid. Trim surrounding spaces only. No identity/verification semantics.
   */
  phone?: string;
}

export interface CustomerAccountResponse {
  success: true;
  data: CustomerAccount;
  meta: Meta;
}

export interface CartIssue {
  code: "not_purchasable" | "out_of_stock" | "insufficient_stock" | "purchase_quantity_limit";
  message: string;
}

export interface CartItem {
  id: `cit_${string}`;
  product_id: `prd_${number}`;
  variation_id: `var_${number}` | null;
  name: string;
  image: Image | null;
  selected_attributes: Array<VariationSelection>;
  quantity: number;
  unit_price: Money | null;
  line_subtotal: Money | null;
  stock: Stock;
  seller: SellerPublic | null;
  valid: boolean;
  issues: Array<CartIssue>;
}

export interface Cart {
  items: Array<CartItem>;
  count: number;
  /**
   * False when current price/stock/purchasability validation leaves any line not checkout-ready. Cart never reserves stock.
   */
  valid: boolean;
  owner_type: "guest" | "customer";
  /**
   * Session-bound Cart mutation token; keep in memory and never localStorage.
   */
  csrf_token: string;
}

export interface CartMutation {
  items: Array<CartItem>;
  count: number;
  /**
   * False when current price/stock/purchasability validation leaves any line not checkout-ready. Cart never reserves stock.
   */
  valid: boolean;
  owner_type: "guest" | "customer";
  /**
   * Session-bound Cart mutation token; keep in memory and never localStorage.
   */
  csrf_token: string;
  /**
   * False for retry-safe duplicate add or removal of an absent item.
   */
  changed: boolean;
}

export interface CartItemRequest {
  product_id: `prd_${number}`;
  variation_id?: `var_${number}` | null;
  quantity: number;
}

export interface CartItemUpdateRequest {
  quantity: number;
}

export interface CartResponse {
  success: true;
  data: Cart;
  meta: Meta;
}

export interface CartMutationResponse {
  success: true;
  data: CartMutation;
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

export type Customer = CustomerAccount;

export type Session = AuthSession;

export interface Issue {
  code: string;
  message: string;
}

export interface CheckoutIssue {
  code: string;
  message: string;
  blocking: boolean;
  /**
   * Opaque string; never parse or convert its suffix.
   */
  item_id?: `cit_${string}`;
}

export type CartLine = CartItem;

/**
 * Opaque string; never parse or convert its suffix.
 */
export type PublicCusId = `cus_${string}`;

/**
 * Opaque string; never parse or convert its suffix.
 */
export type PublicAdrId = `adr_${string}`;

/**
 * Opaque string; never parse or convert its suffix.
 */
export type PublicCitId = `cit_${string}`;

/**
 * Opaque string; never parse or convert its suffix.
 */
export type PublicChkId = `chk_${string}`;

/**
 * Opaque string; never parse or convert its suffix.
 */
export type PublicOrdId = `ord_${string}`;

/**
 * Opaque string; never parse or convert its suffix.
 */
export type PublicShpId = `shp_${string}`;

/**
 * Opaque string; never parse or convert its suffix.
 */
export type PublicGokId = `gok_${string}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicPrdId = `prd_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicVarId = `var_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicSelId = `sel_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicCatId = `cat_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicBrdId = `brd_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicTagId = `tag_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicAttId = `att_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicAtrId = `atr_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicHslId = `hsl_${number}`;

/**
 * Opaque catalog string; decimal suffix is never a numeric API identity.
 */
export type PublicHbnId = `hbn_${number}`;

export interface CheckoutAddressInput {
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  company?: string | null;
  country?: "EG";
  state: string;
  city: string;
  street_address: string;
  address_extra?: string | null;
}

export interface CheckoutAddress {
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  company: string | null;
  country: "EG";
  state: string;
  city: string;
  street_address: string;
  address_extra: string | null;
  /**
   * Opaque string; never parse or convert its suffix.
   */
  id?: `adr_${string}`;
}

export interface Address {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  id: `adr_${string}`;
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  company: string | null;
  country: "EG";
  state: string;
  city: string;
  street_address: string;
  address_extra: string | null;
  is_default: boolean;
}

export interface AddressCreate {
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  company?: string | null;
  country: "EG";
  state: string;
  city: string;
  street_address: string;
  address_extra?: string | null;
  is_default?: boolean;
}

export interface AddressUpdate {
  first_name?: string;
  last_name?: string;
  phone?: string;
  email?: string;
  company?: string | null;
  country?: "EG";
  state?: string;
  city?: string;
  street_address?: string;
  address_extra?: string | null;
  is_default?: boolean;
}

export interface AddressBook {
  items: Array<Address>;
  count: number;
  /**
   * Opaque string; never parse or convert its suffix.
   */
  default_address_id: `adr_${string}` | null;
}

export interface AddressDeleted {
  deleted: true;
  /**
   * Opaque string; never parse or convert its suffix.
   */
  id: `adr_${string}`;
  /**
   * Opaque string; never parse or convert its suffix.
   */
  default_address_id: `adr_${string}` | null;
}

export interface ShippingChoice {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  id: `shp_${string}`;
  name: string;
  description: string;
  amount: Money;
  available: true;
  type: "pickup" | "door_delivery";
}

export interface PaymentChoice {
  id: "cod" | "paymob";
  name: string;
  description: string;
  available: true;
  requires_redirect: boolean;
}

export interface CheckoutTotals {
  subtotal: Money | null;
  shipping: Money | null;
  tax: Money | null;
  fees: Money | null;
  discount: Money | null;
  total: Money | null;
  taxes_enabled: boolean;
  calculable: boolean;
}

export interface CheckoutCartItem {
  id: `cit_${string}`;
  product_id: `prd_${number}`;
  variation_id: `var_${number}` | null;
  name: string;
  image: Image | null;
  selected_attributes: Array<VariationSelection>;
  quantity: number;
  unit_price: Money | null;
  line_subtotal: Money | null;
  stock: Stock;
  seller: SellerPublic | null;
  valid: boolean;
  issues: Array<CheckoutIssue>;
}

export interface CheckoutCart {
  items: Array<CheckoutCartItem>;
  count: number;
  /**
   * False when current price/stock/purchasability validation leaves any line not checkout-ready. Cart never reserves stock.
   */
  valid: boolean;
  owner_type: "guest" | "customer";
  /**
   * Session-bound Cart mutation token; keep in memory and never localStorage.
   */
  csrf_token: string;
}

export interface Checkout {
  cart: CheckoutCart;
  ready: boolean;
  issues: Array<CheckoutIssue>;
  address: CheckoutAddress | null;
  address_requirements: {
    country: "EG";
    required_fields: Array<string>;
    state_codes: Array<string>;
    required: true;
  };
  saved_addresses: AddressBook;
  shipping_required: boolean;
  shipping_calculable: boolean;
  shipping_methods: Array<ShippingChoice>;
  selected_shipping_method: ShippingChoice | null;
  payment_methods: Array<PaymentChoice>;
  payment_availability_calculable: boolean;
  payment_required: boolean | null;
  totals: CheckoutTotals;
  stock_reserved: false;
}

export type QuoteInput = {
  address_id: `adr_${string}`;
  shipping_method_id?: `shp_${string}`;
} | {
  address: CheckoutAddressInput;
  shipping_method_id?: `shp_${string}`;
};

export type PurchaseInput = {
  address_id: `adr_${string}`;
  shipping_method_id: `shp_${string}` | null;
  payment_method: "cod" | "paymob";
  order_notes?: string;
} | {
  address: CheckoutAddressInput;
  shipping_method_id: `shp_${string}` | null;
  payment_method: "cod" | "paymob";
  order_notes?: string;
};

export type PlacementInput = {
  address_id: `adr_${string}`;
  shipping_method_id: `shp_${string}` | null;
  payment_method: "cod" | "paymob";
  order_notes?: string;
  checkout_attempt_id: `chk_${string}`;
} | {
  address: CheckoutAddressInput;
  shipping_method_id: `shp_${string}` | null;
  payment_method: "cod" | "paymob";
  order_notes?: string;
  checkout_attempt_id: `chk_${string}`;
};

export interface CheckoutAttempt {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  checkout_attempt_id: `chk_${string}`;
  state: "prepared";
  stock_reserved: false;
}

export interface GuestAccess {
  /**
   * Sensitive capability for exactly one Guest Order. Header only. Never log, URL-encode, share or put in analytics.
   */
  credential: `gok_${string}`;
  /**
   * Dedicated Guest Order mutation token; independent of account/Cart CSRF.
   */
  csrf_token: string;
  header: "X-ElDokan-Order-Access";
}

/**
 * Authoritative normalized live Woo state. completed/custom native states require explicitly verified mapping; otherwise order_read_unavailable. Never infer delivery from completed.
 */
export type OrderStatus = "pending_payment" | "processing" | "awaiting_pickup" | "shipped" | "delivered" | "cancelled" | "failed" | "refunded";

export type PaymentStatus = "unpaid" | "pending" | "paid" | "failed" | "cancelled" | "refunded";

export interface OrderSeller {
  id: `sel_${number}`;
  name: string;
}

export interface Fulfillment {
  seller: OrderSeller;
  /**
   * Independent trusted mapping; no invented aggregate delivery inference.
   */
  status: "pending" | "pending_payment" | "processing" | "awaiting_pickup" | "shipped" | "delivered" | "cancelled" | "failed" | "refunded";
}

export interface PlacementLine {
  name: string;
  quantity: number;
  subtotal: Money;
  total: Money;
  seller: OrderSeller;
}

export interface Payment {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  order_id: `ord_${string}`;
  order_status: OrderStatus;
  payment_status: PaymentStatus;
  payment_method: "paymob";
  requires_redirect: boolean;
  /**
   * Sensitive HTTPS native Paymob hosted session URL. Only use when requires_redirect=true; never log/cache.
   */
  redirect_url: string | null;
  retryable: boolean;
  generation: number;
  expires_at: string | null;
  issue?: Issue;
}

export interface PlacementPaymentIssue {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  order_id: `ord_${string}`;
  requires_redirect: false;
  redirect_url: null;
  retryable: true;
  issue: Issue;
}

export interface Placement {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  order_id: `ord_${string}`;
  /**
   * Opaque string; never parse or convert its suffix.
   */
  checkout_attempt_id: `chk_${string}`;
  /**
   * Immutable native Woo placement-time status; not normalized live OrderStatus.
   */
  status: string;
  payment_method: {
    id: "cod" | "paymob";
    name: string;
    requires_redirect: boolean;
  };
  total: Money;
  currency: string;
  lines: Array<PlacementLine>;
  shipping: ShippingChoice | null;
  created_at: string;
  guest_access?: GuestAccess;
  payment?: Payment | PlacementPaymentIssue;
}

export interface OrderSummary {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  id: `ord_${string}`;
  created_at: string;
  updated_at: string | null;
  status: OrderStatus;
  payment_status: PaymentStatus;
  payment_method: "cod" | "paymob" | "unavailable";
  currency: string;
  total: Money;
  /**
   * Sum of quantities.
   */
  item_count: number;
  shipping: ShippingChoice | null;
  fulfillments: Array<Fulfillment>;
}

export interface OrderLine {
  product_id: `prd_${number}`;
  variation_id: `var_${number}` | null;
  name: string;
  quantity: number;
  subtotal: Money;
  total: Money;
  tax: Money;
  seller: OrderSeller;
}

export interface OrderFee {
  name: string;
  total: Money;
  tax: Money;
}

export interface OrderDetail {
  /**
   * Opaque string; never parse or convert its suffix.
   */
  id: `ord_${string}`;
  created_at: string;
  updated_at: string | null;
  status: OrderStatus;
  payment_status: PaymentStatus;
  payment_method: "cod" | "paymob" | "unavailable";
  currency: string;
  total: Money;
  /**
   * Sum of quantities.
   */
  item_count: number;
  shipping: ShippingChoice | null;
  fulfillments: Array<Fulfillment>;
  subtotal: Money;
  discount: Money;
  shipping_total: Money;
  tax: Money;
  fees: Array<OrderFee>;
  address: OrderAddress;
  lines: Array<OrderLine>;
  order_notes: string;
}

export interface OrderAddress {
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  company: string | null;
  country: "EG";
  state: string;
  city: string;
  street_address: string;
  address_extra: string | null;
}

export interface OrderPagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface OrderList {
  items: Array<OrderSummary>;
  pagination: OrderPagination;
}

/**
 * {} recovers current state. Explicit retry=true requires observed expected_generation. No automatic new Order/attempt/generation. Fencing is not provider idempotency or cancellation of older sessions.
 */
export type PaymentInput = {
  retry?: false;
  expected_generation?: number;
} | {
  retry: true;
  expected_generation: number;
};

export interface AddressResponse {
  success: true;
  data: Address;
  meta: Meta;
}

export interface AddressBookResponse {
  success: true;
  data: AddressBook;
  meta: Meta;
}

export interface AddressDeletedResponse {
  success: true;
  data: AddressDeleted;
  meta: Meta;
}

export interface CheckoutResponse {
  success: true;
  data: Checkout;
  meta: Meta;
}

export interface CheckoutAttemptResponse {
  success: true;
  data: CheckoutAttempt;
  meta: Meta;
}

export interface PlacementResponse {
  success: true;
  data: Placement;
  meta: Meta;
}

export interface OrderListResponse {
  success: true;
  data: OrderList;
  meta: Meta;
}

export interface OrderDetailResponse {
  success: true;
  data: OrderDetail;
  meta: Meta;
}

export interface PaymentResponse {
  success: true;
  data: Payment;
  meta: Meta;
}
