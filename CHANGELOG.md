# Changelog

## 0.3.0

Experimental public edition of Struo: plan → preview → approve → apply for
allowlisted Gutenberg and ACF changes. Agents may plan. Only a logged-in
person at the site applies.

- Fail-closed page and block allowlists, durable plans, human approval,
  audit records, and provider protections (HTTPS host allowlist, no
  redirects, 45s timeout). API keys prefer `STRUO_OPENAI_API_KEY` in
  wp-config; a database copy is plaintext and is never echoed.
- Mission Brief in the block editor, one Work Queue, and S9-ro: read-only Findings.
- Durable plan store: one writer for plan moves.
- one compile for one page and a bundle child.
- Direct REST persist is `sae_plan_apply_required`. Apply is `plan_id` →
  approve → apply. Request `planner_options` are ignored.
- WordPress 6.9 / 7.1 eval in CI, PHP 8.2–8.5, gitleaks, Composer audit,
  PHPCS on `includes/` and `src/`, deterministic release zip.
- `src/` and `assets/src/` are an incomplete extraction. `struo.php` and
  `assets/src/js/app.js` still hold most of the runtime.

Lane-by-lane reviewer chronology is not part of this public tree.
