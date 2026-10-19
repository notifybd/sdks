# Notify for WordPress

A WordPress plugin for [Notify](../../README.md): send OTP and transactional messages (app first,
SMS fallback) from WordPress and receive signed delivery-status webhooks.

* Plugin directory: `notify-bd/` (this folder is what gets zipped and shipped)
* Requires WordPress 6.0+ and PHP 7.4+
* Licence: GPL-2.0-or-later (WordPress.org requires it; the other SDKs here are MIT)

## What it does (v1)

| Feature | Where |
| --- | --- |
| `notify_bd_send( $to, $body, $args )` for themes and plugins | `includes/functions.php` |
| Settings → Notify: API key, default sender ID, webhook secret, test message | `includes/class-notify-bd-settings.php` |
| Signed webhook endpoint `POST /wp-json/notify-bd/v1/webhook` | `includes/class-notify-bd-webhook.php` |
| Hooks: `notify_bd_send_params`, `notify_bd_message_accepted`, `notify_bd_send_failed`, `notify_bd_message_status`, `notify_bd_message_delivered`, `notify_bd_message_failed` | |
| Secrets via `NOTIFY_BD_API_KEY` / `NOTIFY_BD_WEBHOOK_SECRET` in `wp-config.php` | `includes/class-notify-bd-options.php` |

Out of scope for v1: WooCommerce order messages and login/checkout OTP.

## Design notes

* No Composer dependencies. The plugin carries its own small client over the WordPress HTTP API, so
  it honours the site's proxy/SSL settings and cannot conflict with another plugin's vendored
  libraries. It follows the same protocol as the other SDKs (idempotency key on every send, retry of
  network errors / 429 / 5xx with the same key).
* The webhook route is public in WordPress terms (`permission_callback` is `__return_true`); the
  HMAC signature is the authentication. Events are de-duplicated by `eventId` for 24 hours.
* Signature verification lives in a pure-PHP class so it is tested against the vectors shared by
  every SDK (`spec/fixtures/webhook-signature.json`).

## Development

```sh
composer install
composer test    # shared webhook vectors, no WordPress needed
composer lint    # WordPress coding standards
```

To try it in a site, symlink `notify-bd/` into `wp-content/plugins/`.

## Releasing to WordPress.org

See [`docs/RELEASING.md`](../../docs/RELEASING.md#wordpress).
