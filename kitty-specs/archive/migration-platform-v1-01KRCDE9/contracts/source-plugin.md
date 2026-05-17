# Contract — Source Plugin

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Spec sections:** §3.1 (FR-001, FR-002, FR-007, FR-008, FR-009), §5.1, §10.1.
**Owning WPs:** WP01 (interface + DTOs), WP10 (conformance).
**Charter anchor:** §5.8 (new — Migration platform).

This document is the normative contract for `SourcePluginInterface`, its companion DTOs (`SourceRecord`, `SourceId`), and the conformance gate. Third-party source-reader package authors implement against this contract.

---

## Interface

```php
namespace Waaseyaa\Migration\Plugin;

use Waaseyaa\Migration\DTO\SourceRecord;
use Waaseyaa\Migration\IdMap\SourceId;

/**
 * @api
 *
 * A source plugin is a streaming reader from a foreign system (CSV, WordPress WXR,
 * Drupal database, etc.) into the Waaseyaa migration runner.
 */
interface SourcePluginInterface
{
    /** Reserved-namespace policy applies. See {@see \Waaseyaa\Migration\Plugin\ReservedPluginIds}. */
    public function id(): string;

    /** @return 'stable'|'experimental' */
    public function stability(): string;

    /**
     * MUST be a lazy iterable (generator or equivalent). MUST NOT eager-load
     * the full source dataset into memory.
     *
     * @return iterable<SourceRecord>
     */
    public function records(): iterable;

    /**
     * Compute the stable identity for a record. Determinism required: same
     * record content MUST yield identical SourceId across PHP versions,
     * locales, and machines.
     */
    public function sourceIdFor(SourceRecord $record): SourceId;

    /**
     * Total record count when pre-computable; null when streaming and unknown.
     * MUST NOT return negative or NaN-equivalent values.
     */
    public function count(): ?int;
}
```

### `SourceRecord` DTO

```php
namespace Waaseyaa\Migration\DTO;

/**
 * @api
 *
 * A read-only snapshot of one record from the source. Field names + values are
 * source-defined; the runner does not interpret them until process plugins fire.
 */
final readonly class SourceRecord
{
    public function __construct(
        public string $sourceType,
        /** @var array<string, mixed> */
        public array $fields,
    ) {}
}
```

### `SourceId` DTO

```php
namespace Waaseyaa\Migration\IdMap;

/**
 * @api
 *
 * Stable identity for a source record. Hash is sha256 of canonical form
 * (sourceType + JSON-encoded sorted-key associative array of $keys).
 */
final readonly class SourceId
{
    public function __construct(
        public string $sourceType,
        /** @var array<string, scalar|null> $keys */
        public array $keys,
    ) {}

    /**
     * Canonical form:
     *   sha256(sourceType . '|' . json_encode(ksort($keys, SORT_STRING),
     *     JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
     *
     * Key values coerced to string before encoding.
     */
    public function hash(): string;
}
```

---

## Semantic invariants

1. **Streaming** (FR-002, spec §5.1). `records()` MUST be a lazy iterable. Implementations MUST NOT call `iterator_to_array()` internally or build a populated array result.
2. **Determinism** (FR-027). `sourceIdFor($record)` and `SourceId::hash()` MUST be deterministic for fixed input. Calling either twice in the same process MUST yield identical results. Calling across processes / machines / PHP minors MUST also yield identical results.
3. **Re-entrancy** (FR-037). Calling `records()` twice on the same plugin instance MUST yield the same records in the same order. Resume semantics depend on this.
4. **Memory bound.** Peak memory while iterating a 50 MB fixture MUST stay under 50 MB during the conformance run (FR-051).
5. **Count contract.** `count()` returns `null` OR a non-negative `int`. No `PHP_INT_MAX`, no negative, no NaN. (FR-002.)
6. **Stability label.** `stability(): 'experimental'` causes the runner to emit a structured warning on the `migration.deprecation` channel on first record encountered per process (FR-009). `stability(): 'stable'` is silent.

---

## Error conditions

| Error | When | Type | Code |
|---|---|---|---|
| Source unreachable / I/O failure during `records()` | streaming I/O error | `SourceReadException` | `source_io_error` |
| Source record malformed / unparseable | a record fails to parse mid-stream | `SourceReadException` | `source_parse_error` |
| `sourceIdFor()` cannot derive a stable id | key fields missing on record | `SourceReadException` | `source_id_undefined` |

`SourceReadException` carries `(string $code, string $message, ?int $recordPosition, ?\Throwable $previous)`. The runner records `error_code` + `error_message` in `migration_run_state` and either continues (default) or halts (`--halt-on-error`, FR-047).

---

## Conformance requirements (WP10)

`SourceConformanceTestCase` is an abstract `TestCase`. Subclasses provide a fixture plugin under test. The base class runs the following assertions; failure of any is a hard gate.

| # | Test | Spec |
|---|---|---|
| C1 | `records()` returns a `\Generator` or implements `\IteratorAggregate`/`\Iterator` with no internal array buffering. | FR-002, §10.1 |
| C2 | Iterating a fixture larger than 50 MB keeps `memory_get_peak_usage(true)` under 50 MB throughout. | FR-051, §10.1 |
| C3 | `sourceIdFor()` is deterministic across two invocations on the same record. | FR-027, §10.1 |
| C4 | `SourceId::hash()` is byte-identical across two invocations and across two `SourceId` instances constructed with the same `(sourceType, keys)`. | FR-027 |
| C5 | `count()` returns either `null` or a non-negative `int`. | FR-002 |
| C6 | Calling `records()` twice on the same plugin yields the same record sequence (re-entrancy for resume). | FR-037 |
| C7 | A source plugin reporting `stability() === 'experimental'` emits exactly one `migration.deprecation` log line per run. | FR-009 |
| C8 | A simulated I/O failure during `records()` raises a `SourceReadException` (not a generic `\Throwable`). | FR-045 |

### Test surface

`SourceConformanceTestCase` lives under `packages/migration/testing/` (autoload-dev only). Subclasses MUST implement:

```php
abstract protected function buildPluginUnderTest(): SourcePluginInterface;
abstract protected function buildLargeFixturePath(): string;     // ≥ 50 MB
abstract protected function buildSmallFixturePath(): string;     // for re-entrancy + determinism
```

---

## Reserved-id policy

`ReservedPluginIds::SOURCE_RESERVED` is currently empty for source plugins — the framework reserves no source-plugin ids (this differs from process plugins, where six ids are reserved). Source plugins ship as separate composer packages (`waaseyaa-migrate-source-wordpress`, future `waaseyaa-migrate-source-drupal-7`, etc.) and choose their own ids. Recommended convention: `<vendor>_<system>_<entity>` (e.g. `wordpress_post`, `drupal7_node`).

Collisions between source plugins still raise `MigrationPluginCollisionException` at boot per FR-008.

---

## Out of scope (FR boundaries)

- **Cross-source diffing.** A source plugin reports records; it does NOT diff against a previous run. Idempotency is the runner's job via `source_record_hash` (FR-031).
- **Field-level metadata** (column types, nullability). Source records are unstructured maps. Process plugins do the typing (e.g. `TypeCoerceProcessor`).
- **Authentication/authorization** to the foreign source. Each source plugin handles its own auth (e.g. WordPress XML file path, Drupal DB credentials). The framework does NOT prescribe an auth shape.
