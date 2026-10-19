# @notifybd/sdk — Node.js SDK for Notify

Send OTP and transactional messages **app-first, with SMS fallback**, track delivery, and verify
webhooks. TypeScript types included, **zero runtime dependencies**, ESM and CommonJS.

```sh
npm install @notifybd/sdk
```

Requires Node.js 18+ (uses the global `fetch`).

## Send a message

```ts
import { Notify } from "@notifybd/sdk";

const notify = new Notify({ apiKey: process.env.NOTIFY_API_KEY }); // or just set NOTIFY_API_KEY

const accepted = await notify.messages.send({
  to: "01712345678", // any common Bangladeshi format
  body: "Your code is 123456",
  messageType: "otp", // "otp" | "transactional"
  senderId: "MyShop", // one of your approved sender IDs
  reference: "order-1234", // optional, returned on the message
});
console.log(accepted.id, accepted.status); // status === "accepted"
```

The call returns when the message is accepted (HTTP 202). Delivery is asynchronous: follow it with a
[webhook](#webhooks) or by polling.

```ts
const message = await notify.messages.get(accepted.id);
console.log(message.status, message.channel); // e.g. "delivered", "app"

const { data } = await notify.messages.deliveryEvents(accepted.id);
for (const event of data) console.log(event.eventType, event.provider, event.status);
```

### Listing

```ts
const page = await notify.messages.list({ limit: 50 }); // newest first
const next = await notify.messages.list({ cursor: page.nextCursor! });

for await (const message of notify.messages.listAll()) {
  // follows cursors for you
}
```

## Retries and idempotency

Every `send` carries an `Idempotency-Key` — yours (`{ idempotencyKey: "order-42-otp" }`) or a
generated one. The SDK retries network errors, `429` and `5xx` (default 2 retries, exponential
backoff with jitter, `Retry-After` honoured) and reuses the same key, so **a message is never sent
twice**. Reusing a key with a different body is a `ConflictError`.

## Errors

```ts
import { RateLimitError, UnprocessableError, ApiError } from "@notifybd/sdk";

try {
  await notify.messages.send(params);
} catch (error) {
  if (error instanceof UnprocessableError) {
    // 422: marketing content, unapproved sender ID or link domain
    console.error(error.code, error.message);
  } else if (error instanceof RateLimitError) {
    console.error("retry in", error.retryAfterSeconds);
  } else if (error instanceof ApiError) {
    console.error(error.status, error.code, error.requestId); // quote requestId to support
  } else throw error;
}
```

| Class | Status |
| --- | --- |
| `ValidationError` | 400 (see `.details`) |
| `AuthenticationError` | 401 |
| `InsufficientCreditsError` | 402 |
| `PermissionError` | 403 |
| `NotFoundError` | 404 |
| `ConflictError` | 409 |
| `UnprocessableError` | 422 |
| `RateLimitError` | 429 |
| `ServerError` | 5xx |
| `ApiConnectionError` / `ApiTimeoutError` | no response |

All derive from `NotifyError`.

## Webhooks

Notify POSTs a signed JSON body when a message reaches a final status. Verify it with the **raw**
request body — not a parsed-and-re-serialised one:

```ts
import express from "express";
import { constructEvent, SignatureVerificationError } from "@notifybd/sdk";

const app = express();

app.post("/webhooks/notify", express.raw({ type: "application/json" }), (req, res) => {
  try {
    const event = constructEvent(
      req.body, // Buffer
      req.header("x-notify-signature"),
      process.env.NOTIFY_WEBHOOK_SECRET!, // shown once when you create the endpoint
    );
    if (event.eventName === "message.failed") console.log(event.payload.messageId, event.payload.reason);
    res.sendStatus(204);
  } catch (error) {
    if (error instanceof SignatureVerificationError) return res.sendStatus(400);
    throw error;
  }
});
```

Events are delivered **at least once**: dedupe on `event.eventId`. Timestamps older than five minutes
are rejected (`{ toleranceSeconds }` to change). During secret rotation the header may carry two
signatures; either one is accepted.

## Configuration

| Option | Default |
| --- | --- |
| `apiKey` | `NOTIFY_API_KEY` |
| `baseUrl` | `NOTIFY_BASE_URL`, then the production API |
| `timeoutMs` | `30000` |
| `maxRetries` | `2` |
| `fetch` | the global `fetch` (pass your own for proxies or instrumentation) |
| `appInfo` | `{ name, version }`, added to the User-Agent |

Per-request `{ signal, timeoutMs, maxRetries }` overrides are accepted as the last argument.

## Development

```sh
npm ci && npm run typecheck && npm test && npm run build
```

The webhook tests load `spec/fixtures/webhook-signature.json`, shared with every other SDK.

MIT licensed.
