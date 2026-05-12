# WP05 Review — Cycle 1 (APPROVED)

**Commit reviewed:** `feea8c2b3` "feat(WP05): sql-column backend, schema builder, query translator, type mapping"
**Lane:** `kitty/mission-entity-storage-v2-01KRCDDC-lane-a`
**Baseline:** `kitty/mission-entity-storage-v2-01KRCDDC`
**Reviewer:** Opus 4.7 (1M)

## Verdict

**APPROVED** with non-blocking observations. WP05 delivers the `sql-column`
backend, its schema builder, the §8.2 normative type mapping, the standalone
query translator, and a 14-test SqlColumn integration suite — without
disturbing the WP03 byte-identity gate or the WP01 `EntityQuery` marker.

## Criteria checklist

| # | Criterion | Result |
|---|---|---|
| 1 | `SqlColumnBackend` implements `FieldStorageBackendInterface`, `id()='sql-column'`, per-field DBAL CRUD; `supportsQuery` returns true for §8.2 types and false for `float_vector_<n>` / rerouted fields | ✓ |
| 2 | `SqlColumnSchemaBuilder` generates entity-keys + per-`FieldDefinition` columns, dialect dispatch via `TypeMapping`, decimal TEXT (SQLite) / `NUMERIC(p,s)` (Postgres), `float_vector_<n>` rejected at build time with `storedIn('vector')` hint | ✓ |
| 3 | Indexed fields emit `CREATE INDEX <table>_<field>_idx` via raw query after table creation; index existence verified in tests | ✓ |
| 4 | DBAL query builder used; SQLite in-memory tests via `DBALDatabase::createSqlite()`; Postgres-specific types covered via `TypeMapping` unit tests | ✓ |
| 5 | `TypeMapping::columnTypeFor(platform, fieldType, ?length, ?precision/?scale)` matches §8.2 row-for-row; translator standalone, EntityQuery still untouched | ✓ |
| 6 | Integration tests cover CRUD round-trip, indexed query, decimal lossless, datetime ISO-8601, float_vector rejection, supportsQuery semantics, §8.2 SQLite+Postgres coverage | ✓ |
| 7 | `SqlSchemaHandler` modification is minimal (single ensureTable branch + additive `$entityLevelFields` param, default `[]`); sql-blob path unchanged; BehaviorIdentity suite (19 tests) still green | ✓ |
| 8 | §8.2 normative type table — every row walked against `TypeMapping`; no drift | ✓ |
| 9 | No methods called on `EntityQuery`; `supportsQuery` inspects only field type + backend id | ✓ |
| 10 | Namespace `Waaseyaa\EntityStorage`; L1; `@api` on all new public symbols | ✓ |
| 11 | No `psr/log`, no `Illuminate\*`, no service locators; `declare(strict_types=1)`; `final class` default | ✓ |
| 12 | No scope creep — no revisions, no vector backend, no moderation, no mass migration | ✓ |

## §8.2 conformance walkthrough

Direct inspection of `TypeMapping::sqliteType()` and `TypeMapping::postgresType()`:

| Spec row | SQLite expected | Postgres expected | Code result |
|---|---|---|---|
| `string` | `TEXT` (or `VARCHAR(n)`) | `TEXT` / `VARCHAR(n)` if length | ✓ both branches present (SQLite ignores length, Postgres honors it) |
| `int` / `integer` | `INTEGER` | `INTEGER` | ✓ both aliases handled |
| `bigint` | `INTEGER` | `BIGINT` | ✓ |
| `bool` / `boolean` | `INTEGER` (0/1) | `BOOLEAN` | ✓ both aliases |
| `datetime` | `TEXT` (ISO 8601) | `TIMESTAMPTZ` | ✓ |
| `json` | `TEXT` | `JSONB` | ✓ |
| `uuid` | `TEXT` | `UUID` | ✓ |
| `text` | `TEXT` | `TEXT` | ✓ |
| `float` | `REAL` | `DOUBLE PRECISION` | ✓ |
| `decimal` / `numeric` | `TEXT` (lossless) | `NUMERIC(p,s)` | ✓ via `numericType($precision, $scale)` |
| `float_vector_<n>` | forbidden | forbidden | ✓ `\InvalidArgumentException` thrown before dispatch |

No drift. The fallback for unknown types (`'TEXT'`) is benign and only fires
for non-§8.2 inputs.

## BehaviorIdentity (WP03) regression check

`./vendor/bin/phpunit packages/entity-storage/tests/Integration/BehaviorIdentity/`
→ **Tests: 19, Assertions: 37, OK.** The byte-identity gate that WP03
established for the legacy `_data` JSON path still holds; the
`SqlSchemaHandler` edit is gated on `primaryBackendId === SQL_COLUMN` and the
`sql-blob` branch is untouched.

