# REST API

Base: `/wp-json/struo/v1`. All mutating routes require a logged-in user with the matching capability. Writes persist only through an approved durable plan (`plan_id` → approve → apply). Direct persist is `403` `sae_plan_apply_required`.

## Catalog and inspection

| Method | Route | Notes |
| --- | --- | --- |
| GET | `/block-catalog` | Allowlisted blocks and policies |
| GET | `/posts/{id}/blocks` | Parsed tree |

## Plan and apply

| Method | Route | Notes |
| --- | --- | --- |
| POST | `/plan` | Compile a request; dry-run preview is plan-only |
| POST | `/console/agent-plans/{id}/preview` | Review a waiting plan |
| POST | `/console/agent-plans/{id}/approve` | Person-only |
| POST | `/console/agent-plans/{id}/apply` | Person-only |
| POST | `/pages/plan-create` | Create-page compile |
| POST | `/pages/create` | Persist create; requires approved plan |

Create preview: `intent=create`, `payload_type=page_spec_v1`, no `serialized_content`. `page_spec_v1` is a discovery payload, not a Work Queue item.

## Console

| Method | Route | Notes |
| --- | --- | --- |
| GET | `/console/status` | Allowlist, queue, kill switch |
| POST | `/console/kill-switch` | Pause writes |

Block insert/update/remove and batch routes still exist for dry-run compile. They do not redeem a confirmation token.
