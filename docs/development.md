# Development

## Layout

- `struo.php` — WordPress entry and remaining god class (~22k lines). Extraction is incomplete.
- `src/` — three `Struo\` classes: the REST route registrar, the OpenAI-compatible provider HTTP call, and the privacy exporter/eraser facade (see [architecture.md](architecture.md#src-classes-and-their-callers)).
- Autoloading: at runtime `Struo\` classes load through the small `spl_autoload_register` closure near the top of `struo.php`; it maps `Struo\X\Y` to `src/X/Y.php` and silently skips missing files. The PSR-4 block in `composer.json` is for development tools only, and `vendor/` is never shipped.
- `includes/` — durable plans, authority, abilities, findings, mutation journal
- `assets/src/js` and `assets/src/css` — authored Mission Brief console (`app.js` is still the large remaining module)
- `assets/console.js` / `assets/console.css` — built console (shipped)
- `src/work-queue` and `src/gutenberg-host` — wp-scripts islands (source not shipped)

## Commands

```bash
composer install
composer lint          # PHPCS on includes/, src/, provider-url-guard.php, uninstall.php (blocking)
composer test          # pure-PHP cases (provider URL, rate limit, deactivate, plan store)
bash tests/smoke.sh    # static harness, including release zip
npm install
npm run build          # Work Queue + Gutenberg host + console concat
npm run lint           # ESLint + Prettier on extracted assets/src modules; syntax check on app.js
npm test
```

## Release zip

`bash bin/build-zip.sh` writes `dist/struo-<version>.zip`, with the version taken from the plugin header (for example `dist/struo-0.3.1.zip`).

The zip contains `struo.php`, `includes/`, the PHP classes under `src/` (not `src/work-queue` or `src/gutenberg-host`), `block-manifest.json`, `README.md`, `CHANGELOG.md`, `readme.txt`, `LICENSE`, `provider-url-guard.php`, `uninstall.php`, and the built `assets/` without `assets/src`. The file list is an explicit allowlist in the script, and the script fails if a listed file is missing.

The build is deterministic: sorted entries, no extra zip fields, and mtimes fixed to the HEAD commit date. Without `.git`, set `SOURCE_DATE_EPOCH` (unix seconds). CI builds the zip, writes a `.sha256` file and scans the unpacked zip with gitleaks.

## Public clone

Private git history is not the public artifact. After the tree is clean:

```bash
STRUO_LEAK_PATTERNS_FILE=~/.config/struo/leak-patterns \
  bash bin/export-public.sh ../struo-public
```

`STRUO_LEAK_PATTERNS_FILE` points at an untracked, local-only file of names that must never reach the public tree (client, employer or site names, internal hostnames, private post IDs). One extended regex per line; blank lines and `#` comments are ignored; matching is case-insensitive. Keep the file outside the repository so the public tree never contains the words it guards against. The export stops and lists the matching files if any pattern hits. Without the variable the export refuses to run; `STRUO_SKIP_LEAK_SCAN=1` skips the scan explicitly.

The destination must not already exist. The script stages into a temporary sibling directory, leak-checks there, then renames into place and tags `v<version>` from the plugin header. It does not rewrite this repository or push `origin`.
