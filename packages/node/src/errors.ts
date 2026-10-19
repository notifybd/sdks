export interface ErrorDetail {
  path: string;
  message: string;
}

/** Base class of everything this SDK throws. */
export class NotifyError extends Error {
  constructor(message: string, options?: { cause?: unknown }) {
    super(message, options);
    this.name = new.target.name;
  }
}

/** The request never produced an HTTP response (DNS, connection reset, timeout). */
export class ApiConnectionError extends NotifyError {}

export class ApiTimeoutError extends ApiConnectionError {}

/** The API answered with an error status. */
export class ApiError extends NotifyError {
  readonly status: number;
  /** Stable machine-readable code, for example `VALIDATION_ERROR` or `RATE_LIMITED`. */
  readonly code: string;
  /** Quote this when contacting support. */
  readonly requestId: string | undefined;
  readonly details: ErrorDetail[] | undefined;

  constructor(
    status: number,
    code: string,
    message: string,
    extra: { requestId?: string | undefined; details?: ErrorDetail[] | undefined } = {},
  ) {
    super(message);
    this.status = status;
    this.code = code;
    this.requestId = extra.requestId;
    this.details = extra.details;
  }
}

/** 400: the request failed validation. See `details` for the offending fields. */
export class ValidationError extends ApiError {}
/** 401: the API key is missing, invalid or revoked. */
export class AuthenticationError extends ApiError {}
/** 402: the prepaid balance is exhausted. */
export class InsufficientCreditsError extends ApiError {}
/** 403: the key lacks the scope, or the account is frozen. */
export class PermissionError extends ApiError {}
export class NotFoundError extends ApiError {}
/** 409: the idempotency key was already used with a different request. */
export class ConflictError extends ApiError {}
/** 422: refused by policy, for example marketing content, an unapproved sender ID or link domain. */
export class UnprocessableError extends ApiError {}
/** 429: slow down. `retryAfterSeconds` says for how long. */
export class RateLimitError extends ApiError {
  readonly retryAfterSeconds: number | undefined;

  constructor(
    status: number,
    code: string,
    message: string,
    extra: {
      requestId?: string | undefined;
      details?: ErrorDetail[] | undefined;
      retryAfterSeconds?: number | undefined;
    } = {},
  ) {
    super(status, code, message, extra);
    this.retryAfterSeconds = extra.retryAfterSeconds;
  }
}
/** 5xx: a problem on our side. Safe to retry. */
export class ServerError extends ApiError {}

/** A webhook failed verification. `reason` is one of the values below. */
export type SignatureFailure =
  | "malformed_header"
  | "timestamp_outside_tolerance"
  | "signature_mismatch"
  | "invalid_payload";

export class SignatureVerificationError extends NotifyError {
  readonly reason: SignatureFailure;

  constructor(reason: SignatureFailure, message: string) {
    super(message);
    this.reason = reason;
  }
}

export function errorForStatus(
  status: number,
  code: string,
  message: string,
  extra: {
    requestId?: string | undefined;
    details?: ErrorDetail[] | undefined;
    retryAfterSeconds?: number | undefined;
  },
): ApiError {
  switch (status) {
    case 400:
      return new ValidationError(status, code, message, extra);
    case 401:
      return new AuthenticationError(status, code, message, extra);
    case 402:
      return new InsufficientCreditsError(status, code, message, extra);
    case 403:
      return new PermissionError(status, code, message, extra);
    case 404:
      return new NotFoundError(status, code, message, extra);
    case 409:
      return new ConflictError(status, code, message, extra);
    case 422:
      return new UnprocessableError(status, code, message, extra);
    case 429:
      return new RateLimitError(status, code, message, extra);
    default:
      return status >= 500
        ? new ServerError(status, code, message, extra)
        : new ApiError(status, code, message, extra);
  }
}
