"""The asyncio client. Same surface as :class:`notifybd.Notify`, with ``await``."""

from __future__ import annotations

import asyncio
import uuid
from typing import Any, AsyncIterator, Dict, List, Mapping, Optional

import httpx

from . import _http
from ._http import Config
from .client import resolve_config
from .errors import APIConnectionError, APITimeoutError, NotifyError
from .models import (
    DeliveryEvent,
    Message,
    MessageEvent,
    Page,
    SenderId,
    SendMessageResponse,
)

_sleep = asyncio.sleep  # replaced in tests


class _AsyncTransport:
    def __init__(self, config: Config, client: httpx.AsyncClient) -> None:
        self._config = config
        self._client = client

    async def request(
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
                response = await self._client.request(
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
                    await _sleep(_http.retry_delay(response, attempt))
                    attempt += 1
                    continue
                raise _http.to_api_error(response)

            if safe and attempt < self._config.max_retries:
                await _sleep(_http.backoff(attempt))
                attempt += 1
                continue
            raise failure


class AsyncMessages:
    def __init__(self, transport: _AsyncTransport) -> None:
        self._t = transport

    async def send(
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
        """Submit a message: app first, SMS if the app cannot be reached. See ``Messages.send``."""
        payload: Dict[str, Any] = {
            "to": to,
            "body": body,
            "messageType": message_type,
            "senderId": sender_id,
        }
        if reference is not None:
            payload["reference"] = reference
        data = await self._t.request(
            "POST",
            "/messages",
            json=payload,
            headers={"Idempotency-Key": idempotency_key or str(uuid.uuid4())},
            retryable=True,
            timeout=timeout,
        )
        return SendMessageResponse.from_api(data)

    async def get(self, message_id: str) -> Message:
        return Message.from_api(await self._t.request("GET", f"/messages/{message_id}"))

    async def list(
        self, *, limit: Optional[int] = None, cursor: Optional[str] = None
    ) -> Page[Message]:
        return await self._page("/messages", Message.from_api, limit, cursor)

    async def list_all(self, *, limit: Optional[int] = None) -> AsyncIterator[Message]:
        """Every message, newest first: ``async for message in notify.messages.list_all()``."""
        cursor: Optional[str] = None
        while True:
            page = await self.list(limit=limit, cursor=cursor)
            for message in page.data:
                yield message
            if not page.has_more:
                return
            cursor = page.next_cursor

    async def events(
        self, message_id: str, *, limit: Optional[int] = None, cursor: Optional[str] = None
    ) -> Page[MessageEvent]:
        return await self._page(
            f"/messages/{message_id}/events", MessageEvent.from_api, limit, cursor
        )

    async def delivery_events(
        self, message_id: str, *, limit: Optional[int] = None, cursor: Optional[str] = None
    ) -> Page[DeliveryEvent]:
        return await self._page(
            f"/messages/{message_id}/delivery-events", DeliveryEvent.from_api, limit, cursor
        )

    async def _page(
        self, path: str, build: Any, limit: Optional[int], cursor: Optional[str]
    ) -> Page[Any]:
        wire = await self._t.request("GET", path, params={"limit": limit, "cursor": cursor})
        return Page([build(item) for item in wire["data"]], wire.get("nextCursor"))


class AsyncSenders:
    def __init__(self, transport: _AsyncTransport) -> None:
        self._t = transport

    async def create(self, value: str) -> SenderId:
        return SenderId.from_api(await self._t.request("POST", "/senders", json={"value": value}))

    async def list(self) -> List[SenderId]:
        return [SenderId.from_api(item) for item in await self._t.request("GET", "/senders")]


class AsyncNotify:
    """
    >>> async with AsyncNotify(api_key="...") as notify:
    ...     await notify.messages.send(to="01712345678", body="Your code is 123456",
    ...                                message_type="otp", sender_id="MyShop")
    """

    messages: AsyncMessages
    senders: AsyncSenders

    def __init__(
        self,
        api_key: Optional[str] = None,
        *,
        base_url: Optional[str] = None,
        timeout: float = 30.0,
        max_retries: int = 2,
        http_client: Optional[httpx.AsyncClient] = None,
        app_info: Optional[Mapping[str, str]] = None,
    ) -> None:
        config = resolve_config(api_key, base_url, timeout, max_retries, app_info)
        self._owns_client = http_client is None
        self._client = http_client or httpx.AsyncClient()
        transport = _AsyncTransport(config, self._client)
        self.messages = AsyncMessages(transport)
        self.senders = AsyncSenders(transport)

    async def aclose(self) -> None:
        if self._owns_client:
            await self._client.aclose()

    async def __aenter__(self) -> AsyncNotify:
        return self

    async def __aexit__(self, *exc: object) -> None:
        await self.aclose()
