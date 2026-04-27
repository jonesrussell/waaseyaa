# Phase 0 Research: Single-Entity Work Surface

**Mission**: `single-entity-work-surface-01KQ7M1P`
**Date**: 2026-04-27

All findings grounded in code reads of the existing `packages/` tree at HEAD (post charter merge `1d7a6a63`).

## Q1 — `FieldDefinition` constructor evolution

### Decision

**Append two new constructor parameters at the end** of `Waaseyaa\Field\FieldDefinition`:

```php
public function __construct(
    private string $name,
    private string $type,
    private int $cardinality = 1,
    private array $settings = [],
    private string $targetEntityTypeId = '',
    private ?string $targetBundle = null,
    private bool $translatable = false,
    private bool $revisionable = false,
    private mixed $defaultValue = null,
    private string $label = '',
    private string $description = '',
    private bool $required = false,
    private bool $readOnly = false,
    private array $constraints = [],
    private FieldStorage $stored = FieldStorage::Column,
    private ?FieldTypeManager $fieldTypeManager = null,
    private string $group = '',                    // NEW
    private array $promptAliases = [],              // NEW (list<string>)
) {}
```

Add corresponding getters: `getGroup(): string` and `getPromptAliases(): array`. Add both to `FieldDefinitionInterface`.

### Rationale

- Class is `final readonly`; subclassing is forbidden by design. Adding properties via constructor is the only path.
- Existing call sites use named arguments (PHP 8.4 idiom). Appending parameters at the end with default values keeps every existing call site compiling.
- `group: ''` (empty string, not `null`) avoids `?string` nullable plumbing for a field that semantically defaults to "no group". Empty string sentinels are already used elsewhere in this constructor (`label: ''`, `description: ''`, `targetEntityTypeId: ''`).
- `promptAliases: []` is a `list<string>` per PHPStan generic. Empty array means "no aliases registered; matching by field name only."

### Alternatives considered

- **Builder pattern** — adds a class, doesn't simplify any call site, breaks the readonly idiom. Rejected.
- **`array $extras`** carrying loose metadata — soft typing, no IDE support, would be a step backward from the existing strongly-typed constructor. Rejected.
- **Separate `FieldTemplate` value object** referenced by name from `FieldDefinition` — creates two-source-of-truth (the dual-state bug pattern CLAUDE.md explicitly forbids). Rejected during planning interrogation Q1.

### Spec impact

- FR-003, FR-004 unaffected. The attributes feed `FieldDefinition` constructor calls; no separate registry needed.
- UPGRADING.md gets one entry: "Adding fields with the deprecated positional-argument call style? Switch to named arguments — `new FieldDefinition(name: '...', type: '...', label: '...')` — or pad with `[]` / `''` for the new positional slots." DIR-003 permits the breaking change; DIR-001 mandates the announcement.

## Q2 — `PolicyAttribute` discovery in new packages

### Decision

**No changes needed.** `Waaseyaa\Access\Gate\PolicyAttribute` is auto-discovered via `AbstractKernel::discoverAccessPolicies()` from any `#[PolicyAttribute(...)]`-decorated class registered through PSR-4 in any package.

### Rationale

- `PolicyAttribute::__construct` accepts `string|array $entityType` and stores `entityTypes` as an immutable array.
- `discoverAccessPolicies()` uses string-based attribute lookup (`'Waaseyaa\\Access\\Gate\\PolicyAttribute'`) rather than `::class` to maintain Layer 0 hygiene — works with any package's classes regardless of layer.
- Adding `Attachment` policy via `#[PolicyAttribute(entityType: 'attachment')]` in `packages/attachment/src/Policy/ParentDelegatedAccessPolicy.php` will be discovered automatically once the package is in `composer.json` autoload.
- Constraint C-011 (parent-delegated policy via existing resolution path) is satisfied without any access-package changes.

### Spec impact

None. Implementation can rely on existing access machinery.

## Q3 — `RouteBuilder` extensibility for F1

### Decision

**Compose, don't extend.** Create `Waaseyaa\Routing\EntityDeepLinkRouteBuilder` as a separate class that uses `RouteBuilder::create(...)` internally. Do **not** add static factory methods to `RouteBuilder` itself.

API shape:

```php
namespace Waaseyaa\Routing;

final readonly class EntityDeepLinkRouteBuilder
{
    public function __construct(
        private string $segment,           // e.g. '/edit'
        private string $entityTypeId,      // e.g. 'node'
    ) {}

    public function controller(string|callable $controller): RouteBuilder
    {
        return RouteBuilder::create($this->segment . '/' . $this->entityTypeId . '/{id}')
            ->controller($controller)
            ->entityParameter('id', $this->entityTypeId)  // existing RouteBuilder method
            ->methods('GET');
    }

    public static function for(string $segment, string $entityTypeId): self
    {
        return new self($segment, $entityTypeId);
    }
}
```

### Rationale

