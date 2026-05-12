# Phase 1 Data Model: Entity Storage — Single-Axis Translations v1

**Mission:** M-006 / `01KRF0FQ0AA42F434JNAA56WFB`
**Date:** 2026-05-12
**Plan:** [`plan.md`](plan.md) · **Research:** [`research.md`](research.md)

Captures the domain shapes the mission lands. Three slices: **domain (PHP)**, **storage (SQL)**, **events**.

---

## Domain (PHP)

### `Waaseyaa\Entity\TranslatableInterface` — expanded

Existing minimal stub at `packages/entity/src/TranslatableInterface.php` becomes:

```php
namespace Waaseyaa\Entity;

interface TranslatableInterface
{
    // EXISTING (kept):
    public function language(): string;                              // deprecated alias for activeLangcode()
    public function getTranslationLanguages(): array;                // canonical multi-langcode getter
    public function hasTranslation(string $langcode): bool;
    public function getTranslation(string $langcode): static;

    // NEW (M-006):
    public function defaultLangcode(): string;
    public function activeLangcode(): string;                        // canonical name for "current"
    public function addTranslation(string $langcode): static;
    public function removeTranslation(string $langcode): void;
    public function translations(): iterable;                        // alias for getTranslationLanguages()
}
```

`fieldLangcode(string): ?string` lives on `ContentEntityBase`, not the interface (R7).

**Invariants:**
- `activeLangcode()` MUST equal `defaultLangcode()` for an entity loaded via `find($id)` without `getTranslation()` chained.
- `removeTranslation($lc)` MUST throw `EntityTranslationException::cannotRemoveDefault()` when `$lc === defaultLangcode()`.
- `addTranslation($lc)` MUST throw `EntityTranslationException::translationAlreadyExists()` when `hasTranslation($lc) === true`.
- `getTranslation($lc)` MUST throw `EntityTranslationException::translationNotFound()` when `hasTranslation($lc) === false`.
- `translations()` MUST yield default-langcode first, then ascending lex.
- All methods MUST throw `EntityTranslationException::notTranslatable()` when called on an entity whose type has `translatable: false`.

### `Waaseyaa\Entity\Exception\EntityTranslationException`

```php
final class EntityTranslationException extends \DomainException
{
    public static function translationNotFound(string $langcode): self;
    public static function cannotRemoveDefault(string $langcode): self;
    public static function langcodeRequired(): self;
    public static function notTranslatable(string $entityTypeId): self;
    public static function translationAlreadyExists(string $langcode): self;
}
```

Static factories return instances with formatted messages. No additional public surface.

### `Waaseyaa\Field\FieldDefinition` — builder addition

```php
// New builder (WP03):
public function translatable(bool $value = true): self
{
    return new self(
        // all current readonly constructor params, with translatable: $value swapped
    );
}

// Existing getter (line 78), now load-bearing:
public function isTranslatable(): bool
{
    return $this->translatable;
}
```

### `Waaseyaa\Entity\EntityType` — boot validation (WP02)

`EntityType::__construct` already accepts `translatable: bool`. WP02 adds validation in the constructor body:

```php
if ($this->translatable) {
    if (!isset($this->keys['langcode'])) {
        throw InvalidEntityTypeException::missingLangcodeKey($this->id);
    }
    if (!isset($this->keys['default_langcode'])) {
        throw InvalidEntityTypeException::missingDefaultLangcodeKey($this->id);
    }
    if (!is_subclass_of($this->class, TranslatableInterface::class)) {
        throw InvalidEntityTypeException::translatableEntityClassNotImplementingInterface(
            $this->id,
            $this->class
        );
    }
}
```

### `Waaseyaa\Entity\Exception\InvalidEntityTypeException` — additions

```php
public static function missingLangcodeKey(string $entityTypeId): self;
public static function missingDefaultLangcodeKey(string $entityTypeId): self;
public static function translatableEntityClassNotImplementingInterface(
    string $entityTypeId,
    string $class,
): self;
```

### `Waaseyaa\Field\Exception\InvalidFieldDefinitionException` — additions

```php
public static function translatableOnNonTranslatableEntityType(
    string $fieldName,
    string $entityTypeId,
): self;

public static function systemKeyMarkedTranslatable(string $fieldName): self;
```

### `Waaseyaa\EntityStorage\SaveContext` — extension

```php
final class SaveContext
{
    private function __construct(
        public readonly bool $withoutNewRevision = false,
        public readonly ?string $langcode = null,                    // NEW (WP07)
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public function withoutNewRevision(): self
    {
        return new self(withoutNewRevision: true, langcode: $this->langcode);
    }

    public function withLangcode(string $langcode): self             // NEW (WP07)
    {
        return new self(
            withoutNewRevision: $this->withoutNewRevision,
            langcode: $langcode,
        );
    }
}
```

