# WP03 — Cycle 1 Review: APPROVED

**Reviewer:** claude:opus:reviewer
**Date:** 2026-05-11
**Commit reviewed:** `f1956e3eb` (sql-blob backend refactor + byte-identity gate, T013–T017)
**Lane:** `kitty/mission-entity-storage-v2-01KRCDDC-lane-a`
**Verdict:** **APPROVED**

---

## Summary

WP03 delivers `SqlBlobBackend` (the canonical FR-007 implementation of
`FieldStorageBackendInterface`) plus the hardest gate in the mission —
FR-008 byte-identical `_data` JSON between the legacy `SqlEntityStorage`
write path and the new per-field backend path. The byte-identity test
suite (T015 baseline + T016 post-refactor + T017 minimum-surface) is
sharp: raw `assertSame` on the stored `_data` string, no normalization,
no `assertJsonStringEquals*`. The schema-handler delta (T014) cleanly
gates `_data` emission behind `$primaryBackendId`, deferring the
`sql-column` branch to WP05 with an explicit TODO. Scope is clean — no
revisions, no `sql-column` implementation, no vector, no coordinator
write-path swap; that work is correctly reserved for WP05/WP06.

---

## Verified criteria

| # | Criterion | Evidence | Status |
|---|---|---|---|
| 1 | **FR-007** — `SqlBlobBackend implements FieldStorageBackendInterface`; `id() = ReservedBackendIds::SQL_BLOB`; per-field `read/write/delete`. | `packages/entity-storage/src/Backend/SqlBlobBackend.php` declaration + `id_returns_sql_blob` test (T017-1). | ✓ |
| 2 | **FR-008** — byte-identity gate compares **raw** `_data` strings via `assertSame`, no normalization. | `PostRefactorTest::data_json_bytes_are_byte_identical_after_refactor()` does `self::assertSame($rowA['_data'], $rowB['_data'], ...)`. `empty_data_is_byte_identical_between_paths()` pins literal `'[]'` for empty case. | ✓ |
| 3 | **FR-009** — `_data` TEXT column preserved with `default '{}'`, NULL handling unchanged. | `SqlSchemaHandler::buildTableSpec()` `_data` field: `'type' => 'text', 'not null' => true, 'default' => '{}'`. Same shape as legacy. | ✓ |
| 4 | **FR-010** — `supportsQuery()` returns `false` for field predicates; entity-key column queries serviced by `SqlEntityStorage` directly (the backend does not claim them via this method). | `SqlBlobMinimumSurfaceTest` T017-6 / T017-7: false for `string`, `integer`, and even `id`/`uuid` fields. Class-level docblock and method comments make the contract explicit. | ✓ |
| 5 | **FR-049 partial** — minimum-surface conformance: round-trip, idempotent re-write, `supportsQuery` contract. | `SqlBlobMinimumSurfaceTest` covers write/read round-trip, idempotent re-write, delete-clears, `FieldStorage::Data` explicit routing, and the `supportsQuery` branches. | ✓ |
| 6 | **FR-052** — `SqlSchemaHandler` accepts `$primaryBackendId` (default `sql-blob`); `sql-column` path skips `_data` emission with a TODO(WP05) marker. | `SqlSchemaHandler::__construct(... string $primaryBackendId = ReservedBackendIds::SQL_BLOB)`; conditional in `buildTableSpec()` skips `_data` when `=== ReservedBackendIds::SQL_COLUMN`. | ✓ |
| 7 | **Snapshot ordering integrity** — baseline (T015) pins LEGACY behaviour, not refactored output. | `BaselineSnapshotTest` is `#[CoversNothing]` and writes only through `SqlEntityStorage` / `splitForStorage()`; it asserts literal `'[]'` for empty, escaped `é` for Unicode, and `strpos`-based insertion-order checks (`z_field` before `a_field`). | ✓ |
| 8 | **JSON encoding flags** identical to legacy. | `SqlBlobBackend::write()` and `delete()` use `json_encode($extra, \JSON_THROW_ON_ERROR)` — same as `splitForStorage()`. No `JSON_UNESCAPED_UNICODE` / `JSON_UNESCAPED_SLASHES`. Verified by `baseline_data_json_uses_no_unescaped_flags`. | ✓ |
| 9 | **Field iteration order in `_data`** — per-field writes preserve insertion order. | `PostRefactorTest::data_json_bytes_are_byte_identical_after_refactor()` writes fields in the same order as the legacy entity values and compares raw `_data` bytes. Test passes. | ✓ |
| 10 | **Coordinator integration** — backend reachable via `BackendRegistrar` (per WP01/WP02 wiring). | `PostRefactorTest::makeRegistrar()` constructs a real `BackendRegistrar` with the new backend through an emitted `IsFrameworkBackendProviderInterface` provider. Registrar `build()` succeeds. | ✓ |
| 11 | **Canonical pipeline preserved** — Entity → Repository → Coordinator → backend → DBAL; no PDO bypass. | `SqlBlobBackend` injects `DatabaseInterface` and uses `select/update` query builder; no `\PDO`. Matches `.claude/rules/entity-storage-invariant.md`. | ✓ |
| 12 | **Namespace** — `Waaseyaa\EntityStorage\Backend`, not `Waaseyaa\Entity\Storage`. | File path + namespace declaration. | ✓ |
| 13 | **Layer rule** — L1, no upward imports. | `bin/check-package-layers` clean. Imports: `Database`, `Entity`, `Field`, `Foundation\Log` — all L0/L1. | ✓ |
| 14 | **`@api` on every new public symbol.** | `@api` on `SqlBlobBackend` class docblock. (Class-level annotation covers public surface per project convention used elsewhere in WP01/WP02.) | ✓ |
| 15 | **No `psr/log`, no `Illuminate\*`, `declare(strict_types=1)`, `final class` by default.** | `Waaseyaa\Foundation\Log\LoggerInterface` + `NullLogger` used. `final class SqlBlobBackend`. `declare(strict_types=1)` present. | ✓ |
| 16 | **Scope discipline** — no spec §1.2 / §2.2 non-goals leak. | No revision logic, no sql-column impl, no vector backend, no moderation, no migrations, no admin UI. The `sql-column` branch is a documented stub for WP05. | ✓ |

