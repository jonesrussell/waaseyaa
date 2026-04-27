# Contracts: Single-Entity Work Surface

PHP-level contracts (interfaces, attribute signatures, HTTP endpoint shapes) for each of the six primitives. Implementations must satisfy these contracts; deviations require a spec amendment.

This file consolidates contracts that would otherwise live in six separate files because the surfaces are tightly related and small enough to read in one pass.

---

## F1 — Entity deep-link route helper (`waaseyaa/routing`)

### Class

```php
namespace Waaseyaa\Routing;

final readonly class EntityDeepLinkRouteBuilder
{
    public function __construct(
        public string $segment,
        public string $entityTypeId,
    ) {}

    public static function for(string $segment, string $entityTypeId): self;

    public function controller(string|callable $controller): RouteBuilder;
}
```

### Behavior

- `for('/edit', 'node')->controller(MyController::class . '::view')` returns a `RouteBuilder` configured with:
  - Path `/edit/node/{id}`
  - GET method
  - Entity parameter `id` resolved against `EntityRepositoryInterface` for entity type `node`
  - Access policy applied via `EntityAccessHandler` before the controller runs
- The returned `RouteBuilder` is fully chainable — caller can add additional methods, requirements, and access options.
- 404-equivalent response when the entity is not found: returned as a Symfony `Response` with status 404 by the param converter; controller is not invoked.

### Acceptance

- `EntityDeepLinkRouteBuilder::for('/edit', 'node')->controller($c)->build()` produces a valid Symfony `Route`.
- Hitting `/edit/node/<missing-id>` returns 404 without invoking the controller.
- Hitting `/edit/node/<existing-id>` invokes the controller with the hydrated entity.
- Access policy is enforced — denied requests return 401/403 without invoking the controller.

---

## F2 — Field-template attributes & compiler (`waaseyaa/field`)

