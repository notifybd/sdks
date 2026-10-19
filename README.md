# Notify SDKs

Official client libraries for [Notify](https://notify.example): send OTP and transactional messages
**app-first, with SMS fallback**, track delivery, and verify webhooks.

| Package | Install | Docs |
| --- | --- | --- |
| **Node.js / TypeScript** | `npm install @notifybd/sdk` | [packages/node](packages/node#readme) |
| **PHP** | `composer require notifybd/notify-php` | [packages/php](packages/php#readme) |
| **Python** | `pip install notifybd` | [packages/python](packages/python#readme) |
| **WordPress** | Plugins → *Notify for WordPress* | [packages/wordpress](packages/wordpress#readme) |

```ts
import { Notify } from "@notifybd/sdk";

const notify = new Notify({ apiKey: process.env.NOTIFY_API_KEY });
await notify.messages.send({
  to: "01712345678",
  body: "Your code is 123456",
  messageType: "otp",
  senderId: "MyShop",
});
```

Every SDK offers the same things, with the idioms of its language:

* **Send** a message and read it back, with its lifecycle and delivery events
* **Safe retries**: each send has an idempotency key, so network errors, `429` and `5xx` are retried
  without ever sending twice
* **Typed errors** carrying the API's code and request id
* **Webhook verification** (HMAC-SHA256, replay window, secret rotation)
* **Sender IDs**: request and list

They wrap the public, API-key surface only (`messages:*`, `senders:*`). Portal, billing and mobile
endpoints are intentionally not exposed.

## Repository

```
packages/node        @notifybd/sdk          TypeScript, zero dependencies, ESM + CJS
packages/php         notifybd/notify-php    PHP 8.1+, zero dependencies beyond ext-curl
packages/python      notifybd               Python 3.9+, sync + asyncio, httpx
packages/wordpress   notify-bd              WordPress 6.0+ plugin, PHP 7.4+
spec/openapi.json    the API contract the SDKs target (synced from the platform)
spec/fixtures/       test vectors shared by every SDK
docs/                architecture and release process
```

Why one repository, how versions and releases work: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) and
[docs/RELEASING.md](docs/RELEASING.md). Contributing: [CONTRIBUTING.md](CONTRIBUTING.md). Security
reports: [SECURITY.md](SECURITY.md).

## Licence

MIT, except the WordPress plugin, which is GPL-2.0-or-later as WordPress.org requires.
