"""Official Python SDK for the Notify transactional messaging API."""

from ._http import DEFAULT_BASE_URL
from ._version import __version__
from .aio import AsyncNotify
from .client import Notify
from .errors import (
    APIConnectionError,
    APIError,
    APITimeoutError,
    AuthenticationError,
    ConflictError,
    InsufficientCreditsError,
    NotFoundError,
    NotifyError,
    PermissionDeniedError,
    RateLimitError,
    ServerError,
    SignatureVerificationError,
    UnprocessableError,
    ValidationError,
)
from .models import (
    DeliveryEvent,
    Message,
    MessageEvent,
    Page,
    SenderId,
    SendMessageResponse,
    WebhookEvent,
    WebhookPayload,
)
from .webhooks import SIGNATURE_HEADER, construct_event, verify_signature

__all__ = [
    "DEFAULT_BASE_URL",
    "SIGNATURE_HEADER",
    "APIConnectionError",
    "APIError",
    "APITimeoutError",
    "AsyncNotify",
    "AuthenticationError",
    "ConflictError",
    "DeliveryEvent",
    "InsufficientCreditsError",
    "Message",
    "MessageEvent",
    "NotFoundError",
    "Notify",
    "NotifyError",
    "Page",
    "PermissionDeniedError",
    "RateLimitError",
    "SendMessageResponse",
    "SenderId",
    "ServerError",
    "SignatureVerificationError",
    "UnprocessableError",
    "ValidationError",
    "WebhookEvent",
    "WebhookPayload",
    "__version__",
    "construct_event",
    "verify_signature",
]
