# Data Model: Single-Entity Work Surface

**Mission**: `single-entity-work-surface-01KQ7M1P`

This document fixes the shapes of every persisted entity, value object, and registry-resident structure introduced or modified by this mission. Implementation tasks must conform to these shapes; deviations require a spec amendment.

## 1. `Attachment` content entity (`waaseyaa/attachment`, L2)

A persisted entity attached to any parent entity, with at most one `is_active = true` per parent.

### PHP class

```php
namespace Waaseyaa\Attachment;

use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Attribute\ContentEntityKeys;

#[ContentEntityKeys(
    id: 'id',
    uuid: 'uuid',
    label: 'filename',
)]
final class Attachment extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct($values, 'attachment', [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'filename',
        ]);
    }
}
```

The class itself carries no domain methods — accessors come from `ContentEntityBase::get('field_name')`. Repository ops live on `AttachmentRepository`, not on the entity (consistent with `.claude/rules/entity-storage-invariant.md`).

### Storage schema

`SqlSchemaHandler` config (`packages/attachment/src/Schema/AttachmentSchema.php`):

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | INTEGER | PK, AUTOINCREMENT | Standard entity ID |
| `uuid` | VARCHAR(36) | UNIQUE, NOT NULL | Standard entity UUID |
| `parent_entity_type` | VARCHAR(64) | NOT NULL | e.g. `'node'`, `'taxonomy_term'` |
| `parent_entity_id` | VARCHAR(255) | NOT NULL | Stringified entity ID (supports both int and UUID parents) |
| `is_active` | BOOLEAN | NOT NULL DEFAULT 0 | At most one true per parent |
| `created_at` | INTEGER | NOT NULL | Unix timestamp |
| `updated_at` | INTEGER | NOT NULL | Unix timestamp |
| `_data` | TEXT | (auto by `SqlSchemaHandler`) | JSON blob: `filename`, `content_type`, `size`, `storage_uri`, `checksum` |

Indexes:
- Primary key on `id`.
- Unique on `uuid`.
- Composite on `(parent_entity_type, parent_entity_id)` — list-by-parent queries.
- Composite on `(parent_entity_type, parent_entity_id, is_active)` — active-attachment lookup.

### Validation invariants

- `parent_entity_type` non-empty.
- `parent_entity_id` non-empty.
- `is_active` is the boolean `true` or `false`, never null.
- At any committed state: `count(*) WHERE parent_entity_type = X AND parent_entity_id = Y AND is_active = true` is in `{0, 1}` (NFR-010).

### State transitions

| From | Event | To | Side effect |
|---|---|---|---|
| `is_active = false` | `setActive(this.id)` | `is_active = true` | All siblings (same parent) → `is_active = false` in same transaction |
| `is_active = true` | `setActive(other_sibling.id)` | `is_active = false` | Implicit — cleared by sibling's `setActive` |
| (any) | `delete(this.id)` | (removed) | Row deleted; siblings unchanged |

There is no explicit "deactivate" transition. Active state is mutually exclusive within a parent group.

## 2. `FieldDefinition` (enriched) (`waaseyaa/field`, L1)

Existing `final readonly` value object, extended with two optional properties.

### Constructor (post-mission)

```php
public function __construct(
    private string $name,
    private string $type,
    // ... existing 14 parameters unchanged ...
    private FieldStorage $stored = FieldStorage::Column,
    private ?FieldTypeManager $fieldTypeManager = null,
    // NEW:
    private string $group = '',
    private array $promptAliases = [],   // list<string>
) {}
```

### New getters

```php
public function getGroup(): string;
public function getPromptAliases(): array;  // list<string>
```

Both added to `FieldDefinitionInterface`.

### Semantics

- `group: ''` (empty string) means "no group" — the renderer treats it as ungrouped.
- `promptAliases: []` (empty array) means "no aliases declared" — F5 matches the field's `name` only (after normalization).
- Aliases are stored verbatim; normalization is applied at match time.
- The constructor performs no alias validation; the `BundleTemplateCompiler` (Q2 below) checks uniqueness across a `(entity_type, bundle)` set and throws on collision at boot.

## 3. `BundleTemplate` and `FieldTemplate` attributes (`waaseyaa/field`, L1)

PHP 8.4 attributes. Class-level + repeatable property/method-level.

### `BundleTemplate`

