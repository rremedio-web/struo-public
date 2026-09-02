# Security model

Struo is allowlist-first. A post is not editable until an administrator puts it on the allowlist (or a create flow adds it after apply).

## Auth and capability

REST permission callbacks authorize through `Struo_Authority`. Anonymous callers get 401 before allowlist 403. Plan / approve / apply use `struo_plan`, `struo_approve`, and `struo_apply`.

## Provider URL

`provider-url-guard.php` (wrapped by `Struo\Security\ProviderUrlPolicy`) requires HTTPS, an allowlisted host, and a public resolution. Loopback HTTP is only for localhost when `STRUO_ALLOW_INSECURE_PROVIDER_URL` is set. Redirects are not followed (`redirection => 0`); a Location header is a new destination and is validated the same way. The bearer key is never sent across a hop.

## API keys

Prefer `STRUO_OPENAI_API_KEY` in wp-config.php. A database copy is plaintext. Settings never echo the key. Empty submit keeps the saved value. Removing it takes an explicit checkbox. Audit rows do not store keys, prompts, or provider bodies.

## Timeouts and rate limits

The HTTP planner hop times out at 45 seconds. Write and plan rate limits use atomic counters (object cache or options CAS).

## Privacy

WordPress personal-data export/erase for audit and plan rows is scoped to the requested user. Option-store leftovers are filtered by `user_id`.

## Uninstall leftovers

`uninstall.php` deletes leftover `subsurface_ai_*` options and `sae_*` transients if they still exist. That is not a runtime compatibility layer. The public plugin never reads those keys. The deletes exist so an older private install that later used this zip does not leave secrets behind. Fresh installs have nothing matching those names.

