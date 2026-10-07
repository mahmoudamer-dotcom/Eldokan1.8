export class EldokanClientError extends Error {
    kind;
    status;
    code;
    requestId;
    context;
    issues;
    constructor(options) {
        super(options.message, { cause: options.cause });
        this.name = 'EldokanClientError';
        this.kind = options.kind;
        this.code = options.code;
        this.status = options.status ?? null;
        this.requestId = options.requestId ?? null;
        this.context = options.context ?? null;
        this.issues = Array.isArray(options.issues)
            ? options.issues.filter((issue) => !!issue && typeof issue.code === 'string' && typeof issue.message === 'string')
            : [];
    }
}
export function isErrorEnvelope(value) {
    if (!value || typeof value !== 'object')
        return false;
    const record = value;
    if (record.success !== false)
        return false;
    if (!record.error || typeof record.error !== 'object')
        return false;
    const error = record.error;
    return typeof error.code === 'string' && typeof error.message === 'string';
}
//# sourceMappingURL=errors.js.map