```php
namespace Waaseyaa\Field\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class BundleTemplate
{
    public function __construct(
        public string $entityType,   // e.g. 'node'
        public string $bundle,       // e.g. 'article'
    ) {}
}
```

### `FieldTemplate`

```php
namespace Waaseyaa\Field\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class FieldTemplate
{
    /**
     * @param list<string> $promptAliases
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label = '',
        public string $group = '',
        public array $promptAliases = [],
        public bool $required = false,
        public bool $readOnly = false,
    ) {}
}
```

### Usage example

```php
namespace App\Templates;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

#[BundleTemplate(entityType: 'node', bundle: 'article')]
final class ArticleTemplate
{
    #[FieldTemplate(key: 'title', type: 'string', label: 'Title', promptAliases: ['title', 'headline'])]
    public string $title;

    #[FieldTemplate(key: 'body', type: 'string_long', label: 'Body', group: 'content', promptAliases: ['body', 'body text', 'content'])]
    public string $body;
}
```

The class need not be instantiable in production — it's a metadata carrier. Properties may be public for readability; the compiler reads attributes via reflection.

### Compiler output

`BundleTemplateCompiler` (new in `waaseyaa/field`):
1. Scans classes via `PackageManifestCompiler` for `#[BundleTemplate]`.
2. For each class, reads all `#[FieldTemplate]` attributes (on properties or methods, in declaration order).
3. Constructs `FieldDefinition` instances with `name = key`, `type = type`, `label`, `group`, `promptAliases`, `required`, `readOnly`, `targetEntityTypeId = $bundleTemplate->entityType`, `targetBundle = $bundleTemplate->bundle`.
4. Calls `FieldDefinitionRegistry::registerBundleFields($entityType, $bundle, $fields)`.
5. Validates: `(entity_type, bundle, key)` triples are unique; alias uniqueness within `(entity_type, bundle)`.
6. Caches the compiled list (cache invalidation tied to `PackageManifest` regeneration).

## 4. `FormFieldDescriptor` (`waaseyaa/field`, L1)

Readonly value object emitted by F6 for each registered field on a bundle.

```php
namespace Waaseyaa\Field\Form;

final readonly class FormFieldDescriptor
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public string $group,
        public mixed $value,
        public bool $readOnly,
        public bool $required,
        public array $errors = [],
    ) {}
}
```

### Semantics

- `name` matches `FieldDefinition::getName()`.
- `type` is the registered field type (e.g. `'string'`, `'string_long'`, `'entity_reference'`).
- `label` is the user-facing label (falls back to `ucfirst($name)` if empty).
- `group` is the optional grouping key (empty string = ungrouped).
- `value` is the entity's current value for this field, or null if unset.
- `readOnly` is `true` if `FieldAccessPolicyInterface::fieldAccess(...)` returned `Forbidden` for `update`, OR if `FieldDefinition::isReadOnly()` is true. Either source forces read-only.
- `required` mirrors `FieldDefinition::isRequired()`.
- `errors` is empty in the normal "render the form" case. Reserved for future "render with validation errors" use cases.

### Builder contract

```php
namespace Waaseyaa\Field\Form;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Access\AccountInterface;

final class FormDescriptorBuilder
{
    public function __construct(
        private readonly FieldDefinitionRegistryInterface $registry,
        private readonly ?\Waaseyaa\Access\EntityAccessHandler $accessHandler = null,
    ) {}

    /**
     * @return list<FormFieldDescriptor>
     */
    public function build(EntityInterface $entity, string $bundle, ?AccountInterface $account = null): array;
}
```

- Pulls fields via `FieldDefinitionRegistry::bundleFieldsFor($entity->getEntityTypeId(), $bundle)`.
- Order matches the registry's iteration order (registration order from the compiler).
- For each field:
  - Resolves value via `EntityInterface::get($field->getName())`.
  - If `$accessHandler` and `$account` are both provided, runs `fieldAccess` for `'update'`; sets `readOnly = true` if `Forbidden`. Otherwise uses `FieldDefinition::isReadOnly()` only.

## 5. `ImportResult` (`waaseyaa/structured-import`, L3)

Readonly value object returned by every `StructuredImporterInterface` implementation.