### Attributes

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class BundleTemplate
{
    public function __construct(
        public string $entityType,
        public string $bundle,
    ) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class FieldTemplate
{
    /** @param list<string> $promptAliases */
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

### `FieldDefinition` extension

Constructor gains two trailing parameters:

```php
private string $group = '',
private array $promptAliases = [],   // list<string>
```

`FieldDefinitionInterface` gains:

```php
public function getGroup(): string;
public function getPromptAliases(): array;   // list<string>
```

### Compiler contract

```php
namespace Waaseyaa\Field;

final class BundleTemplateCompiler
{
    public function __construct(
        private readonly FieldDefinitionRegistryInterface $registry,
        private readonly PackageManifestCompiler $manifestCompiler,
    ) {}

    public function compile(): void;   // discovers + registers; idempotent
}
```

### Acceptance

- A class decorated with `#[BundleTemplate(entityType: 'node', bundle: 'article')]` and three `#[FieldTemplate]`s on properties produces three `FieldDefinition` instances registered against `('node', 'article')` in declaration order.
- Each registered `FieldDefinition` carries the declared `label`, `group`, `promptAliases`.
- Duplicate `key` within a bundle: compiler throws `\InvalidArgumentException` at boot.
- Duplicate `promptAlias` (after normalization) within a bundle: compiler throws `\InvalidArgumentException` at boot.
- Mixing imperative `registerBundleFields()` calls with attribute-discovered fields for the same bundle: imperative wins for collisions; compiler logs a warning.

---

## F3 — Per-field auto-save endpoint (`waaseyaa/api`)

### HTTP contract

```
PUT {api_segment}/{entityType}/{id}/field/{key}
Content-Type: application/json

{"value": "<string>"}
```

| Status | Cause | Body |
|---|---|---|
| 200 | Success | `{"data": {"id": "<id>", "type": "<entityType>", "attributes": {"<key>": "<persisted-value>"}}}` |
| 401 | Not authenticated when route requires session | Standard error envelope |
| 403 | Authenticated but `AccessPolicyInterface::access('update')` returned `Forbidden`, OR `FieldAccessPolicyInterface::fieldAccess('update', key)` returned `Forbidden` | Standard error envelope |
| 404 | Entity not found, OR field key not registered for the entity's bundle | `{"errors": [{"status": "404", "code": "field_not_registered", "title": "..."}]}` for the field-key case; standard 404 otherwise |
| 415 | Content-Type is not `application/json` | Standard error envelope |
| 422 | Body exceeds size cap (default 64 KiB), OR JSON malformed, OR `value` missing/non-string | Standard error envelope; size cap echoed in error detail when applicable |

### Idempotency

- Multiple identical PUTs converge: persisted entity state matches the request body's `value`.
- The endpoint updates `updated_at` on every successful PUT; this is not idempotent in the strict "same byte-for-byte response" sense, but the entity's user-visible state is.

### Controller contract

```php
namespace Waaseyaa\Api\Controller;

final class FieldAutoSaveController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityAccessHandler $accessHandler,
        private readonly FieldDefinitionRegistryInterface $fieldRegistry,
        private readonly int $maxBodyBytes = 65536,   // 64 KiB default
    ) {}

    public function update(Request $request, string $entityType, string $id, string $key): Response;
}
```

### Acceptance

- 200 path: entity persisted, response shape matches above, integration test passes against SQLite.
- p95 latency ≤ 50 ms server-side (NFR-001) under nominal load (single field, < 4 KiB body, SQLite).
- Body size guard runs before full-body read (NFR-002 — "without buffering the full payload").

---

## F4 — Attachment repository (`waaseyaa/attachment`)

### Repository contract

```php
namespace Waaseyaa\Attachment;

final class AttachmentRepository
{
    public function __construct(
        private readonly EntityRepositoryInterface $entityRepository,
        private readonly DatabaseInterface $database,
    ) {}

    /**
     * @return list<Attachment>
     */
    public function listFor(string $parentEntityType, string $parentId): array;

    public function getActive(string $parentEntityType, string $parentId): ?Attachment;

    /**
     * Atomically clear `is_active` on all siblings of $attachmentId's parent,
     * then set `is_active = true` on $attachmentId.
     *
     * @throws AttachmentNotFoundException if $attachmentId does not exist.
     */
    public function setActive(string $attachmentId): void;

    public function save(Attachment $attachment): void;

    public function delete(string $attachmentId): void;
}
```

### Access policy

```php
namespace Waaseyaa\Attachment\Policy;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;

#[PolicyAttribute(entityType: 'attachment')]
final class ParentDelegatedAccessPolicy implements AccessPolicyInterface
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityAccessHandler $accessHandler,
    ) {}

    public function access(EntityInterface $entity, string $operation, ?AccountInterface $account): AccessResult;
}
```

Behavior: looks up the parent entity by `parent_entity_type` + `parent_entity_id`; delegates `view`/`update`/`delete` to the parent entity's policy via `EntityAccessHandler`. Returns `Neutral` if the parent entity does not exist (the handler's enforcement layer will deny via `isAllowed()`).

### Acceptance

- `setActive` invariant under 50 concurrent calls: exactly one row has `is_active = true` for the parent (NFR-010).
- `getActive(parent_type, parent_id)` returns the unique active attachment, or null.
- `listFor` returns all attachments for a parent in insertion order.
- A user who can `view` the parent entity can `view` its attachments; a user who can `update` the parent can `update` / `setActive` / `delete` its attachments.

---

## F5 — Structured importer (`waaseyaa/structured-import`)

### Interface

```php
namespace Waaseyaa\StructuredImport;

interface StructuredImporterInterface
{
    public function import(
        string $payload,
        string $entityTypeId,
        ?string $bundle = null,
    ): ImportResult;
}
```

### `ImportResult`

See [data-model.md § 5](../data-model.md). `matched: array<string, string>`, `unmatched: list<UnmatchedRow>`, `errors: list<string>`.

### Default implementation: `GfmTableImporter`

```php
namespace Waaseyaa\StructuredImport\Gfm;

final class GfmTableImporter implements StructuredImporterInterface
{
    public function __construct(
        private readonly FieldDefinitionRegistryInterface $registry,
        private readonly GfmTableParser $parser = new GfmTableParser(),
        private readonly PromptNormalizer $normalizer = new PromptNormalizer(),
    ) {}
}
```

### Parser scope (per research.md Q7)

Supported:
- Pipe-delimited rows; optional leading/trailing pipe
- Header separator row required (`| --- | --- |`)
- Alignment markers (`:---`, `---:`, `:---:`) accepted, ignored
- Escaped pipe in cell content via `\|`
- Exactly 2 columns

Not supported (record in `errors`, skip):
- > 2 columns
- Missing header separator
- Multi-line cells, nested tables, HTML cells
- Multiple 2-column tables: parse first, skip subsequent with non-fatal `errors` entries

### Prompt normalization

```php
function normalize(string $prompt): string
{
    return trim(preg_replace('/\s+/u', ' ', mb_strtolower($prompt, 'UTF-8')));
}
```

Applied to both declared aliases and inbound prompts before comparison. Diacritics preserved (no transliteration — C-012).

### Acceptance

- Input: `| Title | Hello |\n| --- | --- |\n| Body Text | Lorem |` against bundle with field `title` (alias `'title'`) and `body` (alias `'body text'`) → `matched = ['title' => 'Hello', 'body' => 'Lorem']`.
- Input with unknown prompt: prompt lands in `unmatched`, not silently dropped.
- Input with no table: `errors = ["No table found"]`, `matched = []`, `unmatched = []`.
- Input with malformed row: `errors = ["Row 3 has 3 columns, expected 2"]`, processing continues for remaining rows.
- Contract test exercises all of the above against a stub `FieldDefinitionRegistryInterface`.

---

## F6 — Form descriptor builder (`waaseyaa/field`)

### Value object

```php
namespace Waaseyaa\Field\Form;

final readonly class FormFieldDescriptor
{
    /** @param list<string> $errors */
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

### Builder

```php
namespace Waaseyaa\Field\Form;

final class FormDescriptorBuilder
{
    public function __construct(
        private readonly FieldDefinitionRegistryInterface $registry,
        private readonly ?\Waaseyaa\Access\EntityAccessHandler $accessHandler = null,
    ) {}

    /**
     * @return list<FormFieldDescriptor>
     */
    public function build(
        EntityInterface $entity,
        string $bundle,
        ?AccountInterface $account = null,
    ): array;
}
```

### Acceptance

- Given a bundle with three `#[FieldTemplate]`s declared in order `[title, body, summary]`, `build()` returns descriptors in that order.
- `value` for each descriptor matches `EntityInterface::get($name)`.
- `readOnly` is `true` iff (`FieldDefinition::isReadOnly()` is true) OR (`accessHandler` + `account` both non-null AND `fieldAccess('update', $name)` returned `Forbidden`).
- `label` defaults to `ucfirst($name)` when `FieldDefinition::getLabel()` is empty.
- No HTML, no Twig output, no string concatenation of markup. Pure value objects.

---

## Cross-cutting acceptance (Success Criterion 5)

A single integration test in `tests/Integration/Phase##/SingleEntityWorkSurfaceTest.php` exercises all six primitives in sequence:

1. Declare a `#[BundleTemplate]` class with 5 `#[FieldTemplate]`s.
2. Run `BundleTemplateCompiler::compile()`; assert registry has the 5 fields.
3. Register an `EntityDeepLinkRouteBuilder` route in a fixture `ServiceProvider`.
4. Hit the deep-link route; assert controller receives the hydrated entity.
5. PUT 5 auto-save round-trips against the registered fields; assert each persists.
6. Create 3 attachments; `setActive` on one; assert `getActive` returns it.
7. Import a markdown document with 5 prompts matching the field aliases; assert `matched` has 5 entries, `unmatched` empty, `errors` empty.
8. Build form descriptors for the entity; assert order, values, group structure, and that the active attachment is referenceable from the descriptors (via consumer wiring; F6 doesn't render attachments directly).

This test is the binding acceptance of the entire mission. If it passes against a fresh SQLite database, the mission ships.
