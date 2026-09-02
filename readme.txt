=== Struo ===
Contributors: struo
Tags: ai, block-editor, gutenberg, automation
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe, allowlisted AI block editing for WordPress: plan → preview →
approve → apply through durable plans, with idempotency, rate limits,
and a full audit trail.

== Description ==
Struo exposes allowlisted REST endpoints and an admin console for
AI-assisted block editing. Content mutations (block insert/update/remove,
batch, field updates and page creation) are preview-first. Persistence
requires an approved durable plan (`plan_id` → approve → apply); direct
REST writes are rejected even if a legacy confirmation token is supplied.
registry and admin writes (templates, patterns, kill switch) are
capability-gated instead. Ordinary block calls may pass an optional idempotency_key
to dedupe retries. Everything is rate limited and audit
logged. Planner: WordPress 7 AI Client / Connectors when present (AI
Engine can gateway them), else AI Engine, else an OpenAI-compatible HTTP
adapter as last resort.

REST and MCP planning responses do not contain a redeemable write token.
Writes are applied only through an approved durable plan. Agents may
plan. Only a logged-in person at the site Applies.

== External services ==

Struo can send operator requests and allowlisted page/block context to an
AI provider when an administrator configures AI planning and an operator
runs Plan. Activation alone does not contact an AI provider.

Depending on site configuration, processing may be provided by:

* WordPress AI Client / a Connector configured under Settings → Connectors
  (the Connector's own provider, terms, and privacy policy apply)
* AI Engine and its configured provider
* A configured OpenAI-compatible HTTPS endpoint. Approved hosts by default
  include api.openai.com and openrouter.ai.
  OpenAI: https://openai.com/policies/terms-of-use
  OpenAI privacy: https://openai.com/policies/privacy-policy
  OpenRouter: https://openrouter.ai/terms
  OpenRouter privacy: https://openrouter.ai/privacy

When Findings are connected, Struo reads Search Console and Analytics
aggregates for allowlisted pages via a Google service account. Page
content is not sent to Google; requests are property/URL/date reports.
Google terms: https://policies.google.com/terms
Google privacy: https://policies.google.com/privacy
Analytics Data API: https://developers.google.com/analytics/devguides/reporting/data/v1
Search Console API: https://developers.google.com/webmaster-tools

== Installation ==
1. Copy the `struo` folder to `wp-content/plugins/`.
2. Activate per site. Configure under Settings → Struo.
3. Optional constants: see README section "Configuration".

== Changelog ==
= 0.3.0 =
Mission Brief in the block editor, Work Queue, Findings, durable plan
store, and S0–S5 authority/apply contract. REST and MCP planning is
plan-only (no redeemable write token). Persist is `plan_id` → approve →
apply. Request planner_options are ignored. See CHANGELOG.md.
