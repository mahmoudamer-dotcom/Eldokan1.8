import type { EldokanHttpClient } from '../client.js';
import type { CustomerSessionState } from '../session.js';
import type { ReviewInput, ReviewListParams, ReviewListResponse, ReviewMineResponse, ReviewResponse, ReviewId, ProductId, SellerId } from '../types.js';
export declare class ReviewsResource {
    private readonly http;
    private readonly session;
    constructor(http: EldokanHttpClient, session: CustomerSessionState);
    private path;
    private id;
    private input;
    list(target: ProductId | SellerId, params?: ReviewListParams): Promise<ReviewListResponse>;
    mine(target: ProductId | SellerId): Promise<ReviewMineResponse>;
    create(target: ProductId | SellerId, input: ReviewInput): Promise<ReviewResponse>;
    update(target: ProductId | SellerId, id: ReviewId, input: ReviewInput): Promise<ReviewResponse>;
    remove(target: ProductId | SellerId, id: ReviewId): Promise<{
        success: true;
        data: {
            deleted: true;
        };
        meta: {
            request_id: string;
        };
    }>;
}
//# sourceMappingURL=reviews.d.ts.map