### `Waaseyaa\Entity\Hydration\FallbackChainResolver` — NEW (WP06)

```php
final readonly class FallbackChainResolver
{
    public function __construct(
        private \Closure $chainFn,                                   // fn(string, EntityInterface): string[]
        private int $maxChainLength = 8,                             // NFR-002
    ) {}

    /** @return iterable<string> */
    public function resolve(string $requested, EntityInterface $entity): iterable
    {
        $chain = ($this->chainFn)($requested, $entity);
        if (count($chain) > $this->maxChainLength) {
            throw new InvalidConfigurationException(
                "Fallback chain length " . count($chain) .
                " exceeds maximum {$this->maxChainLength}"
            );
        }
        $seen = [];
        foreach ($chain as $lc) {
            if (!isset($seen[$lc])) {
                $seen[$lc] = true;
                yield $lc;
            }
        }
    }
}
```

### `Waaseyaa\Entity\ContentEntityBase` — additions (WP01)

```php
abstract class ContentEntityBase extends EntityBase implements
    ContentEntityInterface,
    HydratableFromStorageInterface,
    TranslatableInterface                                            // NEW (WP01)
{
    /** @var array<string,?string> field-name → resolved-langcode (or null on fallback exhausted) */
    private array $fieldLangcodes = [];                              // NEW (WP06)

    private string $activeLangcode;                                  // NEW (WP01)
    private array $translationRows = [];                             // NEW (WP01); lazy-loaded by storage

    public function defaultLangcode(): string { /* from $this->values['default_langcode'] */ }
    public function activeLangcode(): string { return $this->activeLangcode; }
    public function language(): string { return $this->activeLangcode(); }   // deprecated alias

    public function hasTranslation(string $lc): bool;
    public function getTranslation(string $lc): static;
    public function addTranslation(string $lc): static;
    public function removeTranslation(string $lc): void;

    /** @return iterable<string> */
    public function translations(): iterable;
    public function getTranslationLanguages(): array;

    public function fieldLangcode(string $fieldName): ?string
    {
        return $this->fieldLangcodes[$fieldName] ?? null;
    }
}
```

### `Waaseyaa\EntityStorage\EntityRepository::findTranslations` (WP10)

```php
/** @return array<string, EntityInterface> langcode-keyed */
public function findTranslations(EntityInterface $entity): array;
```

Single-query implementation per R10.

---

## Storage (SQL)

### `sql-column` backend — primary table unchanged

```sql
-- Existing shape, unchanged:
CREATE TABLE teaching (
    entity_id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    langcode VARCHAR(12) NOT NULL,
    default_langcode VARCHAR(12) NOT NULL,                           -- NEW column (WP04)
    created_at INTEGER NOT NULL,                                     -- non-translatable
    author_id INTEGER REFERENCES users(uid)                          -- non-translatable
);
```

### `sql-column` backend — NEW translation table

```sql
CREATE TABLE teaching__translation (
    entity_id INTEGER NOT NULL,
    langcode VARCHAR(12) NOT NULL,
    title TEXT,                                                       -- translatable
    body TEXT,                                                        -- translatable
    PRIMARY KEY (entity_id, langcode),
    FOREIGN KEY (entity_id) REFERENCES teaching(entity_id) ON DELETE CASCADE
);
```

Invariant: a `teaching__translation` row for `langcode = default_langcode` MUST exist iff the `teaching` row exists.

### `sql-column` backend — multi-cardinality field tables

```sql
-- Non-translatable multi-field, unchanged:
CREATE TABLE teaching__tags (
    entity_id INTEGER NOT NULL,
    delta INTEGER NOT NULL,
    value VARCHAR(255) NOT NULL,
    PRIMARY KEY (entity_id, delta),
    FOREIGN KEY (entity_id) REFERENCES teaching(entity_id) ON DELETE CASCADE
);

-- Translatable multi-field, NEW shape (FR-032):
CREATE TABLE teaching__notes (
    entity_id INTEGER NOT NULL,
    langcode VARCHAR(12) NOT NULL,
    delta INTEGER NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (entity_id, langcode, delta),
    FOREIGN KEY (entity_id, langcode) REFERENCES teaching__translation(entity_id, langcode)
        ON DELETE CASCADE
);
```

### `sql-blob` backend — widened primary key

