from typing import Any, Callable, List

import httpx
import pytest

from notifybd import (
    APIConnectionError,
    APIError,
    AsyncNotify,
    AuthenticationError,
    ConflictError,
    Notify,
    RateLimitError,
    UnprocessableError,
    ValidationError,
)

ACCEPTED = {
    "id": "018f0000-0000-7000-8000-000000000001",
    "status": "accepted",
    "reference": None,
    "createdAt": "2026-01-01T00:00:00.000Z",
}
SEND: Any = dict(to="01712345678", body="Your code is 1", message_type="otp", sender_id="Shop")


def make(handler: Callable[[httpx.Request], httpx.Response], **kw: Any) -> Notify:
    return Notify(
        "nk_test",
        base_url="https://api.test/v1",
        http_client=httpx.Client(transport=httpx.MockTransport(handler)),
        **kw,
    )


def test_requires_api_key(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.delenv("NOTIFY_API_KEY", raising=False)
    with pytest.raises(ValueError, match="No API key"):
        Notify()


def test_send_sets_auth_agent_and_idempotency_key() -> None:
    seen: List[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request)
        return httpx.Response(202, json=ACCEPTED)

    result = make(handler).messages.send(**SEND)

    assert result.id == ACCEPTED["id"]
    request = seen[0]
    assert str(request.url) == "https://api.test/v1/messages"
    assert request.headers["authorization"] == "Bearer nk_test"
    assert request.headers["user-agent"].startswith("notify-python/")
    assert len(request.headers["idempotency-key"]) == 36
    assert request.read() == (
        b'{"to":"01712345678","body":"Your code is 1","messageType":"otp","senderId":"Shop"}'
    )


def test_retries_reuse_one_idempotency_key() -> None:
    keys: List[str] = []
    responses = [
        httpx.Response(503, json={"error": {"code": "UPSTREAM_UNAVAILABLE", "message": "x"}}),
        httpx.Response(202, json=ACCEPTED),
    ]

    def handler(request: httpx.Request) -> httpx.Response:
        keys.append(request.headers["idempotency-key"])
        return responses.pop(0)

    make(handler).messages.send(**SEND)
    assert len(keys) == 2 and keys[0] == keys[1]


def test_caller_idempotency_key_is_used() -> None:
    seen: List[str] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request.headers["idempotency-key"])
        return httpx.Response(202, json=ACCEPTED)

    make(handler).messages.send(idempotency_key="order-42", **SEND)
    assert seen == ["order-42"]


def test_network_errors_are_retried_then_raised() -> None:
    calls = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        raise httpx.ConnectError("down")

    with pytest.raises(APIConnectionError):
        make(handler, max_retries=1).messages.send(**SEND)
    assert calls == 2


@pytest.mark.parametrize(
    ("status", "code", "error_type"),
    [
        (400, "VALIDATION_ERROR", ValidationError),
        (401, "UNAUTHENTICATED", AuthenticationError),
        (409, "CONFLICT", ConflictError),
        (422, "POLICY_REJECTION", UnprocessableError),
    ],
)
def test_error_mapping_without_retry(status: int, code: str, error_type: type[APIError]) -> None:
    calls = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(
            status, json={"error": {"code": code, "message": "nope", "requestId": "req-1"}}
        )

    with pytest.raises(error_type) as error:
        make(handler).messages.send(**SEND)
    assert error.value.status == status and error.value.code == code
    assert error.value.request_id == "req-1"
    assert calls == 1


def test_rate_limit_exposes_retry_after() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(
            429,
            headers={"retry-after": "7"},
            json={"error": {"code": "RATE_LIMITED", "message": "slow"}},
        )

    with pytest.raises(RateLimitError) as error:
        make(handler, max_retries=0).messages.send(**SEND)
    assert error.value.retry_after == 7


def test_list_all_follows_cursors() -> None:
    urls: List[str] = []
    message = {
        "id": "m",
        "businessId": "b",
        "senderId": "s",
        "body": "x",
        "messageType": "otp",
        "status": "sent",
        "toE164Hash": "h",
        "reference": None,
        "channel": "app",
        "failureReason": None,
        "createdAt": "t",
        "updatedAt": "t",
        "sentAt": None,
        "deliveredAt": None,
        "failedAt": None,
        "readAt": None,
        "futureField": 1,
    }

    def handler(request: httpx.Request) -> httpx.Response:
        urls.append(str(request.url))
        if "cursor=c1" in urls[-1]:
            return httpx.Response(200, json={"data": [message], "nextCursor": None})
        return httpx.Response(200, json={"data": [message, message], "nextCursor": "c1"})

    assert len(list(make(handler).messages.list_all(limit=2))) == 3
    assert urls == [
        "https://api.test/v1/messages?limit=2",
        "https://api.test/v1/messages?limit=2&cursor=c1",
    ]


def test_senders() -> None:
    sender = {
        "id": "s1",
        "value": "Shop",
        "status": "pending",
        "approvedAt": None,
        "createdAt": "t",
    }

    def handler(request: httpx.Request) -> httpx.Response:
        if request.method == "POST":
            return httpx.Response(201, json=sender)
        return httpx.Response(200, json=[sender])

    notify = make(handler)
    assert notify.senders.create("Shop").status == "pending"
    assert len(notify.senders.list()) == 1


async def test_async_client_sends_and_retries() -> None:
    responses = [
        httpx.Response(502, json={"error": {"code": "UPSTREAM_UNAVAILABLE", "message": "x"}}),
        httpx.Response(202, json=ACCEPTED),
    ]

    def handler(request: httpx.Request) -> httpx.Response:
        return responses.pop(0)

    async with AsyncNotify(
        "nk_test",
        base_url="https://api.test/v1",
        http_client=httpx.AsyncClient(transport=httpx.MockTransport(handler)),
    ) as notify:
        result = await notify.messages.send(**SEND)
    assert result.status == "accepted"
