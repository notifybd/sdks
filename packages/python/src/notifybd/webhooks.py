"""Webhook verification.

Pass the **raw** request body exactly as received: parsing and re-serialising it changes the bytes
and breaks the signature.
"""

from __future__ import annotations

import hashlib
import hmac
import json
import re
import time
from typing import List, Optional, Tuple, Union

from .errors import SignatureVerificationError
from .models import WebhookEvent

SIGNATURE_HEADER = "X-Notify-Signature"
DEFAULT_TOLERANCE_SECONDS = 300

_Reason = SignatureVerificationError


def _parse(header: Optional[str]) -> Optional[Tuple[int, List[str]]]:
    if not header:
        return None
    timestamp: Optional[int] = None
    signatures: List[str] = []
    for part in header.split(","):
        key, sep, value = part.partition("=")
        if not sep:
            continue
        key, value = key.strip(), value.strip()
        if key == "t" and re.fullmatch(r"\d+", value):
            timestamp = int(value)
        elif key == "v1" and value:
            signatures.append(value)
    if timestamp is None or not signatures:
        return None
    return timestamp, signatures


def verify_signature(
    raw_body: Union[str, bytes],
    header: Optional[str],
    secret: str,
    *,
    tolerance: int = DEFAULT_TOLERANCE_SECONDS,
    now: Optional[float] = None,
) -> None:
    """Raise :class:`SignatureVerificationError` unless the webhook is authentic and fresh."""
    parsed = _parse(header)
    if parsed is None:
        raise SignatureVerificationError(
            _Reason.MALFORMED_HEADER, "The X-Notify-Signature header is missing or malformed"
        )
    timestamp, signatures = parsed

    current = time.time() if now is None else now
    if abs(current - timestamp) > tolerance:
        raise SignatureVerificationError(
            _Reason.TIMESTAMP_OUTSIDE_TOLERANCE,
            "The webhook timestamp is outside the allowed window",
        )

    body = raw_body.encode("utf-8") if isinstance(raw_body, str) else raw_body
    expected = hmac.new(
        secret.encode("utf-8"), f"{timestamp}.".encode() + body, hashlib.sha256
    ).hexdigest()

    # More than one v1 appears while a secret is being rotated; any match is enough.
    if not any(hmac.compare_digest(expected, signature.lower()) for signature in signatures):
        raise SignatureVerificationError(
            _Reason.SIGNATURE_MISMATCH, "No signature matches the payload"
        )


def construct_event(
    raw_body: Union[str, bytes],
    header: Optional[str],
    secret: str,
    *,
    tolerance: int = DEFAULT_TOLERANCE_SECONDS,
    now: Optional[float] = None,
) -> WebhookEvent:
    """Verify a webhook and return its parsed body.

    Raises :class:`SignatureVerificationError` when the header is malformed, the timestamp is
    outside ``tolerance`` seconds (replay protection) or no signature matches.
    """
    verify_signature(raw_body, header, secret, tolerance=tolerance, now=now)
    try:
        return WebhookEvent.from_api(json.loads(raw_body))
    except (ValueError, KeyError, TypeError) as error:
        raise SignatureVerificationError(
            _Reason.INVALID_PAYLOAD, "The webhook body is not a valid event"
        ) from error
