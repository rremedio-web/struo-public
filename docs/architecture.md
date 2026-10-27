# Architecture

Struo is a WordPress plugin that compiles an operator (or agent) request into a durable plan, then lets a person preview, approve, and apply it.

```text
Request → Plan (dry-run) → Durable plan row → Approve → Apply → Audit
```

## Extraction is incomplete

`src/` is a start, not a finished modularization. `struo.php` is still about 22,000 lines. `assets/src/js/app.js` is still about 11,000 lines (authored console). `src/Rest/RouteRegistrar.php` is one large registration method. Several `Struo\` classes are thin facades that delegate back into `Struo_Block_Editor`.

Next extractions, when they happen: REST controllers and permission policy, planning and provider coordination, privacy exporter/eraser, admin settings rendering, frontend queue / plans / API / modal modules. Do not treat the current layout as complete.

## Layers

- **Bootstrap** — `struo.php` is the WordPress entry. It registers hooks, keeps the atomic rate-limit core, and autoloads `Struo\` from `src/`.
- **REST** — `src/Rest/RouteRegistrar.php` owns the `struo/v1` route table. Callbacks still live on `Struo_Block_Editor` until further splits. Permission checks go through `Struo_Authority` (`src/Rest/PermissionPolicy.php` is the named seam).
- **Planning** — compile, preview, and apply orchestration stay in `struo.php`. Identity and capacity go through `Struo_Durable_Plans` / `src/Planning/PlanRepository.php`.
- **Provider HTTP** — last-resort OpenAI-compatible calls are `src/AI/OpenAICompatibleProvider.php` (45s timeout, `redirection => 0`).
- **Audit / privacy** — table writes in `struo.php`; exporter registration is `src/Audit/PrivacyExporter.php`.
- **Console** — authored JS/CSS under `assets/src/`; shipped files are `assets/console.js` and `assets/console.css`. Work Queue and the block-editor host are wp-scripts builds in `assets/`.

## What does not exist here

There is no former REST namespace, no compatibility class alias, and no n8n bridge. Demo ACF block names are generic (`acf/hero`, `acf/testimonials`, `acf/feature-grid`, `acf/faq`, `acf/call-to-action`).

## Apply contract

Agents may plan. Apply is a person at the site: console, REST with `plan_id`, or the private `struo/apply-agent-plan` ability (`mcp.public` false). Direct REST persist is `sae_plan_apply_required`.