## `float_vector_<n>` rejection — defense in depth

Three independent guards verified:

1. **`TypeMapping::columnTypeFor`** — `preg_match('/^float_vector_\d+$/')`
   throws `\InvalidArgumentException` with explicit `storedIn('vector')` hint.
2. **`SqlColumnSchemaBuilder::buildTable` / `addFieldColumn`** —
   same regex, same exception, names the offending field and entity type and
   prints a copy-pasteable `FieldDefinition::create(...)->storedIn('vector')`
   suggestion.
3. **`SqlColumnBackend::supportsQuery`** — returns `false` for
   `float_vector_<n>` so a query routed here at runtime is also short-circuited.

## EntityQuery boundary and TODO(WP06)

- `git diff feea8c2b3^..feea8c2b3 -- packages/entity-storage/src/Query/EntityQuery.php` →
  empty. The marker interface from WP01 is untouched.
- `SqlEntityQuery.php` also unchanged.
- `SqlColumnQueryTranslator` is standalone — implementer explicitly notes it is
  not yet wired into `SqlColumnBackend` because `EntityQuery` does not yet
  expose operators. Commit message contains a bounded
  **`TODO(WP06): inject translator once EntityQuery exposes operators`** marker.
  Scope of the TODO is narrow and clearly assigned to WP06 (filter/sort/pagination
  contracts). No placeholder for "everything not done yet."
- `SqlColumnBackend::supportsQuery` is implemented purely from field type and
  `getBackendId()` — no method calls on `EntityQuery`. Correct.

## Platform discrimination

`TypeMapping::platformKey(\Doctrine\DBAL\Platforms\AbstractPlatform $platform)`
discriminates by lowercased class basename containing `sqlite` / `postgresql` /
`pgsql` / `postgres`, with SQLite as the safe-default fallback.

**Non-blocking observation:** the review prompt called out "brittle
string-matching on platform names is a smell." It is mildly smelly, but the
implementer's choice is defensible: it survives the DBAL 3 → 4 namespace
reshuffle (`Doctrine\DBAL\Platforms\PostgreSQL94Platform` →
`Doctrine\DBAL\Platforms\PostgreSQLPlatform` →
`Doctrine\DBAL\Platforms\PostgreSqlPlatform` in DBAL 4) without an
`instanceof` matrix. Acceptable for cycle 1; if a third platform (MySQL/MariaDB)
joins later, revisit with `instanceof` + a tested fallback chain. Not blocking.

## Gate spot-checks

| Gate | Result |
|---|---|
| `composer cs-check` | clean |
| `composer phpstan` | OK — no errors (1222 files) |
| `bin/check-package-layers` | OK — package layer constraints satisfied |
| `bin/check-composer-policy` | OK — Composer policy checks passed |
| `./vendor/bin/phpunit packages/entity-storage/tests/Integration/SqlColumn/` | 14 tests, 54 assertions, OK |
| `./vendor/bin/phpunit packages/entity-storage/tests/Integration/BehaviorIdentity/` | 19 tests, 37 assertions, OK |
| `./vendor/bin/phpunit packages/entity-storage/tests/` | 411 tests, 937 assertions, OK |

PHPUnit warnings present are pre-existing (no coverage driver, an abstract
contract test marker) and unrelated to WP05.

## Non-blocking observations (for WP06+)

1. **Index verification via `sqlite_master` instead of `EXPLAIN QUERY PLAN`.**
   Test comment correctly notes EXPLAIN QUERY PLAN is unreliable on empty
   tables without statistics. The `sqlite_master` lookup is the right call
   for cycle 1; consider adding an EXPLAIN-based assertion in WP06 once the
   suite seeds enough rows to make the planner statistics meaningful.
2. **`platformKey()` string-match.** See "Platform discrimination" above —
   revisit when adding MySQL/MariaDB support.
3. **`SqlSchemaHandler::$entityLevelFields` default `[]`.** The default is
   correct for sql-blob entity types, but a future WP that activates sql-column
   for an entity type without supplying fields would silently produce an empty
   schema. A defensive `\LogicException` when `primaryBackendId=sql-column`
   and `$entityLevelFields === []` would harden this; not required for WP05's
   scope.

None of the above blocks approval.

## Approval rationale

WP05 delivers exactly its slice — sql-column backend, schema builder, type
mapping, standalone translator, and tests — and does so without touching
EntityQuery, without disturbing the WP03 byte-identity gate, and without
upward scope creep into revisions or vector storage. §8.2 conformance is
exact. `float_vector_<n>` is rejected at three independent layers. All
gates green.

**Approving for the mission.**
