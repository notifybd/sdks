# Architecture

## One repository, four packages

The four SDKs live together in this public repository, separate from the (private) Notify platform
repository.

**Why not inside the platform repo.** The platform repo holds backend services, infrastructure and
operational history that must stay private. Customers need to read, star, file issues against and
send pull requests to the SDKs. SDKs also release on their own cadence: an SDK fix should not wait
for, or be coupled to, a platform deploy.

**Why not four repositories.** The SDKs share one contract and must behave identically on the same
inputs. One repository lets a single pull request change the contract fixtures and all four
implementations together, with one CI run proving they still agree, and one place for issues and
docs. Four repositories would mean four copies of fixtures and CI to keep in step by hand.

**What still needs its own repository.** Two registries cannot publish from a subdirectory:

* **Packagist** needs `composer.json` at the root of a git repository. `packages/php` is therefore
  mirrored, read-only, to `notifybd/notify-php` on every `php-v*` tag (see RELEASING.md). Packagist
  watches the mirror. Nobody edits the mirror.
* **WordPress.org** distributes plugins from its own SVN repository. The release workflow pushes
  the built plugin there; this repository stays the source of truth.

npm and PyPI publish straight from their subdirectories.

## Keeping the SDKs honest

1. **Contract.** `spec/openapi.json` is a copy of the platform's generated OpenAPI document (the
   platform generates it from its Zod schemas; nobody edits it by hand). `scripts/sync-spec.sh`
   refreshes it, and the `spec-sync` workflow does so on demand and opens a pull request. A diff in
   that file is the signal that an SDK may need a change.
2. **Shared vectors.** `spec/fixtures/webhook-signature.json` holds signed payloads and expected
   outcomes (valid, replay, tampered, rotated secret, malformed). Every SDK's test suite, including
   the WordPress plugin's verifier, runs all of them. Add a case once and four suites exercise it.
3. **Same behaviour, native idiom.** All SDKs: send `Authorization: Bearer <key>`, put an
   `Idempotency-Key` on every `POST /messages` (generated when not supplied) and reuse it across
   retries, retry network errors / 408 / 429 / 5xx with jittered exponential backoff honouring
   `Retry-After`, never retry a request that is unsafe without an idempotency key, map statuses to
   the same set of typed errors, and never log credentials or message bodies.

## Scope

Only what an API key can do: `messages:send`, `messages:read`, `senders:read`, `senders:write`
(send, get, list, events, delivery events; create and list sender IDs) plus webhook verification.
Account, portal, billing, SMS-gateway and mobile-realm endpoints are out of scope on purpose.

## Dependencies

* Node: none at runtime (global `fetch`, `node:crypto`). Node 18+.
* PHP: `ext-curl`, `ext-json`, `ext-hash`. A `Transport` interface lets callers plug in any HTTP
  client.
* Python: `httpx`. Python 3.9+.
* WordPress: none; it uses the WordPress HTTP API.

## Versioning

Each package is versioned independently with semantic versioning and tagged `<package>-v<semver>`
(`node-v1.2.0`, `php-v1.0.1`, `python-v0.4.0`, `wordpress-v1.0.0`). A change to the platform API
that is backwards compatible does not force a release. A breaking API change is versioned by the
platform (`/v2`) and gets a new major of each SDK.
