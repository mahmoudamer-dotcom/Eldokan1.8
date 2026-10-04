import type { EldokanResponseContext, ErrorEnvelope } from './types.js';

export type EldokanClientErrorKind =
  | 'api'
  | 'network'
  | 'timeout'
  | 'invalid_response'
  | 'validation';

export class EldokanClientError extends Error {
  readonly kind: EldokanClientErrorKind;
  readonly status: number | null;
  readonly code: string;
  readonly requestId: string | null;
  readonly context: EldokanResponseContext | null;

  constructor(options: {
    message: string;
    kind: EldokanClientErrorKind;
    code: string;
    status?: number | null;
    requestId?: string | null;
    context?: EldokanResponseContext | null;
    cause?: unknown;
  }) {
    super(options.message, { cause: options.cause });
    this.name = 'EldokanClientError';
    this.kind = options.kind;
    this.code = options.code;
    this.status = options.status ?? null;
    this.requestId = options.requestId ?? null;
    this.context = options.context ?? null;
  }
}

export function isErrorEnvelope(value: unknown): value is ErrorEnvelope {
  if (!value || typeof value !== 'object') return false;
  const record = value as Record<string, unknown>;
  if (record.success !== false) return false;
  if (!record.error || typeof record.error !== 'object') return false;
  const error = record.error as Record<string, unknown>;
  return typeof error.code === 'string' && typeof error.message === 'string';
}