---

## Byte-identity gate assessment (the FR-008 question)

This was the deepest scrutiny target. The gate is **structurally
correct**:

- **Raw-bytes comparison.** `PostRefactorTest` uses `assertSame` on the
  raw `_data` string returned from a direct `SELECT`. No
  `assertJsonStringEqualsJsonString` (which normalizes), no decoded
  array comparison, no fuzz tolerance.
- **Baseline pinned to legacy behaviour.** `BaselineSnapshotTest` is
  `#[CoversNothing]` and exercises only the legacy `SqlEntityStorage`
  path. Its expected values are literals (`'[]'`, escaped `é`,
  ordering via `strpos`), not derived from the new backend — so the
  baseline cannot drift to match a buggy refactor.
- **Empty-case pinned.** The `empty_data_is_byte_identical_between_paths`
  test asserts `'[]'` literally (PHP `json_encode([])` artifact), then
  asserts byte equality across paths. This is the exact failure mode
  the implementer flagged in their commit message — and the test
  catches it.

**One non-blocking nuance:** in
`data_json_bytes_are_byte_identical_after_refactor`, Path B seeds the
base row via `SqlEntityStorage::save()` (which writes `_data='[]'`
through legacy `splitForStorage()`) and then overlays per-field writes
through `SqlBlobBackend`. So the test proves that the new backend,
applied *on top of* a legacy-seeded row, produces byte-identical output
to the pure legacy path. That is the right contract for WP03's
stated scope ("lift the JSON-blob persistence behavior") — the actual
write-path swap is reserved for WP06. A future WP06 review should
verify a separate test where Path B uses **only** the new backend for
the entire write, with no `splitForStorage()` seeding. Calling out for
the WP06 reviewer; not blocking WP03.

---

## Legacy `splitForStorage()` status

**Still in use, intentionally.** `SqlEntityStorage::save()` at line 201
calls `$this->splitForStorage($baseValues)` as the primary write path.
The new `SqlBlobBackend` is contract-conformant and behavior-pinned,
but is **not** yet routed through the coordinator for production
writes. This is acceptable for WP03 because:

1. The WP body says "**Lift** the JSON-blob persistence behavior" —
   i.e. extract and mirror, not replace.
2. WP06 ("coordinator write-path swap") is explicitly the dependent WP
   for this swap (per topology: WP06 → WP03, WP05).
3. The byte-identity gate exists precisely to keep the new backend in
   lockstep with the legacy method until the swap lands.

The legacy method is **live code**, not dead code. The dead-code audit
correctly does not flag it. Once WP06 swaps the write path, the
legacy method becomes a candidate for deletion in WP06's review (with
a final byte-identity re-run before removal).

---

## Gate spot-checks (re-run in lane worktree)

```
composer cs-check       → clean (no files to fix; 0 fixers run)
composer phpstan        → 1209/1209, no errors
bin/check-package-layers → OK — composer.json + PHP file-level
bin/check-composer-policy → OK
./vendor/bin/phpunit packages/entity-storage/tests/Integration/BehaviorIdentity/
                        → 19/19 passing, 37 assertions, 0 failures
```

Implementer report (384/384 passing, all gates clean) is consistent
with what I see on the lane.

---

## Non-blocking observations (carry-forward, not change requests)

1. **WP06 must add a "pure-Path-B" byte-identity test.** When WP06
   swaps the write path so `SqlEntityStorage::save()` routes through
   the coordinator → `SqlBlobBackend`, the existing T016 gate becomes
   self-referential (both paths become the same code). Add a frozen
   golden-file snapshot of the WP03-era legacy `_data` output for the
   same fixture inputs, and assert WP06's pure-new-path output equals
   that frozen string. Without it, WP06 can ship "byte-identical
   compared to itself" — useless.

2. **`@api` granularity.** Class-level `@api` on `SqlBlobBackend`
   matches the convention used by WP01/WP02 in this mission, so
   accepted. Charter §5.3 will eventually want per-method `@api` on
   stable surface — track at mission-level acceptance, not here.

3. **`delete()` "clears the entire `_data` blob"** is correct per the
   contract docstring, but worth flagging for WP06: when the
   coordinator deletes a single field on an entity that has multiple
   blob-stored fields, `SqlBlobBackend::delete()` currently wipes
   *all* of them. The contract may need to evolve to "delete one
   field's key from `_data`" once WP06 wires per-field
   coordinator-driven deletes. Not a WP03 defect — the WP body says
   the backend "operates on a single field at a time" but does not
   specify the delete granularity, and the current implementation
   matches the legacy "drop and re-insert blob" pattern.

---

## Verdict: APPROVED

WP03 ships exactly what the WP body asked for — `SqlBlobBackend`,
byte-identity gate, schema-handler `$primaryBackendId` parameter — and
the FR-008 gate is structurally sound. The legacy `splitForStorage()`
remaining as the live write path is intentional and correct for this
WP's scope.

Move WP03 → approved.
