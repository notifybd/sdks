"""Transport-independent pieces shared by the sync and async clients."""

from __future__ import annotations

import random
from dataclasses import dataclass
from typing import Any, Dict, Mapping, Optional

import httpx

from ._version import __version__
from .errors import APIError, error_for_status

#: Replace with the production URL before the first release (see docs/RELEASING.md).
DEFAULT_BASE_URL = "https://api.notify.example/v1"
RETRY_STATUSES = frozenset({408, 429, 500, 502, 503, 504})
_MAX_BACKOFF = 8.0
_BASE_BACKOFF = 0.5


@dataclass(frozen=True)
class Config:
    api_key: str
    base_url: str
    timeout: float
    max_retries: int
    app_info: Optional[Mapping[str, str]] = None

    def headers(self, has_body: bool, extra: Optional[Mapping[str, str]] = None) -> Dict[str, str]:
        agent = f"notify-python/{__version__}"
        if self.app_info:
            name, version = self.app_info.get("name"), self.app_info.get("version")
            if name:
                agent += f" {name}" + (f"/{version}" if version else "")
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Accept": "application/json",
            "User-Agent": agent,
        }
        if has_body:
            headers["Content-Type"] = "application/json"
        headers.update(extra or {})
        return headers

    def url(self, path: str) -> str:
        return self.base_url.rstrip("/") + path


def clean_params(params: Optional[Mapping[str, Any]]) -> Dict[str, Any]:
    return {k: v for k, v in (params or {}).items() if v is not None}


def backoff(attempt: int) -> float:
    ceiling = min(_MAX_BACKOFF, _BASE_BACKOFF * (1 << attempt))
    return ceiling / 2 + random.random() * (ceiling / 2)


def retry_delay(response: httpx.Response, attempt: int) -> float:
    try:
        seconds = float(response.headers.get("retry-after", ""))
    except ValueError:
        return backoff(attempt)
    return min(seconds, 30.0) if seconds > 0 else backoff(attempt)


def to_api_error(response: httpx.Response) -> APIError:
    try:
        body = response.json()
        error = body.get("error", {}) if isinstance(body, dict) else {}
    except ValueError:
        error = {}
    try:
        retry_after: Optional[float] = float(response.headers["retry-after"])
    except (KeyError, ValueError):
        retry_after = None
    return error_for_status(
        response.status_code,
        error.get("code", "UNKNOWN"),
        error.get("message", f"Request failed with status {response.status_code}"),
        request_id=error.get("requestId") or response.headers.get("x-request-id"),
        details=error.get("details"),
        retry_after=retry_after,
    )


def parse_body(response: httpx.Response) -> Any:
    if response.status_code == 204 or not response.content:
        return None
    return response.json()
