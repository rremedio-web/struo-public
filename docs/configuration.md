# Configuration constants

Struo reads these constants from `wp-config.php`. Each one has a current `STRUO_*` name and, for most, a legacy `SAE_*` alias from the plugin's earlier prefix. Define only the `STRUO_*` name.

## Precedence

Constants are read through `Struo_Block_Editor::get_config_constant( $legacy, $generic )`:

- **If both names are defined, the legacy `SAE_*` value wins, even when it is an empty string.** An empty legacy value does not fall through to the `STRUO_*` one: for the string settings below it falls back to the saved option instead. Define only one name.
- If neither is defined, the saved setting (Settings → Struo) or the built-in default applies.
- The last column says whether the related filter still runs after a constant is set. When it does, the filter can override the constant.

## Constants

| Constant | Legacy alias | Default when unset | Related filter | Filter runs after the constant? |
| --- | --- | --- | --- | --- |
| `STRUO_OPENAI_API_KEY` | `SAE_OPENAI_API_KEY` | saved API key option (empty) | none | — |
| `STRUO_OPENAI_BASE_URL` | `SAE_OPENAI_BASE_URL` | saved option, else `https://api.openai.com/v1`; its host is added to the provider host allowlist | none | — |
| `STRUO_OPENAI_MODEL` | `SAE_OPENAI_MODEL` | saved option, else `gpt-4o-mini` | none | — |
| `STRUO_OPENAI_API_STYLE` | `SAE_OPENAI_API_STYLE` | `chat` (`responses` for `muse-spark*` models); only `chat` and `responses` are accepted | none | — |
| `STRUO_AI_PROVIDER` | `SAE_AI_PROVIDER` | saved option, else `auto` (`auto`, `ai_engine` or `openai`) | `struo_provider` | yes |
| `STRUO_ALLOW_INSECURE_PROVIDER_URL` | none | `false`; when true, plain HTTP is allowed for localhost only | none | — |
| `STRUO_ALLOW_PRIVATE_PROVIDER_URL` | `SAE_ALLOW_PRIVATE_PROVIDER_URL` | `false`; when true, provider hosts may resolve to private addresses | none | — |
| `STRUO_PLAN_DAILY_TOKEN_CAP` | `SAE_PLAN_DAILY_TOKEN_CAP` | saved option, else `0` (no cap); capped at 10,000,000 | `struo_plan_daily_token_cap` | yes |
| `STRUO_PLAN_RATE_LIMIT_WINDOW` | `SAE_PLAN_RATE_LIMIT_WINDOW` | `3600` seconds; never below 60 | `struo_plan_rate_limit_window` | yes |
| `STRUO_PLAN_RATE_LIMIT_MAX` | `SAE_PLAN_RATE_LIMIT_MAX` | per-role quota from settings, else `60` plans per window; never below 5 | `struo_plan_rate_limit_max` | yes |
| `STRUO_ALLOW_CORE_HTML` | `SAE_ALLOW_CORE_HTML` | `false`; when true, `core/html` joins the insertable core blocks | none | — |
| `STRUO_RAG_DISABLED` | `SAE_RAG_DISABLED` | `false`; when true, AI Engine retrieval context is not added to planner prompts | none | — |
| `STRUO_LEGACY_SEEDS` | `SAE_LEGACY_SEEDS` | stored flag, else `false` | `struo_legacy_seeds_enabled` | no (the constant short-circuits) |
| `STRUO_CONSOLE_SHORTCUT` | `SAE_CONSOLE_SHORTCUT` | the legacy-seeds value | `struo_console_shortcut_enabled` | no (the constant short-circuits) |
| `STRUO_DROP_AUDIT_TABLE_ON_UNINSTALL` | `SAE_DROP_AUDIT_TABLE_ON_UNINSTALL` | `false`; when true, uninstall drops `{prefix}sae_audit_log` | none | — |
| `STRUO_DROP_PLANS_TABLE_ON_UNINSTALL` | `SAE_DROP_PLANS_TABLE_ON_UNINSTALL` | `false`; when true, uninstall drops `{prefix}struo_plans` and `{prefix}struo_events` | none | — |

The two uninstall constants are read by `uninstall.php`, which runs without the plugin loaded; it applies the same legacy-first rule.

For the four string settings (`*_OPENAI_API_KEY`, `*_OPENAI_BASE_URL`, `*_OPENAI_MODEL`, `*_OPENAI_API_STYLE`), an empty constant counts as unset and the saved option applies.

## Test-only constants

These are set by the test harnesses. Do not define them on a real site.

| Constant | Used by |
| --- | --- |
| `STRUO_EVAL_HARNESS` | `tests/wp-eval-cases.php`, `tests/wp-eval-lite.php`: tags audit rows, records eval disclosure spend, and enables the apply crash-injection filter |
| `STRUO_GUARD_STANDALONE` | `tests/provider-url-cases.php`, `tests/plugin-entry-deactivate-cases.php`: load the guard files without WordPress |
| `STRUO_DURABLE_PLANS_STANDALONE` | `tests/durable-plan-store-cases.php`: load the plan store without WordPress |
