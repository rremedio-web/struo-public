# Struo — Normalization Spec

Status: draft v0

This document defines the normalization contract for expanding Struo from a limited allowlist of core and ACF blocks toward broader theme-aware discovery.

It is intentionally implementation-facing. The goal is to give the plugin one stable internal contract that discovery, planning, policy, validation, and write paths can share.

## Goals

- Define one normalized schema shape for Gutenberg and ACF blocks.
- Separate discovery from writeability.
- Preserve safety and policy constraints while broadening coverage.
- Make schema versioning and staleness handling explicit.
- Define what must exist before Claude reviews a normalization phase.

## Non-goals

- Promise that every discovered block becomes writable.
- Flatten every ACF structure into one generic field shape.
- Replace block-specific adapters for complex blocks.
- Infer business intent purely from block registration metadata.

## Output Artifacts

Normalization work produces two distinct artifacts.

### 1. Discovery Catalog

This is the raw site/theme inventory. It answers: what blocks exist here?

Each catalog item should contain:

- `block_name`: exact registered name, for example `core/heading` or `acf/hero`
- `provider`: `core`, `acf`, `theme`, `plugin`, or `unknown`
- `title`: human-readable label when available
- `source_type`: `gutenberg_registration`, `acf_registration`, `manifest_overlay`, or `manual`
- `supports`: raw support metadata when available
- `schema_state`: `unknown`, `partial`, `normalized`, `adapter_required`, or `unsupported`
- `write_state`: `discovered`, `inspectable`, `planned`, `writable`, or `blocked`
- `policy_source`: `manifest`, `adapter`, `default`, or `none`
- `schema_hash`: version hash of the normalized schema if one exists

Discovery does not imply mutation safety.

### 2. Normalized Block Schema

This is the planner/write contract. It answers: how do we reason about this block safely?

Each normalized block schema should contain:

- `block_name`
- `schema_version`: semantic version of the normalization contract, for example `1`
- `schema_hash`: deterministic hash of the normalized payload
- `adapter_type`: `core_generic`, `acf_generic`, `adapter_explicit`, or `unsupported`
- `identity_model`: `single`, `group`, `repeater`, `nested_blocks`, or mixed
- `write_state`: `planned`, `writable`, or `blocked`
- `unsupported_reasons`: array of machine-readable reason codes when not writable
- `fields`: normalized top-level editable fields
- `policy`: normalized policy overlay used by validation/write paths

## Normalized Field Contract

Every normalized field must include:

- `path`: stable dotted path, for example `headline`, `slides[].title`, `media.image.id`
- `label`: human-readable field label
- `kind`: one of `text`, `rich_text`, `boolean`, `number`, `url`, `select`, `image`, `file`, `link`, `group`, `repeater`, `blocks`, `unknown`
- `storage_type`: `attribute`, `acf_field`, `derived`, `computed`, or `unknown`
- `required`: boolean
- `writable`: boolean
- `readonly_reason`: nullable reason code when not writable
- `cardinality`: `one`, `many`, or `unknown`
- `children`: nested field definitions for `group`, `repeater`, and `blocks`
- `constraints`: normalized validation hints such as enum values, max items, min items, allowed block names, or pattern requirements
- `safety`: structured safety metadata

### Safety Metadata

Each field `safety` object should contain:

- `risk_level`: `low`, `medium`, `high`, or `blocked`
- `side_effects`: array such as `layout_sensitive`, `media_dependency`, `render_callback`, `theme_coupled`
- `requires_adapter`: boolean
- `requires_manual_confirmation`: boolean
- `safe_default_available`: boolean

## Identity Rules

Normalization must preserve identity across read, plan, and apply.

### Block Identity

Blocks are identified by:

- `block_name`
- resolved target reference already used by the plugin, such as `index_path`, `anchor`, or `block_id`
- `schema_hash` for compatibility checks

### Field Identity

Fields are identified by normalized `path`, not just display label.

Rules:

- Top-level ACF fields use their logical field name when stable.
- Repeaters use `[]` notation, for example `slides[].title`.
- Nested block collections use block-relative paths, for example `cards[].content.headline`.
- Label text is never the canonical identity key.
- Renamed fields must either map through a declared alias or invalidate the schema hash.

## Nesting Model

The contract must model four nesting modes explicitly.

### 1. Scalar Fields

Examples:

- `core/heading.content`
- `acf/hero-board.headline`

### 2. Group Fields

Object-like nested structures with named children.

Example:

- `cta.primary.label`
- `cta.primary.url`

### 3. Repeater Fields

Ordered rows of the same child schema.

Rules:

- Child paths use `[]`
- Row order is significant
- Read-only or planned repeaters may use positional identity
- Writable repeaters must expose a stable row key from source data or adapter logic
- If no stable row key exists, repeater `write_state` must remain `planned` or `blocked`

Recommended writable row key sources, in order:

- persisted UUID or row ID from source data
- adapter-managed stable key persisted alongside the row
- explicit immutable business key declared by adapter policy

### 4. Nested Block Fields

Containers that accept child blocks or block-like structures.

Rules:

- The contract must preserve allowed child block names
- Existing plugin allowlist checks still apply until adapter coverage expands
- If nested block identity cannot be represented safely, mark the field `blocked`

## Writable Classification

Every block and field must land in one of these states.

