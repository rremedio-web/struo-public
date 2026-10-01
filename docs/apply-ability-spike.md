# Apply-as-Ability (`struo/apply-agent-plan`)

Status: **registered, not MCP-public.** Catalog is eight abilities.
Approve stays REST/console. Agents still only see `plan-block-change`.

## Contract

| Field | Rule |
|---|---|
| Input | `{ "plan_id": string }` only. No `confirmation_token`, no `dry_run`, no payload rewrite. |
| Permission | Same ACL as REST `can_struo_apply` (logged-in, `struo_apply`, plan exists, `user_can_review_agent_plan`), authorized with `door=ability`. REST Apply still defaults `door=rest`. Kill switch is enforced inside `apply_agent_plan()`. |
| Execute | `Struo_Abilities::execute_apply_agent_plan()` builds a REST request and calls `apply_agent_plan()` directly. State must be `approved`; hash check; `durable_plan_apply` claim; `mutation_v1` and `page_spec_v1`. |
| `mcp.public` | **false**, forced after `struo_ability_mcp_public`. That filter cannot open this id. |
| `meta.ai_editor` | no |
| Tokens | Execute does not call `issue_confirmation_token`. Never returns `redeemable: true`. |

## Why not `dispatch_internal()`

`dispatch_internal()` always sets `dry_run=true`, strips
`confirmation_token`, and stamps `struo_origin=mcp`. An apply ability
cannot reuse that dispatcher. `dispatch_internal( 'apply-agent-plan', … )`
returns `sae_unknown_operation`.

## Frozen

- No `struo/approve-agent-plan` ability.
- No MCP tool alias for apply.
- No Lane 4 fan-out. No seeds add-on. No BYO-key UI.
