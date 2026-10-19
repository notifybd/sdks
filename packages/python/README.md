# notifybd — Python SDK for Notify

Send OTP and transactional messages **app-first, with SMS fallback**, track delivery, and verify
webhooks. Sync and asyncio clients, fully typed, one dependency (`httpx`).

```sh
pip install notifybd
```

Requires Python 3.9+.

## Send a message

```python
from notifybd import Notify

notify = Notify(api_key="nk_live_...")  # or set NOTIFY_API_KEY

accepted = notify.messages.send(
    to="01712345678",  # any common Bangladeshi format
    body="Your code is 123456",
    message_type="otp",  # "otp" or "transactional"
    sender_id="MyShop",  # one of your approved sender IDs
    reference="order-1234",  # optional, returned on the message
)
print(accepted.id, accepted.status)  # status == "accepted"
```

The call returns when the message is accepted (HTTP 202). Delivery is asynchronous: follow it with
a [webhook](#webhooks) or by polling.

```python
message = notify.messages.get(accepted.id)
print(message.status, message.channel)  # e.g. "delivered", "app"

for event in notify.messages.delivery_events(accepted.id).data:
    print(event.event_type, event.provider, event.status)
```

### asyncio

```python
from notifybd import AsyncNotify

async with AsyncNotify() as notify:
    await notify.messages.send(
        to="01712345678", body="Your code is 123456", message_type="otp", sender_id="MyShop"
    )
    async for message in notify.messages.list_all():
        ...
```

### Listing

```python
page = notify.messages.list(limit=50)  # newest first
page = notify.messages.list(cursor=page.next_cursor)

for message in notify.messages.list_all():  # follows cursors for you
    ...
```

## Retries and idempotency

Every `send` carries an `Idempotency-Key` — yours (`idempotency_key="order-42-otp"`) or a generated
one. The SDK retries network errors, `429` and `5xx` (default 2 retries, exponential backoff,
`Retry-After` honoured) and reuses the same key, so **a message is never sent twice**. Reusing a
key with a different body raises `ConflictError`.

## Errors

```python
from notifybd import RateLimitError, UnprocessableError, APIError

try:
    notify.messages.send(...)
except UnprocessableError as error:  # 422: marketing content, unapproved sender ID or link domain
    print(error.code, error.message)
except RateLimitError as error:  # 429
    print("retry in", error.retry_after)
except APIError as error:  # any other API error
    print(error.status, error.code, error.request_id)  # quote request_id to support
```

| Class | Status |
| --- | --- |
| `ValidationError` | 400 (see `.details`) |
| `AuthenticationError` | 401 |
| `InsufficientCreditsError` | 402 |
| `PermissionDeniedError` | 403 |
| `NotFoundError` | 404 |
| `ConflictError` | 409 |
| `UnprocessableError` | 422 |
| `RateLimitError` | 429 |
| `ServerError` | 5xx |
| `APIConnectionError` / `APITimeoutError` | no response |

All derive from `NotifyError`.

## Webhooks

Notify POSTs a signed JSON body when a message reaches a final status. Verify it with the **raw**
request body — not a re-serialised one:

```python
from notifybd import SignatureVerificationError, construct_event


# Flask
@app.post("/webhooks/notify")
def webhook():
    try:
        event = construct_event(
            request.get_data(),  # raw bytes
            request.headers.get("X-Notify-Signature"),
            os.environ["NOTIFY_WEBHOOK_SECRET"],  # shown once when you create the endpoint
        )
    except SignatureVerificationError:
        return "", 400

    if event.event_name == "message.failed":
        print(event.payload.message_id, event.payload.reason)
    return "", 204
```

Events are delivered **at least once**: dedupe on `event.event_id`. Timestamps older than 5 minutes
are rejected (`tolerance=` to change). During secret rotation the header may carry two signatures;
either one is accepted.

## Configuration

| Argument | Default |
| --- | --- |
| `api_key` | `NOTIFY_API_KEY` |
| `base_url` | `NOTIFY_BASE_URL`, then the production API |
| `timeout` | `30` seconds |
| `max_retries` | `2` |
| `http_client` | a new `httpx.Client` (pass your own for proxies, mTLS, ...) |
| `app_info` | `{"name": "my-app", "version": "1.0"}`, added to the User-Agent |

## Development

```sh
python -m venv .venv && .venv/bin/pip install -e . pytest pytest-asyncio mypy ruff
.venv/bin/pytest && .venv/bin/mypy && .venv/bin/ruff check .
```

The webhook tests load `spec/fixtures/webhook-signature.json`, shared with every other SDK.

MIT licensed.
