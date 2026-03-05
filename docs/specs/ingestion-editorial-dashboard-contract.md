# Ingestion Editorial Dashboard Contract (v1.5)

## Scope
- Issue: `#167`
- Surface: deterministic editorial ingestion dashboard generated from ingest artifact JSON files.
- Goal: expose queue state, validation outcomes, inference review load, refresh state, and workflow correctness in a single operational view.

## Command Surface
- Command: `ingest:dashboard`
- Inputs:
  - `--input` (repeatable file paths)
  - `--glob` (optional file glob)
- Outputs:
  - text dashboard (default)
  - JSON payload (`--json`)
  - optional file output (`--output`)

## Queue Status Classification
Per run:
- `blocked`: `error_count > 0`
- `review`: no errors and (`inferred_relationship_count > 0` or `policy == validate_only`)
- `ready`: no errors and no review blockers

## Dashboard Payload
Top-level:
- `meta`
- `summary`
- `runs[]`

### `summary`
- `queue_status_counts` (`blocked|review|ready`)
- `failed_run_count`
- `successful_run_count`
- `refresh_required_count`
- `inference_review_pending_total`
- `workflow_mismatch_total`
- `workflow_state_totals`
- `refresh_category_counts`
- `diagnostic_code_counts`

### `runs[]`
Per artifact row:
- `path`
- `batch_id`
- `policy`
- `source`
- `node_count`
- `relationship_count`
- `error_count`
- `schema_error_count`
- `validation_error_count`
- `runtime_error_count`
- `inferred_relationship_count`
- `inference_review_pending`
- `refresh_required`
- `refresh_primary_category`
- `workflow_state_counts`
- `workflow_mismatch_count`
- `queue_status`
- `diagnostic_codes`

## Determinism Rules
- Input paths are normalized, de-duplicated, and sorted.
- Run rows are sorted by artifact path.
- Aggregate keys (`workflow_state_totals`, `refresh_category_counts`, `diagnostic_code_counts`) are sorted.
- No non-deterministic timestamps are emitted.

## Workflow Correctness Surface
- Dashboard computes `workflow_mismatch_count` by checking node `status` vs. `workflow_state` expectation (`published => 1`, otherwise `0`).
- Aggregate `workflow_mismatch_total` is used as editorial correctness signal.

## Stability Rules
- Dashboard contract is stable for v1.5.
- Additions must be additive; no renames/removals in v1.5.
