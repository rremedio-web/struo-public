# Security model

Struo is allowlist-first. A post is not editable until an administrator puts it on the allowlist (or a create flow adds it after apply).

## Auth and capability

REST permission callbacks authorize through `Struo_Authority`. Anonymous callers get 401 before allowlist 403. Plan / approve / apply use `struo_plan`, `struo_approve`, and `struo_apply`.

## Provider URL

`provider-url-guard.php` requires HTTPS, an allowlisted host, and a public resolution. Loopback HTTP is only for localhost when `STRUO_ALLOW_INSECURE_PROVIDER_URL` is set. Redirects are not followed (`redirection => 0`); a Location header is a new destination and is validated the same way. The bearer key is never sent across a hop.

## API keys

Prefer `STRUO_OPENAI_API_KEY` in wp-config.php. A database copy is plaintext. Settings never echo the key. Empty submit keeps the saved value. Removing it takes an explicit checkbox. Audit rows do not store keys, prompts, or provider bodies.

## Timeouts and rate limits

The HTTP planner hop times out at 45 seconds. Write and plan rate limits use atomic counters (object cache or options CAS).

## Privacy

WordPress personal-data export/erase for audit and plan rows is scoped to the requested user. Option-store leftovers are filtered by `user_id`.

## Uninstall

`uninstall.php` deletes Struo's options, its `struo_*` transients and `sae_*` transients written under the plugin's earlier key prefix, the daily planner-spend rows (`struo_plan_spend_*`) and the atomic rate-limit counters (`struo_rl_*`). It also removes the five Struo capabilities (`struo_plan`, `struo_approve`, `struo_apply`, `struo_manage_registry`, `struo_manage_settings`) from every role. The audit and plans tables stay unless `STRUO_DROP_AUDIT_TABLE_ON_UNINSTALL` / `STRUO_DROP_PLANS_TABLE_ON_UNINSTALL` is defined as true; the legacy `SAE_DROP_AUDIT_TABLE_ON_UNINSTALL` / `SAE_DROP_PLANS_TABLE_ON_UNINSTALL` names are still read, and win if both are defined.

The plan rate-limit transients (`struo_plan_rate_*`) and the `struo_rl_*` counters are cleared only on uninstall, not on deactivation, so toggling the plugin cannot reset those quotas.

