# notifybd/notify-php

Official PHP SDK for **Notify**: send OTP and transactional messages **app-first, with SMS
fallback**, track delivery, and verify webhooks. Typed, no runtime dependencies beyond `ext-curl`.

```sh
composer require notifybd/notify-php
```

Requires PHP 8.1+.

## Send a message

```php
use NotifyBd\Notify;

$notify = new Notify('nk_live_...');   // or set the NOTIFY_API_KEY environment variable

$accepted = $notify->messages->send([
    'to'          => '01712345678',      // any common Bangladeshi format
    'body'        => 'Your code is 123456',
    'messageType' => 'otp',              // 'otp' or 'transactional'
    'senderId'    => 'MyShop',           // one of your approved sender IDs
    'reference'   => 'order-1234',       // optional, returned on the message
]);

echo $accepted->id, ' ', $accepted->status;   // status === 'accepted'
```

The call returns when the message is accepted (HTTP 202). Delivery is asynchronous: follow it with
a [webhook](#webhooks) or by polling.

```php
$message = $notify->messages->get($accepted->id);
echo $message->status, ' ', $message->channel;     // e.g. "delivered app"

foreach ($notify->messages->deliveryEvents($accepted->id)->data as $event) {
    echo $event->eventType, ' ', $event->provider, ' ', $event->status, PHP_EOL;
}
```

### Listing

```php
$page = $notify->messages->list(limit: 50);                  // newest first
$page = $notify->messages->list(cursor: $page->nextCursor);

foreach ($notify->messages->listAll() as $message) {          // follows cursors for you
    // ...
}
```

## Retries and idempotency

Every `send()` carries an `Idempotency-Key` — yours (`send($params, 'order-42-otp')`) or a generated
one. The SDK retries network errors, `429` and `5xx` (default 2 retries, exponential backoff,
`Retry-After` honoured) and reuses the same key, so **a message is never sent twice**. Reusing a key
with a different body throws `ConflictException`.

## Errors

```php
use NotifyBd\Exception\{ApiException, RateLimitException, UnprocessableException};

try {
    $notify->messages->send($params);
} catch (UnprocessableException $e) {   // 422: marketing content, unapproved sender ID or link domain
    echo $e->errorCode, ': ', $e->getMessage();
} catch (RateLimitException $e) {       // 429
    echo 'retry in ', $e->retryAfter;
} catch (ApiException $e) {             // any other API error
    echo $e->status, ' ', $e->errorCode, ' ', $e->requestId;   // quote requestId to support
}
```

| Exception | Status |
| --- | --- |
| `ValidationException` | 400 (see `->details`) |
| `AuthenticationException` | 401 |
| `InsufficientCreditsException` | 402 |
| `PermissionDeniedException` | 403 |
| `NotFoundException` | 404 |
| `ConflictException` | 409 |
| `UnprocessableException` | 422 |
| `RateLimitException` | 429 |
| `ServerException` | 5xx |
| `ApiConnectionException` | no response |

All derive from `NotifyBd\Exception\NotifyException`.

## Webhooks

Notify POSTs a signed JSON body when a message reaches a final status. Verify it with the **raw**
request body — not a re-encoded one:

```php
use NotifyBd\Webhook;
use NotifyBd\Exception\SignatureVerificationException;

try {
    $event = Webhook::constructEvent(
        file_get_contents('php://input'),
        $_SERVER['HTTP_X_NOTIFY_SIGNATURE'] ?? null,
        getenv('NOTIFY_WEBHOOK_SECRET'),       // shown once when you create the endpoint
    );
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

if ($event->eventName === 'message.failed') {
    error_log($event->messageId . ' failed: ' . $event->reason);
}
http_response_code(204);
```

Events are delivered **at least once**: dedupe on `$event->eventId`. Timestamps older than five
minutes are rejected (pass a `$tolerance` to change). During secret rotation the header may carry
two signatures; either one is accepted.

## Configuration

```php
$notify = new Notify([
    'apiKey'     => 'nk_live_...',          // default: NOTIFY_API_KEY
    'baseUrl'    => 'https://...',          // default: NOTIFY_BASE_URL, then the production API
    'timeout'    => 30.0,                   // seconds
    'maxRetries' => 2,
    'transport'  => new MyTransport(),      // implement NotifyBd\Http\Transport (PSR-18 adapters, proxies)
    'appInfo'    => ['name' => 'my-app', 'version' => '1.0'],   // added to the User-Agent
]);
```

## Development

```sh
composer install
composer test    # PHPUnit
composer stan    # PHPStan
composer cs      # php-cs-fixer (dry run)
```

The webhook tests load `spec/fixtures/webhook-signature.json`, shared with every other SDK.

MIT licensed.