- `discovered`: known to exist only
- `inspectable`: readable schema/structure exists
- `planned`: planner may reference it, but writes stay blocked
- `writable`: write path exists and policy checks are defined
- `blocked`: explicitly unsupported or unsafe

A block is only `writable` when all of the following are true:

- normalized schema exists
- schema hash is current
- policy overlay exists or safe defaults are known
- field-level writable flags are resolved
- required adapter behavior is implemented

## Partial-write Semantics

Normalization must make partial updates explicit so adapters do not invent incompatible behavior.

Rules:

- Write payloads are patch-style by default and only target declared normalized field paths.
- Untouched fields must be preserved from source content rather than re-derived from defaults.
- Adapters may require full-block round-trip only when explicitly declared in policy or adapter metadata.
- If a block cannot guarantee safe preservation of untouched fields, its `write_state` must remain `planned` or `blocked`.

Each normalized block schema should declare:

- `write_mode`: `patch`, `round_trip`, or `blocked`
- `preserve_unknown_fields`: boolean
- `requires_source_snapshot`: boolean

## Adapter Criteria

The generic model is allowed only where it stays truthful.

### Generic Core Adapter

Use when:

- block attributes already map cleanly to scalar/group fields
- no hidden render-time side effects matter to writes
- nested content is already covered by existing plugin behavior

### Generic ACF Adapter

Use when:

- fields are scalar/group/repeater only
- field names are stable
- no flexible content, clone-field ambiguity, or relationship-driven side effects exist

### Explicit Adapter Required

Use when any of the following are true:

- flexible content layouts exist
- repeater rows contain nested blocks
- media fields require ID/url/alt/caption coordination
- theme logic depends on implicit defaults or derived fields
- block rendering depends on computed server-side state
- one block slug maps to materially different field groups across themes

### Unsupported

Mark `blocked` when:

- structure cannot be represented without lossy flattening
- safe mutation semantics are unclear
- available metadata is insufficient to generate a truthful adapter

## Unsupported Cases

The first implementation should explicitly reject, not guess, these cases:

- ACF flexible content with layout-specific row schemas
- clone fields where origin field identity is ambiguous
- relationship/post-object fields without a clear allowed target contract
- nested arbitrary `innerBlocks` with unknown child types
- blocks whose safe defaults come entirely from theme PHP and cannot be reconstructed
- blocks whose write semantics depend on undocumented side effects

When unsupported, the plugin should:

- expose the block in discovery
- surface a machine-readable unsupported reason
- keep write state `blocked`
- avoid planner suggestions that imply the block is editable

## Versioning And Staleness

The normalized schema is versioned product surface.

### Required Version Inputs

Each `schema_hash` should be derived from:

- canonical normalized schema JSON
- normalized block schema payload
- source registration metadata
- relevant ACF field group definitions
- manifest/policy overlay
- normalization contract version
- adapter implementation version when an explicit adapter is used

### Hash Algorithm Contract

To avoid silent divergence, the first implementation should pin:

- canonical key ordering for all normalized JSON payloads
- UTF-8 serialization with no insignificant whitespace dependence
- `SHA-256` as the schema hash function

Equivalent logical schemas must always produce the same `schema_hash`.

### Invalidation Triggers

Schema should be recomputed when any of these change:

- `block-manifest.json`
- block registration metadata
- ACF field group definition affecting the block
- normalization contract version
- explicit adapter implementation version

If adapter implementation version changes:

- the adapter version must feed the `schema_hash`
- cached normalized schemas for that adapter must invalidate
- existing write sessions using the old adapter hash must fail safe

### Safe Degradation

If a schema hash changes mid-session:

- dry-run/apply contracts referencing the old hash must fail safely
- planner cache for that block must invalidate
- write state should degrade to `planned` or `blocked` until re-normalized

## Validation Matrix

Theme-agnostic claims require a real validation matrix.

Minimum matrix:

- a block theme with an allowlisted ACF set
- one second theme or block library with materially different ACF/custom block patterns
- core-only Gutenberg case with no ACF dependency

For each theme/block set, capture:

- discovered block count
- normalized block count
- writable block count
- explicit-adapter block count
- blocked block count by reason

## Claude Review Contract

Claude should review a concrete artifact bundle, not just a roadmap paragraph.

Required review inputs:

- this spec
- unsupported-case list
- sample normalized schemas for at least:
  - one core scalar block
  - one ACF group block
  - one ACF repeater block
  - one blocked/unsupported block
- validation matrix draft

Claude review prompts should ask for:

- abstraction gaps in the normalized contract
- places where the generic model lies about mutation safety
- missing versioning/staleness cases
- portability risks across themes

Acceptance criteria for review closure:

- no unresolved contract ambiguity around identity or nesting
- unsupported cases are explicit rather than hand-waved
- schema versioning and invalidation rules are testable
- second-theme validation is defined before broad write expansion

## Immediate Implementation Implications

Before broadening write support, the plugin should have:

1. A normalization builder that emits `schema_hash`, `adapter_type`, and `write_state`.
2. A discovery/read surface that can expose blocked and adapter-required blocks honestly.
3. A policy layer that consumes normalized fields rather than assuming raw ACF schemas are enough.
4. Tests or fixtures for at least one core block, one simple ACF block, one repeater ACF block, and one blocked case.
