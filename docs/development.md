# Development

## Layout

- `struo.php` — WordPress entry and remaining god class (~22k lines). Extraction is incomplete.
- `src/` — PSR-4 `Struo\` seams (REST registrar, planning facade, provider HTTP, audit, security). Several classes still delegate into `Struo_Block_Editor`.
- `includes/` — durable plans, authority, abilities, findings, mutation journal
- `assets/src/js` and `assets/src/css` — authored Mission Brief console (`app.js` is still the large remaining module)
- `assets/console.js` / `assets/console.css` — built console (shipped)
- `src/work-queue` and `src/gutenberg-host` — wp-scripts islands (source not shipped)

## Commands

```bash
composer install
composer lint          # PHPCS on includes/ + src/ (blocking)
composer test          # pure-PHP cases (provider URL, rate limit, deactivate)
bash tests/smoke.sh    # static harness, including release zip
npm install
npm run build          # Work Queue + Gutenberg host + console concat
npm run lint           # ESLint + Prettier on extracted assets/src modules; syntax check on app.js
npm test
```

`bin/build-zip.sh` stages the shippable tree. It copies PHP under `src/` and built `assets/`, and drops `assets/src`. Without `.git`, set `SOURCE_DATE_EPOCH` (unix seconds) so mtimes stay deterministic.

## Public clone

Private git history is not the public artifact. After the tree is clean:

```bash
bash bin/export-public.sh ../struo-public
```

The destination must not already exist. The script stages into a temporary sibling directory, leak-checks there, then renames into place and tags `v0.3.0`. It does not rewrite this repository or push `origin`.
