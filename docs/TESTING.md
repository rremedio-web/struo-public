# Testing

## Harness map

| File | What it covers | How to run | Needs WordPress or network? | In CI? |
| --- | --- | --- | --- | --- |
| `tests/smoke.sh` | PHP lint, structural greps over the source, docs and `ci.yml`, the four pure-PHP case files, the release zip build and its contents | `bash tests/smoke.sh` | No | Yes, every PHP version |
| `tests/provider-url-cases.php` | Provider URL guard: HTTPS, host allowlist, private and loopback addresses, redirect locations | `php tests/provider-url-cases.php` | No (injected resolver, no DNS) | Yes, through smoke |
| `tests/rate-limit-cases.php` | Atomic rate limiter: interleaved increments never pass the cap | `php tests/rate-limit-cases.php` | No | Yes, through smoke |
| `tests/plugin-entry-deactivate-cases.php` | Load-time floor guard (PHP and WordPress): deactivates the plugin file that was actually loaded | `php tests/plugin-entry-deactivate-cases.php` | No | Yes, through smoke |
| `tests/durable-plan-store-cases.php` | Durable plan store: create/load, expiry, prune, 25-plan capacity, family cancel | `php tests/durable-plan-store-cases.php` | No | Yes, through smoke |
| `tests/autodetect-cases.mjs` | Console target auto-detection (pure functions pulled out of `assets/console.js`) | `node tests/autodetect-cases.mjs` | No | Yes |
| `tests/console-build-cases.mjs` | Authored console sources exist and the built `assets/console.js` / `console.css` have the expected shape | `node tests/console-build-cases.mjs` (part of `npm test`) | No | Yes |
| `tests/action-pins.sh` | Every `uses:` line is a 40-hex commit SHA; `--resolve` checks each SHA exists on GitHub | `bash tests/action-pins.sh --format`, `--resolve` | `--resolve` needs network | Yes |
| `tests/gitleaks.sh` | Secret scan of git history, the working tree and the unpacked release zip, with a SHA-pinned gitleaks | `bash tests/gitleaks.sh --history` | Network (downloads gitleaks) | Yes |
| `tests/export-public-cases.sh` | Safety of `bin/export-public.sh`: refuses an existing destination, leak scan fails closed | `bash tests/export-public-cases.sh` | No | Yes, through smoke |
| `tests/rest-probe.sh` | Live REST probe: route list, 401 before 403 for anonymous writes, plan-only dry runs | `bash tests/rest-probe.sh <site-url>` | A running site | Syntax check only (`bash -n`); run it by hand |
| `tests/wp-eval-lite.php` | Short live walk without ACF or an AI provider: tables, `/plan` permission, MCP dispatch forces dry runs, prompt guards fail before any provider HTTP | `wp eval-file tests/wp-eval-lite.php --user=admin` | WordPress | Yes, wp-env 6.9 and 7.1 |
| `tests/wp-eval-cases.php` | The full runtime contract: REST, MCP and Abilities doors, durable plans, journal and recovery, privacy, bundles, rename migrations, uninstall | `wp eval-file tests/wp-eval-cases.php --user=admin` | WordPress | Yes, wp-env 6.9 and 7.1 |

`composer test` runs the four pure-PHP case files. `npm run lint` runs ESLint and Prettier on the extracted `assets/src` modules and `node --check` on `app.js` / `assets/console.js`; `npm test` runs the two `.mjs` files.

CI runs the PHP 8.2–8.5 matrix with smoke and PHPCS (blocking on `includes/`, `src/`, `provider-url-guard.php` and `uninstall.php`; report-only on `struo.php`), plus the lite and full evals on WordPress 6.9 and 7.1 via wp-env (plugin only, no ACF). The release zip job needs all of them green.

**smoke.sh pins text in other files.** It greps `.github/workflows/ci.yml` (step names, pins, matrix), eval case labels, README/readme.txt sentences and `composer.json`. When you change one of those, run smoke in the same change and update the matching check.

## Test IDs

Eval and smoke labels carry an ID in parentheses. `-eN` is an eval case, `-smN` a smoke check; IDs without a suffix are older eval groups.

| Prefix | Area |
| --- | --- |
| `allow` | Block and post allowlists refuse: no plan, no write |
| `s0a`, `s0b` | Disclosure and copy; plan lifecycle and storage honesty |
| `s1` | Authorization: one path for REST, MCP and Abilities |
| `s2` | Immutable mutation journal |
| `s3` | Verified, recoverable apply (including the crash-injection cases) |
| `s4` | The one Work Queue |
| `s45` | Durable discovery list |
| `s5` | Block-editor (Gutenberg) host; manual browser proofs |
| `s9ro` | Read-only Findings (Search Console / Analytics) |
| `dps` | Durable plan store |
| `ptc` | Per-target compile in bundles |
| `door` | Which entry point ("door") an apply came through |
| `f10`, `f12`, `f13` | Dynamic allowlist cache; create-page needs an approved plan; capability migration |
| `p*` | Privacy: retention, cron, excerpt redaction, export and erase |
| `b*` | Multi-page bundles |
| `r*` | Rename migrations and uninstall |
| `c*` | MCP dispatch |
| `i*` | Agent plan inbox |
| `ps*` | `page_spec_v1` create-page plans |
| `xf*` | Cross-field (excerpt) edits |

## What the full eval is for

The full eval is the contract: do not weaken a named assertion. For example, privacy export must paginate table and option-buffer rows without duplicates (`p4b`), a bundle whose planner is unavailable must skip cleanly (`ptc-e4`), and create-page plans must show up in discovery as previews with no serialized content (`s45-e3`, `s45-e4`).

It is a live WordPress walk of REST, privacy export, planner fallback and discovery payloads. wp-env can fail a case that LocalWP passes (one admin user, WP AI Client present, plan capacity). The fixtures in `tests/wp-eval-cases.php` isolate users, disable the Client adapter for planner-fallback cases, and free plan headroom. Keep those fixtures; do not skip the assertions.

Both eval files end with `exit()`, non-zero when any check failed (`print_summary_and_exit()` in the full eval).

TODO: `yoast/phpunit-polyfills` is still a dev dependency although there are no PHPUnit tests. Removing it means regenerating `composer.lock`.