```sql
-- BEFORE M-006 (non-translatable, unchanged):
CREATE TABLE teaching (
    entity_id INTEGER NOT NULL,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    langcode VARCHAR(12) NOT NULL,
    _data TEXT,                                                       -- JSON blob: all field values
    PRIMARY KEY (entity_id)
);

-- AFTER M-006 (translatable: true types):
CREATE TABLE teaching (
    entity_id INTEGER NOT NULL,
    uuid VARCHAR(36) NOT NULL,                                        -- unique constraint moved per-default-langcode row only? See note below
    langcode VARCHAR(12) NOT NULL,
    default_langcode VARCHAR(12) NOT NULL,
    _data TEXT,                                                       -- JSON blob: translatable fields ONLY
    PRIMARY KEY (entity_id, langcode)
);
```

**Note on UUID uniqueness in sql-blob:** the same UUID must appear on every translation row of the same entity. Enforce via a partial unique index: `CREATE UNIQUE INDEX teaching_uuid_default ON teaching(uuid) WHERE langcode = default_langcode;`. Verified at WP04.

**Non-translatable field storage in sql-blob:** stored only on the `default_langcode` row's `_data` blob (FR-021). Reads from non-default-langcode rows single-step fallback to the default row (FR-022).

---

## Events

Existing `EntityEvent` class extended:

```php
final class EntityEvent extends Event
{
    public function __construct(
        public readonly EntityInterface $entity,
        public readonly ?EntityInterface $originalEntity = null,
        public readonly ?string $langcode = null,                    // NEW (WP08)
    ) {}
}
```

New `TranslationEvent` subclass:

```php
final class TranslationEvent extends EntityEvent
{
    public function __construct(
        EntityInterface $entity,
        public readonly string $langcode,                            // required, not nullable
        ?EntityInterface $originalEntity = null,
    ) {
        parent::__construct($entity, $originalEntity, $langcode);
    }
}
```

New constants in `EntityEvents` registry:

```php
const PRE_TRANSLATION_INSERT  = 'waaseyaa.entity.pre_translation_insert';
const POST_TRANSLATION_INSERT = 'waaseyaa.entity.post_translation_insert';
const PRE_TRANSLATION_UPDATE  = 'waaseyaa.entity.pre_translation_update';
const POST_TRANSLATION_UPDATE = 'waaseyaa.entity.post_translation_update';
const PRE_TRANSLATION_DELETE  = 'waaseyaa.entity.pre_translation_delete';
const POST_TRANSLATION_DELETE = 'waaseyaa.entity.post_translation_delete';
```

---

## Write-semantics decision matrix (WP07)

Coordinator `save()` behaviour, given:
- `T` = entity translatable?
- `N` = entity new?
- `L` = SaveContext langcode or entity activeLangcode
- `D` = entity defaultLangcode

| Case | T | N | L vs D | Behaviour |
|---|---|---|---|---|
| 1 | false | * | n/a | Unchanged. Existing single-table path. |
| 2 | true | true | L == D | INSERT primary row + INSERT default-translation row. Dispatch `PRE/POST_INSERT` (entity-level). |
| 3 | true | true | L ≠ D | Case 2 + INSERT non-default translation row. Dispatch `PRE/POST_TRANSLATION_INSERT` for the non-default. |
| 4 | true | false | L == D | UPDATE primary row (non-translatable deltas) + UPDATE default-translation row (translatable deltas). Dispatch `PRE/POST_UPDATE`. |
| 5 | true | false | L ≠ D, hasTranslation(L) | UPDATE primary row + UPDATE translation row for L. Dispatch `PRE/POST_UPDATE` (entity) + `PRE/POST_TRANSLATION_UPDATE` (L). |
| 6 | true | false | L ≠ D, !hasTranslation(L) | UPDATE primary row + INSERT translation row for L. Dispatch `PRE/POST_UPDATE` (entity) + `PRE/POST_TRANSLATION_INSERT` (L). |
| 7 | true | false | pending `removeTranslation(R)` | UPDATE primary + DELETE translation row R + per-cell behaviour from above. Dispatch `PRE/POST_TRANSLATION_DELETE` for R. |
| 8 | true | false | T `default_langcode` unset | `throw EntityTranslationException::langcodeRequired()` (FR-034). |

All within a single `UnitOfWork::transaction` for atomicity.

---

## Validation entity-type fixture (WP13)

```php
namespace Waaseyaa\EntityStorage\Tests\Fixtures;

use Waaseyaa\Entity\ContentEntityBase;

final class TestTranslatableEntity extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct($values, 'test_translatable_entity', [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
            'langcode' => 'langcode',
            'default_langcode' => 'default_langcode',
        ]);
    }
}
```

Registered via the test-only entity-type manager. Two translatable fields (`title`, `body`), two non-translatable (`created_at`, `author_id`).

---

## Open data-model questions

None at draft time. New questions discovered during implement-review will be logged here.
