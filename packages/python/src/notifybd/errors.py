"""Exceptions raised by the SDK. Everything derives from :class:`NotifyError`."""

from __future__ import annotations

from typing import Any, List, Optional


class NotifyError(Exception):
    """Base class of everything this SDK raises."""


class APIConnectionError(NotifyError):
    """The request never produced an HTTP response (DNS, connection reset, timeout)."""


class APITimeoutError(APIConnectionError):
    """The request timed out."""


class APIError(NotifyError):
    """The API answered with an error status."""

    def __init__(
        self,
        status: int,
        code: str,
        message: str,
        *,
        request_id: Optional[str] = None,
        details: Optional[List[Any]] = None,
        retry_after: Optional[float] = None,
    ) -> None:
        super().__init__(message)
        self.status = status
        #: Stable machine-readable code, for example ``VALIDATION_ERROR`` or ``RATE_LIMITED``.
        self.code = code
        self.message = message
        #: Quote this when contacting support.
        self.request_id = request_id
        self.details = details
        #: Seconds the API asked us to wait (429 only).
        self.retry_after = retry_after

    def __str__(self) -> str:
        suffix = f" (request {self.request_id})" if self.request_id else ""
        return f"{self.status} {self.code}: {self.message}{suffix}"


class ValidationError(APIError):
    """400: the request failed validation. See ``details`` for the offending fields."""


class AuthenticationError(APIError):
    """401: the API key is missing, invalid or revoked."""


class InsufficientCreditsError(APIError):
    """402: the prepaid balance is exhausted."""


class PermissionDeniedError(APIError):
    """403: the key lacks the scope, or the account is frozen."""


class NotFoundError(APIError):
    """404."""


class ConflictError(APIError):
    """409: the idempotency key was already used with a different request."""


class UnprocessableError(APIError):
    """422: refused by policy (marketing content, unapproved sender ID or link domain)."""


class RateLimitError(APIError):
    """429: slow down. ``retry_after`` says for how long."""


class ServerError(APIError):
    """5xx: a problem on our side. Safe to retry."""


class SignatureVerificationError(NotifyError):
    """A webhook failed verification. ``reason`` is machine readable."""

    MALFORMED_HEADER = "malformed_header"
    TIMESTAMP_OUTSIDE_TOLERANCE = "timestamp_outside_tolerance"
    SIGNATURE_MISMATCH = "signature_mismatch"
    INVALID_PAYLOAD = "invalid_payload"

    def __init__(self, reason: str, message: str) -> None:
        super().__init__(message)
        self.reason = reason


_BY_STATUS = {
    400: ValidationError,
    401: AuthenticationError,
    402: InsufficientCreditsError,
    403: PermissionDeniedError,
    404: NotFoundError,
    409: ConflictError,
    422: UnprocessableError,
    429: RateLimitError,
}


def error_for_status(
    status: int,
    code: str,
    message: str,
    *,
    request_id: Optional[str] = None,
    details: Optional[List[Any]] = None,
    retry_after: Optional[float] = None,
) -> APIError:
    cls = _BY_STATUS.get(status) or (ServerError if status >= 500 else APIError)
    return cls(
        status,
        code,
        message,
        request_id=request_id,
        details=details,
        retry_after=retry_after,
    )
