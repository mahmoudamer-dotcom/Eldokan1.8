import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { ProductId } from '../types.js';
import type { OrderId } from './orders.js';
export type CommerceResponse<T> = {
    success: true;
    data: T;
    meta: {
        request_id: string;
    };
};
export type ReturnRequest = {
    id: string;
    state: 'requested' | 'approved' | 'received' | 'closed' | 'rejected';
    reason: string;
    details: string;
    staff_note?: string;
    lines: {
        item_id: number;
        quantity: number;
    }[];
    created_at: string;
    updated_at: string;
};
export type ReturnSummary = {
    items: {
        item_id: number;
        name: string;
        quantity: number;
    }[];
    available: boolean;
    request: ReturnRequest | null;
    refunded_amount: string;
    currency: string;
};
export type ProductAlert = {
    product_id: ProductId;
    name: string;
    kind: 'stock' | 'price';
    target_price: number | null;
    notified_at: string | null;
};
export type DecisionProfile = {
    category: string;
    budget: string;
    keywords: string;
    priority: string;
    compare_ids: string[];
};
export type ReviewFeedback = {
    helpful_count: number;
    images: {
        url: string;
        alt: string;
    }[];
};
export type SitemapPage = {
    categories: {
        slug: string;
    }[];
    items: {
        id: ProductId;
        updated_at: string | null;
    }[];
    total_pages: number;
};
/** Customer commerce extensions. Provider secrets and shipping APIs stay on the server. */
export declare class CommerceResource {
    private readonly http;
    private readonly session;
    constructor(http: EldokanHttpClient, session: CustomerSessionState);
    private write;
    forgotPassword(email: string): Promise<CommerceResponse<{
        accepted: true;
    }>>;
    resetPassword(input: {
        login: string;
        key: string;
        password: string;
    }): Promise<CommerceResponse<{
        reset: true;
    }>>;
    returns(id: OrderId): Promise<CommerceResponse<ReturnSummary>>;
    requestReturn(id: OrderId, input: {
        reason: string;
        details: string;
        lines: {
            item_id: number;
            quantity: number;
        }[];
    }): Promise<CommerceResponse<ReturnSummary>>;
    alerts(): Promise<CommerceResponse<{
        items: ProductAlert[];
    }>>;
    saveAlert(input: {
        product_id: ProductId;
        kind: 'stock' | 'price';
        target_price?: number;
    }): Promise<CommerceResponse<{
        items: ProductAlert[];
    }>>;
    removeAlert(productId: ProductId): Promise<CommerceResponse<{
        items: ProductAlert[];
    }>>;
    decision(): Promise<CommerceResponse<DecisionProfile>>;
    saveDecision(input: DecisionProfile): Promise<CommerceResponse<DecisionProfile>>;
    feedback(reviewId: string, action: 'helpful' | 'report', reason?: string): Promise<CommerceResponse<{
        accepted: true;
        helpful_count: number;
    }>>;
    addReviewImage(reviewId: string, image: string): Promise<CommerceResponse<ReviewFeedback>>;
    sitemap(page?: number): Promise<CommerceResponse<SitemapPage>>;
}
//# sourceMappingURL=commerce.d.ts.map