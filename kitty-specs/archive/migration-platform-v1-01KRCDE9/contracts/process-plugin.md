# Contract — Process Plugin

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Spec sections:** §3.1 (FR-003, FR-004, FR-007, FR-008, FR-009, FR-010), §5.2, §5.4.
**Owning WPs:** WP01 (interface + DTO), WP03 (six reserved-id concretes).
**Charter anchor:** §5.8 (new — Migration platform).

This document is the normative contract for `ProcessPluginInterface`, `ProcessContext`, the chain mechanic, and the reserved-id namespace.

---

## Interface

```php
namespace Waaseyaa\Migration\Plugin;

use Waaseyaa\Migration\DTO\ProcessContext;

/**
 * @api
 *
 * A process plugin transforms a single value during the migration runner's
 * field-mapping phase. Plugins are pure functions of (value, context); they
 * MUST NOT mutate the SourceRecord and MUST NOT touch external state except
 * via the bound lookup callable on ProcessContext.
 */
interface ProcessPluginInterface
{
    public function id(): string;

    /** @return 'stable'|'experimental' */
    public function stability(): string;

    /**
     * Transform a value. Output of one processor becomes input of the next
     * when chained (FR-010).
     */
    public function transform(mixed $value, ProcessContext $context): mixed;
}
```

### `ProcessContext` DTO

```php
namespace Waaseyaa\Migration\DTO;

use Waaseyaa\Migration\Definition\MigrationDefinition;
use Waaseyaa\Migration\IdMap\SourceId;

/**
 * @api
 *
 * Read-only context passed to every process plugin transform call.
 */
final readonly class ProcessContext
{
    public function __construct(
        public SourceRecord $record,
        public MigrationDefinition $migration,
        public string $destinationField,
        /**
         * Lookup callable bound to MigrationIdMap. Returns the WriteResult
         * for a SourceId from a prior migration, or null.
         *
         * @var \Closure(string $migrationId, SourceId $sourceId): ?WriteResult
         */
        public \Closure $lookup,
    ) {}
}
```

---

## Chain semantics (FR-010, spec §6.2)

The `MigrationDefinition::$process[$destField]` value may be:

- A `ProcessPluginInterface` instance — one processor.
- A `string` — interpreted as a source field name; `PassThroughProcessor` runs with that source field.
- An array — processors run in **array order**; output of position N is input of position N+1.

The runner constructs a chain executor per destination field:

```
$value = $record->fields[<source_field_name>] ?? null;
foreach ($chain as $processor) {
    $value = $processor->transform($value, $context);
}
$destinationRecord->fields[$destField] = $value;
```

Array order is the v0.x answer (Q3 resolution; research §2 Q3). No `Pipeline::after()/before()` cross-package ordering in v0.x.

---

## Lookup callable

`ProcessContext::$lookup` is bound to `MigrationIdMap::lookupDestination()` (FR-028). Use:

```php
$writeResult = ($context->lookup)('wp_users_to_accounts', new SourceId(
    sourceType: 'wordpress_user',
    keys: ['ID' => $record->fields['post_author']],
));
return $writeResult?->uuid;
```

`LookupProcessor` (reserved-id `lookup`, WP03) is the framework-provided concrete that wraps this pattern with declarative configuration:

```php
new LookupProcessor(
    sourceField: 'post_author',
    migration: 'wp_users_to_accounts',
    sourceType: 'wordpress_user',
    keyField: 'ID',
)
```

---

## Reserved-id namespace (spec §5.4)

The framework reserves these six process-plugin ids. Third-party packages MUST NOT register a plugin with any of them; doing so raises `MigrationPluginCollisionException` with the `reserved_id` flag at boot (FR-008).

| Id | Class | WP |
|---|---|---|
| `pass_through` | `PassThroughProcessor` | WP03 |
| `html_sanitize` | `HtmlSanitizeProcessor` | WP03 |
| `lookup` | `LookupProcessor` | WP03 |
| `concat` | `ConcatProcessor` | WP03 |
| `type_coerce` | `TypeCoerceProcessor` | WP03 |
| `default_value` | `DefaultValueProcessor` | WP03 |

App-defined process plugins use a non-reserved id. Recommended convention: `<vendor>_<purpose>` (e.g. `wordpress_shortcode_strip`, `minoo_locale_normalize`). The reservation policy mirrors ADR 010's backend-id namespace policy.

---

## Semantic invariants

1. **Purity.** `transform()` MUST be a pure function of `(value, context)`. No global state, no static mutation, no file/network I/O except via `$context->lookup`.
2. **No record mutation.** `$context->record` is `final readonly` — modifications are language-prevented. Plugins MUST treat it as input only.
3. **Type tolerance.** `transform()` receives `mixed` and returns `mixed`. Plugins that need typed input MUST coerce or raise `ProcessException` with a stable code; they MUST NOT type-hint `string $value` on the implementing method.
4. **Determinism.** For fixed `(value, context)`, the same plugin instance MUST return the same output. The conformance suite does not test this directly (process plugins are simpler than source plugins), but the FR-027 stability guarantee depends on it.
5. **Idempotency on re-run.** A chain run against the same `SourceRecord` MUST yield identical `DestinationRecord` field values. This is what makes `source_record_hash` comparison meaningful for change detection (FR-031).

---

## Error conditions

| Error | When | Type | Code |
|---|---|---|---|
| Input value rejects coercion | e.g. `TypeCoerceProcessor` cannot convert | `ProcessException` | `process_coerce_failure` |
| Required input is missing | e.g. `LookupProcessor` source field is null and `required: true` | `ProcessException` | `process_required_missing` |
| External lookup fails | `$context->lookup` returns null and required | `ProcessException` | `process_lookup_miss` |

`ProcessException` is per-record (caught by the runner, recorded in `migration_run_state`, default-continue per FR-046). Runner-level abort uses `MigrationAbortedException` instead.

---

## Conformance test surface

Process plugins do NOT have a dedicated conformance test base class (unlike source/destination). Reason: process plugins are simpler — the contract is `transform(mixed, ProcessContext): mixed`, and per-plugin behavior is plugin-specific. Each framework-provided process plugin (WP03) has its own unit test asserting its specific transformation.

Third-party process plugins SHOULD write their own unit tests but do NOT inherit a `ProcessConformanceTestCase` base. This is a deliberate v0.x simplification; revisit if community process plugins begin to misbehave (Q3 follow-up).
