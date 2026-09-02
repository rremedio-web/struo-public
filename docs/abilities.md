# Struo abilities

Struo registers a small, allowlisted set of WordPress 6.9 abilities so MCP Adapter, AI Editor, and Kratt can discover the plugin without copying its UI.

The allowlist plus the durable plan loop (`plan_id` → `struo_approve` →
`struo_apply`) is the product. Confirmation tokens are not redeemable.
Direct REST writes (`dry_run=false`, including a confirmation token on
insert/update/remove/batch/`fields/update`/`pages/create`) are rejected
with `sae_plan_apply_required`; persist is a durable-plan claim only.
`struo/apply-agent-plan` is registered and **not** MCP-public; see
[`docs/apply-ability-spike.md`](apply-ability-spike.md).

The published action list is `Struo_Operation_Catalog` in
`includes/class-operation-catalog.php`. Ability `mcp.public` is read from
that table, then the existing filters, then apply is forced false. REST,
Abilities, and MCP call `Struo_Authority::authorize()` for the same yes/no.

## Category

`struo` is registered on `wp_abilities_api_categories_init`. Abilities are registered on `wp_abilities_api_init`. Registration is skipped when `wp_register_ability()` is absent.

## Catalog

| Ability | Handler | Annotations | `mcp.public` default | `meta.ai_editor` |
|---|---|---|---|---|
| `struo/get-block-catalog` | `get_block_catalog()` | readonly, not destructive, idempotent | **true** | yes |
| `struo/get-post-blocks` | `get_post_blocks()` | readonly, not destructive, idempotent | **true** | yes |
| `struo/plan-block-change` | `plan_block_change()` | not readonly, not destructive of content | **true** | **no** |
| `struo/preview-insert` | `insert_block()` dry-run | not readonly | **false** | **no** |
| `struo/preview-update` | `update_block()` dry-run | not readonly | **false** | **no** |
| `struo/preview-remove` | `remove_block()` dry-run | destructive | **false** | **no** |
| `struo/preview-batch` | `batch_blocks()` dry-run | destructive | **false** | **no** |
| `struo/apply-agent-plan` | `apply_agent_plan()` | destructive, not idempotent | **false** (forced) | **no** |

MCP Adapter names use hyphens (`struo-get-block-catalog`). The default MCP server lists an ability only when `meta.mcp.public` is strictly `true`.

## Token contract

Read, plan, and preview execute callbacks call `Struo_Block_Editor::dispatch_internal()` with origin `mcp`. That path:

1. runs `Struo_Authority::authorize()` for the operation (same caps and object rules as REST). `post_id` or `id` both resolve through `request_post_id()` (URL `id`, else body `post_id`/`id`). `dispatch_internal` also binds URL `id` so MCP body params cannot wipe the route param.
2. forces `dry_run=true` and strips `confirmation_token`
3. stamps `struo_origin=mcp` so minting returns `redeemable: false` — including nested `plan_block_change` → `run_plan_dry_run()` → `apply_*`

`struo/apply-agent-plan` does **not** use `dispatch_internal`. It calls `apply_agent_plan()` with a durable `plan_id` after REST/console Approve. `issue_confirmation_token()` never stores a redeemable transient.

AI Engine `struo_*` MCP tools (and previous plugin prefixes) use the same dispatcher as the preview/plan abilities. There is no MCP apply tool.

## Not registered

- persist/apply insert, update, remove, or batch as MCP-public tools
- `struo/approve-agent-plan`
- `/plan/stream`
- page create, template promote, kill switch, audit export, `/console/status`
- one ability per ACF block
- a generic “serialize these blocks onto the post” tool (`gutenberg/update-post-blocks` and cousins)

MCP/Abilities `plan-block-change` queues a non-redeemable durable review object
(`plan_id`, no token). A multi-page bundle (`bundle_v1`) is not a new ability;
`struo/plan-block-change` accepts optional `post_ids`. Children apply via the
existing private apply after Approve. Mission Brief
**Plans from agents** is the operator inbox (bundle children are hidden). Approve stays `struo_approve` on REST. Apply is
`struo_apply` on REST **or** the private `struo/apply-agent-plan` ability.
There is no redeemable token in the agent response.

## Overrides

`mcp.public` can be flipped per ability with the `struo_ability_mcp_public` filter (legacy alias `subsurface_ai_ability_mcp_public`), except `struo/apply-agent-plan`, which is forced false after those filters. There is no Settings checkbox that bulk-opens writes.

## WordPress 7 Client

On WordPress 7.0, Auto and the AI Engine pin use `wp_ai_client_prompt()` only when a `text_generation` model exists (Connectors, including Meow as a gateway). Otherwise Auto falls through to AI Engine, then the OpenAI-compatible HTTP adapter. `wp_ai_client_prevent_prompt` is hooked for outbound empty/oversize checks and `struo_prevent_ai_prompt`. Outbound prompt text can be redacted via `struo_ai_outbound_prompt`. Daily estimated prompt tokens can be capped (`ai_plan_daily_token_cap` / `STRUO_PLAN_DAILY_TOKEN_CAP`); over-cap returns `sae_plan_spend_cap` and does not call the provider. `GET /console/status` reports the resolved hop, last hop (backend, model, prompt bytes, latency), and spend remaining.
