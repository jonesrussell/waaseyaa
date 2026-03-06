# Ingestion Fixture Pack Contract (v1.5)

## Scope
- Issue: `#169`
- Goal: provide deterministic, versioned fixture corpus for ingestion pipeline coverage and replay safety.

## Fixture Corpus Paths
- Ingestion inputs:
  - `tests/fixtures/ingestion/structured-valid.input.json`
  - `tests/fixtures/ingestion/structured-schema-invalid.input.json`
  - `tests/fixtures/ingestion/structured-validation-invalid.input.json`
  - `tests/fixtures/ingestion/structured-inference.input.json`
- Scenario pack seeds:
  - `tests/fixtures/scenarios/ingestion-ready.json`
  - `tests/fixtures/scenarios/ingestion-review.json`
  - `tests/fixtures/scenarios/ingestion-blocked.json`

## Required Coverage
- Structured valid ingestion success and replay determinism
- Schema failure (`schema.duplicate_source_uri`)
- Validation failure (`validation.semantic.insufficient_publishable_tokens`)
- Inference coverage (`inference.relationship_inferred`)
- Fixture-pack aggregate determinism (`fixture:pack:refresh` hash stability)

## Regression Consumption
- `IngestionFixturePackRegressionTest` must:
  - replay fixtures through `ingest:run`
  - verify deterministic output hash for replay-safe scenario
  - assert diagnostic coverage across schema/validation/inference
  - validate fixture pack aggregate determinism across repeated runs

## Determinism Rules
- All fixture files are static and version-controlled.
- Ingest replay tests use fixed options (`batch_id`, `timestamp`, policy/source settings).
- Scenario aggregate order is deterministic by sorted filenames and sorted keys.

## Stability Rules
- Fixture corpus and expectations are contract-stable for v1.5.
- Additions are allowed; renames/removals require coordinated test/spec updates.

---

## v1.6 Multi-Source Fixture Pack (Issue #178)

### Scope
- Issue: `#178`
- Goal: expand fixture corpus with federated multi-source scenarios; cover adapter normalization, dedupe, merge conflicts, and refresh deltas; preserve fixture-pack hash and replay determinism.

### Multi-Source Fixture Paths
- Adapter normalization (canonical vs mutation):
  - `tests/fixtures/ingestion/adapter-normalization-canonical.json`
  - `tests/fixtures/ingestion/adapter-normalization-mutation.json`
- Federated multi-source identity/merge:
  - `tests/fixtures/ingestion/multi-source-federated.input.json`
- Refresh deltas (baseline vs current):
  - `tests/fixtures/ingestion/multi-source-refresh.baseline.json`
  - `tests/fixtures/ingestion/multi-source-refresh.current.json`
- Connector transport fixtures (see `docs/specs/source-connectors-contract.md`):
  - `tests/fixtures/connectors/dataset.json`
  - `tests/fixtures/connectors/api.json`
  - `tests/fixtures/connectors/file.json`
  - `tests/fixtures/connectors/crawl.json`

### Required Coverage (v1.6)
- Adapter normalization: canonical URI pass-through and mutation normalization (diagnostic `adapter.normalized_uri`).
- Dedupe / canonical identity: multi-source rows resolved to stable identity bindings (order-independent).
- Merge conflicts: source-priority merge with field conflict diagnostics (`merge.field_conflict`).
- Refresh deltas: baseline vs current comparison with relationship/field change categories (`refresh.relationship_change`).
- Fixture-pack aggregate determinism: `fixture:pack:refresh` hash stability over scenario corpus including multi-source fixtures.

### Regression Consumption (v1.6)
- `SourceAdapterNormalizerTest` must consume `adapter-normalization-canonical.json` and `adapter-normalization-mutation.json`; assert expected provenance and diagnostics.
- `MultiSourceFixturePackTest` must consume `multi-source-federated.input.json` and `multi-source-refresh.*.json`; assert identity stability, merge diagnostics, refresh category.
- `IngestionFixturePackRegressionTest` and `FixturePackRefreshCommandTest` continue to consume scenario pack; multi-source fixtures are part of the deterministic corpus for hash stability.
- All v1.6 fixture paths must exist and be valid JSON (version-controlled, static).

### Determinism Rules (v1.6)
- Same as v1.5: static version-controlled files, fixed options in replay tests, sorted order for aggregates.
- Multi-source fixture inputs use fixed `source_id` / `source_uri` and ownership; replay order (e.g. row order) must not change canonical identity or merge outcome.
