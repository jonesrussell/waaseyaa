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
