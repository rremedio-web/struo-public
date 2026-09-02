# Contributing

This is an experimental WordPress plugin. Small, reviewable patches are welcome.

1. Keep apply person-only. Do not add an agent Apply surface or an MCP-public apply tool.
2. Do not weaken eval assertions in `tests/wp-eval-cases.php`.
3. Run `bash tests/smoke.sh`, `npm run lint`, and `npm test` before sending a change.
4. PHPCS is blocking on `includes/` and `src/`. `struo.php` is still report-only. ESLint and Prettier cover extracted `assets/src` modules; `app.js` is syntax-checked only.
5. Do not commit `.env`, wp-config secrets, or private hostnames.

Public releases are tagged on a clean-history clone (`bash bin/export-public.sh`), not by rewriting private `origin`. The exporter refuses an existing destination.
