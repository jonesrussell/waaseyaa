# Ingestion Validation Gates Contract (v1.5)

## Scope
- Issue: `#165`
- Surface: post-schema ingestion validation gates over mapped nodes/relationships
- Goal: enforce deterministic workflow, visibility, and semantic invariants before publishable output is emitted.

## Deterministic Gate Lifecycle
1. Run schema normalization + `schema.*` validation (`#163`).
2. If schema passes and mapping has no runtime parse errors, run validation gates.
3. Emit `validation.*` diagnostics with fixed templates and deterministic ordering.
4. For `atomic_fail_fast`, emit no mapped nodes/relationships when any `validation.*` error exists.
5. For `validate_only`, always emit no mapped nodes/relationships while still running validation gates.

## Active Namespaces
- `validation.workflow.*`
- `validation.visibility.*`
- `validation.semantic.*`

## Diagnostic Envelope
Each `validation.*` diagnostic is emitted as:
- `code` (stable machine key)
- `category` (`workflow`, `visibility`, `semantic`)
- `message` (fixed template)
- `location` (JSON Pointer-like path into mapped output)
- `item_index` (nullable convenience field)
- `context` (deterministic keys; contains remediation guidance)

## Active Error Codes
- `validation.workflow.unknown_state`
- `validation.workflow.status_state_mismatch`
- `validation.visibility.missing_relationship_endpoint`
- `validation.visibility.relationship_requires_public_endpoints`
- `validation.semantic.missing_publishable_body`
- `validation.semantic.insufficient_publishable_tokens`

## Gate Rules
### Workflow
- Node `workflow_state` must be a known editorial state.
- Node `status` must match workflow-derived status (`published => 1`, otherwise `0`).

### Visibility
- Relationship endpoints must exist in ingested node set.
- Public (`status=1`) relationships require both endpoints to be public/published.

### Semantic
- Published nodes require non-empty body text.
- Published bodies must meet minimum token threshold (`>=5` tokens).

## Summaries
- `diagnostics.validation_summary`:
  - `error_count`
  - `warning_count` (fixed `0` in v1.5)
  - `categories` (sorted unique)
  - `codes` (sorted unique)

## Stability Rules
- `validation.*` codes and message templates are contract-stable for v1.5.
- Additions must be additive; no renames/removals during v1.5.
