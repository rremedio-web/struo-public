# Architecture

Struo is a WordPress plugin that compiles an operator (or agent) request into a durable plan, then lets a person preview, approve, and apply it.

```text
Request → Plan (dry-run) → Durable plan row → Approve → Apply → Audit
```

## Extraction is incomplete

`src/` is a start, not a finished modularization. `struo.php` is still about 22,000 lines. `assets/src/js/app.js` is still about 11,000 lines (authored console). `src/Rest/RouteRegistrar.php` is one large registration method, and `PrivacyExporter` is a thin facade that delegates back into `Struo_Block_Editor`.

Next extractions, when they happen: REST controllers and permission policy, planning and provider coordination, privacy exporter/eraser, admin settings rendering, frontend queue / plans / API / modal modules. Do not treat the current layout as complete.

## Layers

- **Bootstrap** — `struo.php` is the WordPress entry. It registers hooks, keeps the atomic rate-limit core, and autoloads `Struo\` from `src/`.
- **REST** — `src/Rest/RouteRegistrar.php` owns the `struo/v1` route table. Callbacks still live on `Struo_Block_Editor` until further splits. Permission callbacks are `Struo_Block_Editor::can_*`, and they delegate to `Struo_Authority::authorize()` (`includes/class-authority.php`).
- **Planning** — compile, preview, and apply orchestration stay in `struo.php`. Plan identity and capacity: `Struo_Durable_Plans` (`includes/class-durable-plans.php`).
- **Provider HTTP** — last-resort OpenAI-compatible calls are `src/AI/OpenAICompatibleProvider.php` (45s timeout, `redirection => 0`).
- **Audit / privacy** — table writes in `struo.php`; exporter registration is `src/Audit/PrivacyExporter.php`.
- **Console** — authored JS/CSS under `assets/src/`; shipped files are `assets/console.js` and `assets/console.css`. Work Queue and the block-editor host are wp-scripts builds in `assets/`.

### `src/` classes and their callers

| Class | File | Called from |
| --- | --- | --- |
| `Struo\Rest\RouteRegistrar` | `src/Rest/RouteRegistrar.php` | `Struo_Block_Editor::register_rest_routes()` on `rest_api_init` |
| `Struo\Audit\PrivacyExporter` | `src/Audit/PrivacyExporter.php` | the `wp_privacy_personal_data_exporters` / `_erasers` filters in `Struo_Block_Editor::init()` |
| `Struo\AI\OpenAICompatibleProvider` | `src/AI/OpenAICompatibleProvider.php` | `Struo_Block_Editor::call_openai_json_query()`, the last-resort OpenAI-compatible planner call |

## Apply contract

Agents may plan. Apply is a person at the site: console, REST with `plan_id`, or the private `struo/apply-agent-plan` ability (`mcp.public` false). Direct REST persist is `sae_plan_apply_required`.
