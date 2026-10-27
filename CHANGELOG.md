# Changelog

## 0.3.1

- Fix: an excerpt containing a bare `&` was saved (kses stores it as
  `&amp;`) but the plan was marked failed and could not be rolled back.
  The expected-bytes hash is now taken after the same filtering the write
  applies. Cross-field writes are slashed, so a backslash survives.
- Performance: storage migrations and schema checks no longer run on every
  request. An autoloaded `struo_db_version` option gates them; they run on
  activation or a version change. Durable plans are pruned by the existing
  daily cron and before each plan is created.
- Uninstall no longer deletes option names from an earlier internal build.
- `bin/export-public.sh` runs a fail-closed leak scan with patterns from a
  local, untracked file (`STRUO_LEAK_PATTERNS_FILE`).
- CI: the Gitleaks history scan now fetches the full history.
- Plugin header names the author and links the public repository. Docs use
  plain English instead of internal tracker codes.

## 0.3.0

Experimental public edition of Struo: plan → preview → approve → apply for
allowlisted Gutenberg and ACF changes. Agents may plan. Only a logged-in
person at the site applies.

- Fail-closed page and block allowlists, durable plans, human approval,
  audit records, and provider protections (HTTPS host allowlist, no
  redirects, 45s timeout). API keys prefer `STRUO_OPENAI_API_KEY` in
  wp-config; a database copy is plaintext and is never echoed.
- Mission Brief in the block editor, one Work Queue, and read-only Findings
  from Search Console and Analytics.
- Durable plan store: one writer for plan moves.
- One compile path for a single page and for each child of a multi-page bundle.
- Direct REST persist is `sae_plan_apply_required`. Apply is `plan_id` →
  approve → apply. Request `planner_options` are ignored.
- WordPress 6.9 / 7.1 eval in CI, PHP 8.2–8.5, gitleaks, Composer audit,
  PHPCS on `includes/` and `src/`, deterministic release zip.
- `src/` and `assets/src/` are an incomplete extraction. `struo.php` and
  `assets/src/js/app.js` still hold most of the runtime.

The internal development history is not part of this public tree.