```php
namespace Waaseyaa\StructuredImport;

final readonly class ImportResult
{
    /**
     * @param array<string, string> $matched              field-key => value
     * @param list<UnmatchedRow> $unmatched
     * @param list<string> $errors
     */
    public function __construct(
        public array $matched,
        public array $unmatched,
        public array $errors,
    ) {}
}

final readonly class UnmatchedRow
{
    public function __construct(
        public string $prompt,
        public string $value,
    ) {}
}
```

### Semantics

- `matched`: keys are `FieldDefinition::getName()` values; values are the raw cell text from the importer (no type coercion — F5 doesn't know the target type).
- `unmatched`: rows whose normalized prompt did not match any registered alias for the `(entity_type, bundle)`.
- `errors`: human-readable strings safe to surface in admin UIs (FR-020). Examples: `"No table found"`, `"Row 3 has 3 columns, expected 2"`, `"Skipped second 2-column table at line 17"`.

`matched` and `unmatched` are never both empty unless the document had no parseable content (in which case `errors` carries an explanation).

## 6. `StructuredImporterInterface` (`waaseyaa/structured-import`, L3)

```php
namespace Waaseyaa\StructuredImport;

interface StructuredImporterInterface
{
    /**
     * Parse a payload and return matched/unmatched/errors.
     *
     * @param string $payload Raw document content (UTF-8).
     * @param string $entityTypeId Target entity type for alias matching.
     * @param string|null $bundle Target bundle; null means use entity_type as implicit single bundle.
     */
    public function import(string $payload, string $entityTypeId, ?string $bundle = null): ImportResult;
}
```

Single method. Implementations are stateless (no per-import mutable state visible across calls).

## 7. `EntityDeepLinkRouteBuilder` (`waaseyaa/routing`, L4)

Already covered in research.md Q3. For data-model purposes:

```php
final readonly class EntityDeepLinkRouteBuilder
{
    public function __construct(
        public string $segment,
        public string $entityTypeId,
    ) {}

    public function controller(string|callable $controller): RouteBuilder;

    public static function for(string $segment, string $entityTypeId): self;
}
```

No new persisted state; this is a routing-time helper.

## 8. `FieldAutoSaveController` request/response shape (`waaseyaa/api`, L4)

Not persisted state, but worth pinning shapes here for contract completeness. Full details in `contracts/`.

**Request**:
- Method: `PUT`
- Path: `{api_segment}/{entityType}/{id}/field/{key}`
- `Content-Type: application/json` (other types → 415)
- Body: `{"value": "<string>"}` — exactly one key. Bodies with extra keys are rejected with 422.
- Body size: ≤ 64 KiB by default (NFR-002), configurable per-route via `_max_body_bytes` route option.

**Response (success)**:
- 200 OK
- `Content-Type: application/json`
- Body: `{"data": {"id": "<id>", "type": "<entityType>", "attributes": {"<key>": "<persisted-value>"}}}`

**Errors**:
- 401 / 403: standard auth/access response shape.
- 404: entity not found, OR field key not registered for the entity's bundle.
- 415: wrong content type.
- 422: body too large, OR malformed JSON, OR `value` key missing/non-string.

Idempotency: identical PUTs converge to the same persisted state. Multiple PUTs of the same `(entity_id, key, value)` triple update only `updated_at`.

## Cross-entity invariants

- **Attachment ⇄ parent entity**: deleting a parent entity does **not** automatically delete attachments. This is intentional — attachments may need orphan-recovery flows. A future cascade-delete event subscriber is out of scope.
- **FieldDefinition ⇄ FieldTemplate**: every `#[FieldTemplate]` attribute produces exactly one `FieldDefinition` registered in `FieldDefinitionRegistry`. The two are not separate registries — they are one registry with two registration paths (imperative + attribute-driven). Compiler validates no duplicates between paths.
- **FormFieldDescriptor ⇄ FieldDefinition**: descriptors are derived per-request from the registry. They are not cached, not persisted. Any change to `FieldDefinition` reflects on the next call to `FormDescriptorBuilder::build()`.

## Out-of-scope data shapes (explicit)

- File byte storage (S3 keys, filesystem paths) — `Attachment.storage_uri` is opaque.
- Soft delete / trash for attachments — direct delete only.
- Versioning / history of auto-saved field values — relies on `EntityRepository`'s revision support, not added by this mission.
- Multi-language support for `FormFieldDescriptor.label` — labels are single-string today; i18n is the consumer's concern.
