# Research: PHP 8.4 Lazy Object Hydration

**Mission**: php84-lazy-object-hydration-01KR82KZ
**Phase**: 0 (Research)

This document records the three research items the plan flagged. All architectural decisions are already locked in `spec.md` § Open Questions; the items here are verifications and methodology choices, not design re-litigation.

---

## R1: Compatibility of `newLazyGhost()` with the existing reflection-based constructor-shape detection in `SqlEntityStorage`

**Decision**: Use `newLazyGhost()` directly. The initializer closure populates `EntityValues` after instantiation; the constructor is not invoked at lazy-creation time, so the existing `(array $values)` constructor that hardcodes `entityTypeId`/`entityKeys` is not a problem.

**Rationale**:

- PHP 8.4's `ReflectionClass::newLazyGhost(\Closure $initializer)` returns an instance whose properties are not yet set. The class's constructor is **not** invoked. The initializer closure receives the (uninitialized) instance and is responsible for populating its state, which fires the first time any non-key property is read or any method that touches a non-key property is called.
- Eager-path entity construction goes `new SubclassName($values)` → constructor calls `parent::__construct($values, 'entity_type_id', [...keys...])`. We replicate that side effect inside the lazy initializer by calling `EntityBase::initializeFromValues($values)` (a new `protected` helper to factor out from the constructor body), so the same field bag is produced.
- `final class` is supported by PHP 8.4 lazy ghosts. No subclassing required.
- Entity-key fields (`id`, `uuid`, `label`, plus extras from `EntityType::getKeys()`) are written into the field bag during `newLazyGhost` setup via `ReflectionProperty::setValue()` — keys land before the closure ever fires, so reading them does not trigger initialization.

**Alternatives considered**:

- **`newLazyProxy()` for entity instances** — rejected. Proxy is the right choice when the consumer holds an interface and the concrete class is unknown; entity instances are concrete subclasses with hardcoded type metadata, so ghost is the canonical fit.
- **A bespoke `EntityFactory::createGhost()` that uses `ReflectionClass::newInstanceWithoutConstructor()` plus manual field writes** — rejected unless R1 verification fails. PHP 8.4's `newLazyGhost()` is the supported, observable-by-debug-tools mechanism; rolling our own loses Xdebug/profiler integration.

**Verification step (during implement WP01)**: Add a single throwaway unit test that constructs a lazy ghost of an anonymous `EntityBase` subclass with the canonical `(array $values)` constructor; assert (a) the ghost is created without invoking the constructor, (b) reading a key field does not trigger init, (c) reading a non-key field does. If this test passes, the rest of WP01 proceeds; if it fails, fall back to the bespoke `EntityFactory::createGhost()` path documented above.

---

## R2: Audit of `FieldAccessPolicyInterface` implementations that inspect non-key entity state

**Decision**: Catalogue at the start of WP02 (the field-policy compatibility WP). Result is informational, not gating.

**Rationale**:

- Spec accepts the semantics "policies that read non-key state trigger initialization" — there is no design lever to move here.
- The catalogue informs whether NFR-002's ≥40% allocation reduction is realistic in workloads gated by a chatty field policy. If the answer is "no, every list is gated by a policy that touches `owner_id`", we still ship the change (single-entity reads still benefit; list reads of unfiltered endpoints still benefit), but we open a follow-up issue to introduce a per-policy "key-only" hint.

**Method (during implement WP02)**:

```bash
rg -l "implements (.*\\b)?FieldAccessPolicyInterface" packages/ \
  | xargs rg -n "->(get|hasField|toArray|getFieldDefinitions)\\(|->[a-z_]+\\b(?!\\()"
```

Then manually classify each policy as: (a) key-fields-only (no init triggered), (b) reads one named non-key field (init triggered, predictable), (c) reads many fields or `toArray()` (init triggered fully). Record the count and the highest-impact examples in WP02's notes.

**Alternatives considered**:

- **Special-case policy evaluation to bypass init** — rejected during specify discovery (would break transparent semantics).
- **Defer the audit** — rejected. The audit is cheap (one `rg` and a read) and the result is needed to set realistic expectations for benchmark interpretation.

---

## R3: Benchmark methodology

**Decision**: Bespoke PHPUnit-based harness in `packages/entity-storage/tests/Benchmark/`, marked `#[Group('benchmark')]`, excluded from the default `phpunit` invocation, run manually via `./vendor/bin/phpunit --group=benchmark`.

**Rationale**:

- Zero new dev-dependencies; reuses the existing PHPUnit infrastructure (config, autoloading, fixtures).
- Runs against a representative entity (≥5 non-key fields, including a `_data` blob value) seeded into an in-memory SQLite DB via `DBALDatabase::createSqlite()` — exactly the test pattern already used elsewhere in the project.
- Uses three measurement points:
  - **Wall-clock**: `microtime(true)` deltas across each scenario (warmup pass, then ≥1000 iterations, report median + p95).
  - **Allocation count**: a static counter (`LazyInitCounter::$invocations`) incremented inside the lazy initializer closure. Test-only; the counter is plumbed via a configurable initializer wrapper that defaults to a no-op in production code paths.
  - **Peak memory**: `memory_get_peak_usage(true)` reset (`memory_reset_peak_usage()`) between scenarios.
- Three scenarios, mapped to NFRs:
  1. Cold `find()`, key-only read (NFR-001 ≥30% wall-clock improvement).
  2. List-100 query, key-only read (NFR-002 ≥40% allocation reduction; counter must drop from 100 to ≤60).
  3. Cold `find()`, full read of all fields (regression guard; ≤5% slowdown acceptable).

**Alternatives considered**:

- **phpbench** — rejected. Adds a dev-dep, a new config surface, and CI wiring for two NFR thresholds. Over-engineered for the scope.
- **Skip formal benchmark, assert via instance counter only** — rejected. Drops NFR-001 entirely and weakens the perf claim; the wall-clock target is the headline benefit of the mission.

**Run gating**: The benchmark group is opt-in. CI does not run it. The benchmark is invoked manually by the implementer at the end of WP01 (entity hydration) and again as part of mission-review acceptance. Failures of NFR-001 / NFR-002 are mission-blocking before merge.
