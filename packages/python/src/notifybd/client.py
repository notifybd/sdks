"""The synchronous client. See :mod:`notifybd.aio` for the asyncio one."""

from __future__ import annotations

import os
import time
import uuid
from typing import Any, Dict, Iterator, List, Mapping, Optional

import httpx

from . import _http
from ._http import DEFAULT_BASE_URL, Config
from .errors import APIConnectionError, APITimeoutError, NotifyError
from .models import (
    DeliveryEvent,
    Message,
    MessageEvent,
    Page,
    SenderId,
    SendMessageResponse,
)

_sleep = time.sleep  # replaced in tests


class _Transport:
    def __init__(self, config: Config, client: httpx.Client) -> None:
        self._config = config
        self._client = client

    def request(
        self,
        method: str,
        path: str,
        *,
        params: Optional[Mapping[str, Any]] = None,
        json: Any = None,
        headers: Optional[Mapping[str, str]] = None,
        retryable: Optional[bool] = None,
        timeout: Optional[float] = None,
    ) -> Any:
        safe = method in ("GET", "DELETE") if retryable is None else retryable
        attempt = 0
        while True:
            try:
                response = self._client.request(
                    method,
                    self._config.url(path),
                    params=_http.clean_params(params),
                    json=json,
                    headers=self._config.headers(json is not None, headers),
                    timeout=timeout if timeout is not None else self._config.timeout,
                )
            except httpx.TimeoutException as error:
                failure: NotifyError = APITimeoutError("The request timed out")
                failure.__cause__ = error
            except httpx.TransportError as error:
                failure = APIConnectionError(f"Could not reach the Notify API: {error}")
                failure.__cause__ = error
            else:
                if response.is_success:
                    return _http.parse_body(response)
                if (
                    safe
                    and attempt < self._config.max_retries
                    and (response.status_code in _http.RETRY_STATUSES)
                ):
                    _sleep(_http.retry_delay(response, attempt))
                    attempt += 1
                    continue
                raise _http.to_api_error(response)

            if safe and attempt < self._config.max_retries:
                _sleep(_http.backoff(attempt))
                attempt += 1
                continue
            raise failure


class Messages:
    def __init__(self, transport: _Transport) -> None:
        self._t = transport

    def send(
        self,
        *,
        to: str,
        body: str,
        message_type: str,
        sender_id: str,
        reference: Optional[str] = None,
        idempotency_key: Optional[str] = None,
        timeout: Optional[float] = None,
    ) -> SendMessageResponse:
        """Submit a message: app first, SMS if the app cannot be reached.

        Returns once the message is accepted (HTTP 202). Every call carries an idempotency key
        (yours, or a generated one) so the SDK can retry network errors and 5xx responses without
        ever sending the message twice.
        """
        payload: Dict[str, Any] = {
            "to": to,
            "body": body,
            "messageType": message_type,
            "senderId": sender_id,
        }
        if reference is not None:
            payload["reference"] = reference
        data = self._t.request(
            "POST",
            "/messages",
            json=payload,
            headers={"Idempotency-Key": idempotency_key or str(uuid.uuid4())},
            retryable=True,
            timeout=timeout,
        )
        return SendMessageResponse.from_api(data)

    def get(self, message_id: str) -> Message:
        return Message.from_api(self._t.request("GET", f"/messages/{message_id}"))

    def list(self, *, limit: Optional[int] = None, cursor: Optional[str] = None) -> Page[Message]:
        """One page of messages, newest first."""
        return self._page("/messages", Message.from_api, limit, cursor)

    def list_all(self, *, limit: Optional[int] = None) -> Iterator[Message]:
        """Every message, newest first, fetching pages as you iterate."""
        cursor: Optional[str] = None
        while True:
            page = self.list(limit=limit, cursor=cursor)
            yield from page.data
            if not page.has_more:
                return
            cursor = page.next_cursor

    def events(
        self, message_id: str, *, limit: Optional[int] = None, cursor: Optional[str] = None
    ) -> Page[MessageEvent]:
        """The message's lifecycle events, newest first."""
        return self._page(f"/messages/{message_id}/events", MessageEvent.from_api, limit, cursor)

    def delivery_events(
        self, message_id: str, *, limit: Optional[int] = None, cursor: Optional[str] = None
    ) -> Page[DeliveryEvent]:
        """The message's delivery attempts (app push, SMS), newest first."""
        return self._page(
            f"/messages/{message_id}/delivery-events", DeliveryEvent.from_api, limit, cursor
        )

    def _page(
        self, path: str, build: Any, limit: Optional[int], cursor: Optional[str]
    ) -> Page[Any]:
        wire = self._t.request("GET", path, params={"limit": limit, "cursor": cursor})
        return Page([build(item) for item in wire["data"]], wire.get("nextCursor"))


class Senders:
    def __init__(self, transport: _Transport) -> None:
        self._t = transport

    def create(self, value: str) -> SenderId:
        """Request a sender ID. It stays ``pending`` until an operator approves it."""
        return SenderId.from_api(self._t.request("POST", "/senders", json={"value": value}))

    def list(self) -> List[SenderId]:
        return [SenderId.from_api(item) for item in self._t.request("GET", "/senders")]


def resolve_config(
    api_key: Optional[str],
    base_url: Optional[str],
    timeout: float,
    max_retries: int,
    app_info: Optional[Mapping[str, str]],
) -> Config:
    key = api_key or os.environ.get("NOTIFY_API_KEY")
    if not key:
        raise ValueError(
            "No API key. Pass api_key=... or set the NOTIFY_API_KEY environment variable."
        )
    return Config(
        api_key=key,
        base_url=base_url or os.environ.get("NOTIFY_BASE_URL") or DEFAULT_BASE_URL,
        timeout=timeout,
        max_retries=max_retries,
        app_info=app_info,
    )


class Notify:
    """
    >>> notify = Notify(api_key="...")          # or set NOTIFY_API_KEY
    >>> notify.messages.send(to="01712345678", body="Your code is 123456",
    ...                      message_type="otp", sender_id="MyShop")
    """

    messages: Messages
    senders: Senders

    def __init__(
        self,
        api_key: Optional[str] = None,
        *,
        base_url: Optional[str] = None,
        timeout: float = 30.0,
        max_retries: int = 2,
        http_client: Optional[httpx.Client] = None,
        app_info: Optional[Mapping[str, str]] = None,
    ) -> None:
        config = resolve_config(api_key, base_url, timeout, max_retries, app_info)
        self._owns_client = http_client is None
        self._client = http_client or httpx.Client()
        transport = _Transport(config, self._client)
        self.messages = Messages(transport)
        self.senders = Senders(transport)

    def close(self) -> None:
        if self._owns_client:
            self._client.close()

    def __enter__(self) -> Notify:
        return self

    def __exit__(self, *exc: object) -> None:
        self.close()
