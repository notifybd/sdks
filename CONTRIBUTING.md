# Contributing

Thanks for helping. Please open an issue before a large change.

## Ground rules

* **Keep the SDKs consistent.** A behaviour change in one SDK (retries, errors, idempotency, webhook
  verification) belongs in all four, or in an issue explaining why not. See
  [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
* **Shared vectors first.** If you change webhook verification, add the case to
  `spec/fixtures/webhook-signature.json`; every suite must pass it.
* **Zero or minimal dependencies.** Don't add a runtime dependency without discussing it.
* **Never log secrets or message content** (API keys, webhook secrets, recipient numbers, bodies).
* **Tests and docs ship with the change.** Update the package README and `CHANGELOG.md` (*Unreleased*).

## Per package

| Package | Setup | Check |
| --- | --- | --- |
| node | `cd packages/node && npm ci` | `npm run typecheck && npm test && npm run build` |
| python | `cd packages/python && python -m venv .venv && .venv/bin/pip install -e '.[dev]'` | `pytest && mypy && ruff check .` |
| php | `cd packages/php && composer install` | `composer test && composer stan && composer cs` |
| wordpress | `cd packages/wordpress && composer install` | `composer test && composer lint` |

Commit messages follow Conventional Commits (`feat(node): ...`, `fix(php): ...`).

## Reporting security issues

Do not open a public issue. See [SECURITY.md](SECURITY.md).
