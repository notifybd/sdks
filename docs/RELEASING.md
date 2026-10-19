# Releasing

Releases are tag-driven. Pushing a tag runs the matching workflow in `.github/workflows/`.

| Tag | Publishes to | Workflow |
| --- | --- | --- |
| `node-vX.Y.Z` | npm (`@notifybd/sdk`, with provenance) | `release-node.yml` |
| `python-vX.Y.Z` | PyPI (`notifybd`, trusted publishing) | `release-python.yml` |
| `php-vX.Y.Z` | mirror repo `notifybd/notify-php` → Packagist | `release-php.yml` |
| `wordpress-vX.Y.Z` | GitHub release zip, then WordPress.org SVN | `release-wordpress.yml` |

## Every release

1. Update the version in the package: `package.json` + `src/version.ts` (node),
   `src/notifybd/_version.py` (python), `src/Version.php` (php), `notify-bd.php` header +
   `NOTIFY_BD_VERSION` + `readme.txt` *Stable tag* (wordpress).
2. Move the *Unreleased* entries in the package `CHANGELOG.md` under the new version.
3. Open a pull request; CI must be green.
4. After merge: `git tag node-v1.2.0 && git push origin node-v1.2.0`.

`scripts/check-version.mjs` runs first in each release workflow and fails if the tag and the files
disagree, so a mismatched tag never publishes.

## One-time setup

### Placeholders to replace before the first release

* **API base URL.** `https://api.notify.example/v1` appears as a default in `packages/node/src/client.ts`,
  `packages/python/src/notifybd/_http.py`, `packages/php/src/Notify.php` and
  `packages/wordpress/notify-bd/includes/class-notify-bd-options.php`. Set the real production URL.
  `scripts/check-placeholders.sh` (run by every release workflow) fails while `notify.example` remains.
* **Names.** The npm scope `@notifybd`, the Composer vendor `notifybd`, the PyPI name `notifybd`, the
  GitHub organisation `notifybd` and the WordPress slug `notify-bd` are working names. Confirm the
  names are available and owned by you, then search-and-replace them.
* **WordPress readme.** Update *Tested up to* to the current WordPress release.

### Registry credentials (GitHub → Settings → Secrets and variables → Actions)

* **npm**: `NPM_TOKEN` (automation token), or configure npm trusted publishing and drop it.
* **PyPI**: add a *trusted publisher* for this repository and the `release-python.yml` workflow on
  pypi.org; no token needed.
* **PHP mirror**: create the empty repo `notifybd/notify-php`, add a write deploy key, store the
  private key as `PHP_MIRROR_DEPLOY_KEY`. Submit `notifybd/notify-php` to packagist.org once, and
  enable its GitHub hook so tags are picked up automatically.
* <a name="wordpress"></a>**WordPress.org**: submit the plugin for review (zip from `release-wordpress.yml`). Once
  approved, store `SVN_USERNAME` and `SVN_PASSWORD`. The workflow then deploys `trunk` and the tag.
  Plugin review checks: GPL licence, escaped output, nonces and capability checks, no calls home
  except the documented *External services* section in `readme.txt`.

## Spec drift

When the platform API changes, run the `spec-sync` workflow (or `scripts/sync-spec.sh`). Review the
diff of `spec/openapi.json`; update the SDKs and shared fixtures in the same pull request.