- `RouteBuilder` is `final` with `private __construct`. The fluent contract is owned by `RouteBuilder`; extending its API shape (e.g. via instance methods or static factories) couples `EntityDeepLinkRouteBuilder`'s evolution to `RouteBuilder`'s.
- Composition keeps F1 a one-class addition with no edits to `RouteBuilder`.
- `RouteBuilder::entityParameter()` already exists for the entity-resolution use case (verified at line 56-ish). The new helper is glue, not new mechanics.
- DIR-003 latitude is available, but using it where it's not needed is gratuitous churn.

### Alternatives considered

- **Add `RouteBuilder::entityDeepLink(string $segment, string $entityTypeId)`** — couples routing's surface to a higher-level concept. The L4 boundary stays cleaner with composition.
- **Inherit from `RouteBuilder`** — class is `final`. Removing `final` to enable inheritance changes `RouteBuilder`'s contract for everyone, for one consumer's benefit.

### Spec impact

FR-002 is satisfied with one new class and zero edits to existing routing files.

## Q4 — `JsonApiRouteProvider` integration for the auto-save endpoint

### Decision

**Register the auto-save route from `JsonApiRouteProvider`'s `register()` loop**, alongside the existing five CRUD routes per entity type. Use the existing `EntityTypeManagerInterface` injection — no new dependencies.

API change in `JsonApiRouteProvider`:

```php
foreach ($this->entityTypeManager->getEntityTypes() as $entityType) {
    $this->registerCrud($router, $entityType);
    $this->registerFieldAutoSave($router, $entityType);   // NEW
}
```

The new `registerFieldAutoSave` adds a `PUT {basePath}/{entityType}/{id}/field/{key}` route pointing at `FieldAutoSaveController::update`.

### Rationale

- `JsonApiRouteProvider` is the canonical place where per-entity-type API routes are registered. Adding the auto-save route there centralizes the "API routes per entity type" concept.
- A single new method keeps the diff small and reviewable.
- The auto-save endpoint's access semantics (entity policy + optional field policy) are consistent with the JSON:API `update` endpoint's; reusing the same handler infrastructure (`AccessChecker` via route options, `EntityAccessHandler` for field-level checks) keeps behavior aligned with C-011.

### Alternatives considered

- **Standalone `FieldAutoSaveRouteProvider`** — duplicates the entity-type iteration loop with no payoff.
- **Per-package opt-in** — would require consumers to register the auto-save route for each entity type they care about, defeating the "drop-in" goal of FR-005.

### Spec impact

FR-005 simplified: a single edit to `JsonApiRouteProvider::register()`, plus the new `FieldAutoSaveController`.

## Q5 — `Attachment` schema decisions

### Decision

**First-class columns** (queryable, indexed):
- `id` (int, PK)
- `uuid` (UUID, unique, indexed)
- `parent_entity_type` (string, indexed with `parent_entity_id`)
- `parent_entity_id` (string)
- `is_active` (bool, indexed when filtering active)
- `created_at`, `updated_at` (timestamps)

**`_data` JSON blob**:
- `filename`, `content_type`, `size`, `storage_uri`, `checksum`, and any future extensions.

### Rationale

- Active-attachment lookup is the hot path: `WHERE parent_entity_type = ? AND parent_entity_id = ? AND is_active = 1`. Composite index on `(parent_entity_type, parent_entity_id, is_active)` is essential.
- File metadata (`filename`, `content_type`, `size`, `storage_uri`) is rarely queried; storing in `_data` keeps the schema lean and adds zero new migrations when fields are added later.
- Pattern matches existing entity types: `node`, `user`, `taxonomy_term` all use this split per CLAUDE.md "_data JSON blob" gotcha.

### Spec impact

FR-009 specifies the entity contract; data-model.md formalizes the schema.

## Q6 — `setActive` atomicity

### Decision

**Direct `DatabaseInterface` transaction** wrapping two SQL UPDATEs. Do not rely on `UnitOfWork` (which is per-entity-batch) or chained `EntityRepository::save()` calls (which fire events between calls).

```php
public function setActive(string $attachmentId): void
{
    $this->database->transaction(function (DatabaseInterface $db) use ($attachmentId) {
        $attachment = $this->repository->find($attachmentId);
        if ($attachment === null) {
            throw new AttachmentNotFoundException($attachmentId);
        }
        // Clear active on all siblings in one statement.
        $db->update('attachment')
           ->set('is_active', false)
           ->condition('parent_entity_type', $attachment->getParentEntityType())
           ->condition('parent_entity_id', $attachment->getParentEntityId())
           ->execute();
        // Set active on the target.
        $db->update('attachment')
           ->set('is_active', true)
           ->condition('id', $attachmentId)
           ->execute();
    });
}
```

### Rationale

- Single transaction → "at most one active per parent" invariant holds under concurrent writes (NFR-010).
- Two UPDATEs are O(1) each — no N-row scan to clear siblings individually.
- Skips entity events for attachments not under direct mutation (the siblings being deactivated). If consumers need an event when an attachment loses active status, we can fire `AttachmentDeactivatedEvent` after commit; spec does not require this.
- Bypassing entity events for sibling clears is a deliberate design choice — events on every sibling for a single user action is noisy and inconsistent with what consumers actually want to subscribe to.

