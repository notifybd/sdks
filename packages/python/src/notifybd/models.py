"""Typed, immutable views of API objects.

Unknown fields from newer API versions are ignored, so upgrading the API never breaks an older SDK.
"""

from __future__ import annotations

from dataclasses import dataclass, fields
from typing import Any, Dict, Generic, List, Mapping, Optional, Type, TypeVar

T = TypeVar("T")


def _build(cls: Type[T], data: Mapping[str, Any]) -> T:
    names = {f.name for f in fields(cls)}  # type: ignore[arg-type]
    return cls(**{k: v for k, v in data.items() if k in names})


@dataclass(frozen=True)
class SendMessageResponse:
    """The ``202 Accepted`` envelope. Delivery happens asynchronously."""

    id: str
    status: str
    reference: Optional[str]
    created_at: str

    @classmethod
    def from_api(cls, data: Mapping[str, Any]) -> SendMessageResponse:
        return cls(data["id"], data["status"], data.get("reference"), data["createdAt"])


@dataclass(frozen=True)
class Message:
    id: str
    business_id: str
    sender_id: str
    body: str
    message_type: str
    #: ``received | queued | sent | delivered | failed``
    status: str
    #: HMAC of the recipient. The number itself is never returned.
    to_e164_hash: str
    reference: Optional[str]
    #: ``app | sms`` once a channel was chosen.
    channel: Optional[str]
    failure_reason: Optional[str]
    created_at: str
    updated_at: str
    sent_at: Optional[str]
    delivered_at: Optional[str]
    failed_at: Optional[str]
    read_at: Optional[str]

    @classmethod
    def from_api(cls, data: Mapping[str, Any]) -> Message:
        return cls(
            id=data["id"],
            business_id=data["businessId"],
            sender_id=data["senderId"],
            body=data["body"],
            message_type=data["messageType"],
            status=data["status"],
            to_e164_hash=data["toE164Hash"],
            reference=data.get("reference"),
            channel=data.get("channel"),
            failure_reason=data.get("failureReason"),
            created_at=data["createdAt"],
            updated_at=data["updatedAt"],
            sent_at=data.get("sentAt"),
            delivered_at=data.get("deliveredAt"),
            failed_at=data.get("failedAt"),
            read_at=data.get("readAt"),
        )


@dataclass(frozen=True)
class MessageEvent:
    event_id: str
    event_type: str
    message_id: str
    business_id: str
    to_e164_hash: Optional[str]
    channel: Optional[str]
    status: str
    error_code: Optional[str]
    correlation_id: str
    occurred_at: str

    @classmethod
    def from_api(cls, data: Mapping[str, Any]) -> MessageEvent:
        return cls(
            event_id=data["eventId"],
            event_type=data["eventType"],
            message_id=data["messageId"],
            business_id=data["businessId"],
            to_e164_hash=data.get("toE164Hash"),
            channel=data.get("channel"),
            status=data["status"],
            error_code=data.get("errorCode"),
            correlation_id=data["correlationId"],
            occurred_at=data["occurredAt"],
        )


@dataclass(frozen=True)
class DeliveryEvent:
    event_id: str
    event_type: str
    message_id: str
    business_id: str
    channel: Optional[str]
    #: The push provider or SMS carrier.
    provider: Optional[str]
    status: str
    error_code: Optional[str]
    correlation_id: str
    occurred_at: str

    @classmethod
    def from_api(cls, data: Mapping[str, Any]) -> DeliveryEvent:
        return cls(
            event_id=data["eventId"],
            event_type=data["eventType"],
            message_id=data["messageId"],
            business_id=data["businessId"],
            channel=data.get("channel"),
            provider=data.get("provider"),
            status=data["status"],
            error_code=data.get("errorCode"),
            correlation_id=data["correlationId"],
            occurred_at=data["occurredAt"],
        )


@dataclass(frozen=True)
class SenderId:
    id: str
    value: str
    #: ``pending | approved | rejected``
    status: str
    approved_at: Optional[str]
    created_at: str

    @classmethod
    def from_api(cls, data: Mapping[str, Any]) -> SenderId:
        return cls(
            data["id"], data["value"], data["status"], data.get("approvedAt"), data["createdAt"]
        )


@dataclass(frozen=True)
class Page(Generic[T]):
    data: List[T]
    #: Pass as ``cursor`` for the next page; ``None`` on the last page.
    next_cursor: Optional[str]

    @property
    def has_more(self) -> bool:
        return self.next_cursor is not None


@dataclass(frozen=True)
class WebhookPayload:
    message_id: str
    business_id: str
    #: ``delivered | failed``
    status: str
    channel: Optional[str]
    reason: Optional[str]


@dataclass(frozen=True)
class WebhookEvent:
    """The JSON body Notify POSTs to your endpoint when a message reaches a final status."""

    event_id: str
    #: ``message.delivered | message.failed``
    event_name: str
    event_version: int
    occurred_at: str
    correlation_id: str
    payload: WebhookPayload

    @classmethod
    def from_api(cls, data: Mapping[str, Any]) -> WebhookEvent:
        body: Dict[str, Any] = data["payload"]
        return cls(
            event_id=data["eventId"],
            event_name=data["eventName"],
            event_version=data["eventVersion"],
            occurred_at=data["occurredAt"],
            correlation_id=data["correlationId"],
            payload=WebhookPayload(
                message_id=body["messageId"],
                business_id=body["businessId"],
                status=body["status"],
                channel=body.get("channel"),
                reason=body.get("reason"),
            ),
        )
