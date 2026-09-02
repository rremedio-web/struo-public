# Testing

## Layers

1. **Static smoke** — `bash tests/smoke.sh`  
   PHP lint, structural greps, provider-URL cases, rate-limit cases, zip contents. No WordPress required.

2. **Pure-PHP cases** — `composer test`  
   `tests/provider-url-cases.php`, `tests/rate-limit-cases.php`, `tests/plugin-entry-deactivate-cases.php`. Redirect hops and rejected hosts live in the provider-URL script.

3. **Console assets** — `npm run lint` and `npm test`  
   ESLint and Prettier on extracted `assets/src` modules, `node --check` on `app.js` / `assets/console.js`, plus `tests/console-build-cases.mjs`.

4. **WordPress eval** — inside a site, as an administrator:

   ```bash
   wp eval-file tests/wp-eval-lite.php --user=admin
   wp eval-file tests/wp-eval-cases.php --user=admin
   ```

   Lite has no ACF and no provider. Full eval is the contract: do not weaken named assertions (`(p4b)`, `(ptc-e4)`, `(s45-e3)`, `(s45-e4)` and the rest).

CI runs PHP matrix + smoke + PHPCS on `includes/` and `src/` + lite and full eval on WordPress 6.9 and 7.1 via wp-env (plugin-only, no ACF). The release zip job needs all of those green.

PHPUnit wrappers under `tests/phpunit/` are optional local helpers. They are not the CI gate.

## What eval is for

Eval is a live WordPress walk of REST, privacy export, planner fallback, and discovery payloads. wp-env can fail a case that LocalWP passes (one admin user, WP AI Client present, plan capacity). The fixtures in `tests/wp-eval-cases.php` isolate users, disable the Client adapter for planner-fallback cases, and free plan headroom. Keep those fixtures; do not skip the assertions.