### Spec impact

FR-010, FR-019, NFR-010 satisfied. Concurrency test asserts the invariant.

### Alternatives considered

- **`UnitOfWork`** — designed for batch entity persistence with shared transactional boundary; not natively for "two arbitrary UPDATEs that happen to be related." Using it would require shoehorning.
- **Optimistic locking via `is_active` version column** — overkill for a single boolean flip.
- **Per-attachment update via `EntityRepository::save()`** — would fire `EntityEvent` on every sibling; expensive and noisy.

## Q7 — GFM table parser scope

### Decision

**Supported subset** for the default `GfmTableImporter`:

- Pipe-delimited rows (`| col1 | col2 |`)
- Optional leading/trailing pipe (`col1 | col2` also accepted)
- Header separator row required (`| --- | --- |`); alignment markers `:---`, `---:`, `:---:` are accepted but ignored
- Escaped pipe in cell content via `\|`
- Cells trimmed of leading/trailing whitespace
- Exactly 2 columns enforced — left = prompt, right = value

**Not supported** (rejected with error in `ImportResult.errors`):
- Multi-line cells (rows must be one line)
- Nested tables
- HTML cells
- Tables with > 2 columns (record error, skip table)
- Tables without a header separator (record error, skip table)

**Multiple tables in one document**: the first 2-column table is parsed; subsequent tables produce a non-fatal `errors` entry ("Skipped second 2-column table at line N"). Tables with non-2 columns are silently skipped because they're not addressed to F5.

### Rationale

- The "GitHub Flavored Markdown" spec is large; F5's job is to extract `(prompt, value)` pairs, not to be a CommonMark engine.
- 2-column constraint is the spec's definition of an importable table (FR-013). Tables with other shapes are user content, not import directives.
- In-house implementation per C-007 is feasible for this subset (~150 LOC parser).

### Spec impact

FR-013, FR-014, FR-020 satisfied with a small focused parser.

## Q8 — Prompt normalization edge cases

### Decision

**Unicode-safe normalization without transliteration**:

```php
function normalize(string $prompt): string
{
    $lowered = mb_strtolower($prompt, 'UTF-8');
    $collapsed = preg_replace('/\s+/u', ' ', $lowered);
    return trim($collapsed);
}
```

- `mb_strtolower` preserves diacritics (`É → é`, not `É → e`).
- `\s+/u` collapses Unicode whitespace (including non-breaking spaces, ideographic spaces).
- `trim` removes leading/trailing ASCII whitespace; combined with the regex above, internal whitespace is single-space.

### Rationale

- Constraint C-012 forbids fuzzy matching. Transliteration (`café` → `cafe`) is fuzzy by another name.
- Preserving diacritics matches multilingual content fidelity (relevant for Indigenous-language ingestion in adjacent Waaseyaa subsystems).
- `mb_strtolower` is the canonical PHP 8.4 unicode-aware lowercase.

### Spec impact

FR-013, C-012 satisfied. Same normalization applies on both sides of the alias match (declared aliases and inbound prompts).

## Summary — design decisions ready for Phase 1

| Question | Decision | Spec impact |
|---|---|---|
| Q1 FieldDefinition signature | Append `group`, `promptAliases` params with defaults | UPGRADING entry; FR-003/004 unblocked |
| Q2 Policy discovery | No access-package changes needed | C-011 satisfied trivially |
| Q3 RouteBuilder F1 helper | Compose, don't extend | One new file, zero edits |
| Q4 Auto-save route registration | Inside `JsonApiRouteProvider::register()` loop | FR-005 simplified |
| Q5 Attachment schema split | First-class: identity + parent + active + timestamps; `_data`: file metadata | FR-009 formalized |
| Q6 setActive atomicity | Direct DB transaction, two UPDATEs | NFR-010 implementation pinned |
| Q7 GFM scope | 2-column tables only, narrow CommonMark subset | FR-013 bounded |
| Q8 Prompt normalization | mb_strtolower + Unicode whitespace collapse + trim, no transliteration | C-012 honored |

All Phase 0 unknowns resolved. Phase 1 (data-model.md, contracts/, quickstart.md) can proceed without further user input.

## References

- `packages/field/src/FieldDefinition.php` — current `final readonly` shape (Q1 baseline)
- `packages/field/src/FieldDefinitionRegistry.php:113` — `registerBundleFields` API (Q2 target)
- `packages/access/src/Gate/PolicyAttribute.php` — class-level attribute (Q2)
- `packages/routing/src/RouteBuilder.php:20` — `final class`, `private __construct` (Q3)
- `packages/api/src/JsonApiRouteProvider.php` — auto-route registration loop (Q4)
- `packages/entity/src/Repository/EntityRepositoryInterface.php` — repository contract (Q5/Q6)
- `.claude/rules/entity-storage-invariant.md` — pipeline rules (Q5/Q6)
- `CLAUDE.md` "_data JSON blob" gotcha — schema split rationale (Q5)
- `kitty-specs/charter-greenfield-directive-01KQ7MN5` — DIR-003 (greenfield removal) merged at `1d7a6a63`, governs C-010
