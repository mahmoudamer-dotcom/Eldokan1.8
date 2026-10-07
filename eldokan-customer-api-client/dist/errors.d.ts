import type { CheckoutIssue, EldokanResponseContext, ErrorEnvelope } from './types.js';
export type EldokanClientErrorKind = 'api' | 'network' | 'timeout' | 'invalid_response' | 'validation';
export declare class EldokanClientError extends Error {
    readonly kind: EldokanClientErrorKind;
    readonly status: number | null;
    readonly code: string;
    readonly requestId: string | null;
    readonly context: EldokanResponseContext | null;
    readonly issues: ReadonlyArray<CheckoutIssue>;
    constructor(options: {
        message: string;
        kind: EldokanClientErrorKind;
        code: string;
        status?: number | null;
        requestId?: string | null;
        context?: EldokanResponseContext | null;
        issues?: ReadonlyArray<CheckoutIssue>;
        cause?: unknown;
    });
}
export declare function isErrorEnvelope(value: unknown): value is ErrorEnvelope;
//# sourceMappingURL=errors.d.ts.map