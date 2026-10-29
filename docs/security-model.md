# Security model

Struo is allowlist-first. A post is not editable until an administrator puts it on the allowlist (or a create flow adds it after apply).

## Apply contract

There are no write tokens. A post changes only when an approved durable plan is applied:

1. A plan call (console, REST `POST /plan`, a REST dry run, an ability, or an MCP tool) stores a durable plan row and returns its `plan_id`.
2. A person with `struo_approve` approves that `plan_id` (REST or console).
3. A person with `struo_apply` applies it: console, REST with the `plan_id`, or the private `struo/apply-agent-plan` ability, which is never MCP-public.

Legacy `confirmation_token` fields are accepted and ignored; responses carry `redeemable: false`. A direct persist (`dry_run=false` on insert/update/remove/batch, `fields/update` or `pages/create`) returns 403 `sae_plan_apply_required`. MCP tools and the read/plan/preview abilities always run as dry runs, so an agent on MCP can never apply.

## Auth and capability

REST permission callbacks authorize through `Struo_Authority`. Anonymous callers get 401 before allowlist 403. Plan / approve / apply use `struo_plan`, `struo_approve`, and `struo_apply`.

**Preview routes.** The block insert/update/remove/batch and `fields/update` routes, used for dry-run previews, need no Struo capability: only a logged-in user, the post on the allowlist, and `edit_post` on it (the `''` cap in `Struo_Operation_Catalog`). Each REST dry run stores a durable plan, and those plans count toward the site-wide cap of 25 active plans (`Struo_Durable_Plans::ACTIVE_CAP`, 429 `sae_plan_capacity` when full).

**Application passwords.** Approve and apply need `struo_approve` / `struo_apply` and a stored `plan_id`, and MCP can never apply. WordPress cannot tell a person from a script that uses that person's credentials, though: an application password for a user who holds `struo_approve` and `struo_apply` lets a script approve and apply over REST, and the apply ability is registered with `show_in_rest => true`. Do not give agents application passwords for users who hold those capabilities.

## Provider URL

`provider-url-guard.php` requires HTTPS, an allowlisted host, and a public resolution. Loopback HTTP is only for localhost when `STRUO_ALLOW_INSECURE_PROVIDER_URL` is set. Redirects are refused, not followed: the provider call uses `redirection => 0`, and a 3xx response is treated like any other non-2xx status (`sae_planner_error`). The bearer key therefore never leaves the validated host. `Struo_Provider_Url_Guard::validate_redirect_location()` exists and is unit-tested, but it is not on the request path.

## API keys

Prefer `STRUO_OPENAI_API_KEY` in wp-config.php. A database copy is plaintext. Settings never echo the key. Empty submit keeps the saved value. Removing it takes an explicit checkbox. Audit rows do not store keys, prompts, or provider bodies.

The Google service-account JSON for Findings (GA4 / Search Console), private key included, is also stored in plaintext, in the `struo_google_service_account` option. An empty submit keeps it, and there is no remove control yet; delete it with `wp option delete struo_google_service_account`.

## Timeouts and rate limits

The HTTP planner hop times out at 45 seconds. Write and plan rate limits use atomic counters (object cache or options CAS).

## Privacy

WordPress personal-data export/erase for audit and plan rows is scoped to the requested user. Option-store leftovers are filtered by `user_id`.

## Uninstall

`uninstall.php` deletes Struo's options, its `struo_*` transients and `sae_*` transients written under the plugin's earlier key prefix, the daily planner-spend rows (`struo_plan_spend_*`) and the atomic rate-limit counters (`struo_rl_*`). It also removes the five Struo capabilities (`struo_plan`, `struo_approve`, `struo_apply`, `struo_manage_registry`, `struo_manage_settings`) from every role. The audit and plans tables stay unless `STRUO_DROP_AUDIT_TABLE_ON_UNINSTALL` / `STRUO_DROP_PLANS_TABLE_ON_UNINSTALL` is defined as true; the legacy `SAE_DROP_AUDIT_TABLE_ON_UNINSTALL` / `SAE_DROP_PLANS_TABLE_ON_UNINSTALL` names are still read, and win if both are defined.

The plan rate-limit transients (`struo_plan_rate_*`) and the `struo_rl_*` counters are cleared only on uninstall, not on deactivation, so toggling the plugin cannot reset those quotas.

