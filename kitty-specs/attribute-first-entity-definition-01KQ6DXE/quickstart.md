# Quickstart — Defining a Content Entity Post-M1

How to define a brand-new content entity in a Waaseyaa app once this mission lands.

---

## Before (today)

Two-step, two-file:

```php
// src/Entity/Note.php
namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

#[ContentEntityType(id: 'note')]
#[ContentEntityKeys(label: 'title', bundle: 'bundle', langcode: 'langcode')]
final class Note extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = 'note',
        array $entityKeys = [...],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}
```

```php
// config/entity-types.php
return [
    new \Waaseyaa\Entity\EntityType(
        id: 'note',
        label: 'Note',
        class: \App\Entity\Note::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'bundle', 'langcode' => 'langcode'],
        fieldDefinitions: [
            'title' => new FieldDefinition(name: 'title', type: 'string', required: true),
            'body' => new FieldDefinition(name: 'body', type: 'text'),
            'status' => new FieldDefinition(name: 'status', type: 'string', defaultValue: 'draft'),
        ],
    ),
];
```

---

## After M1

One file. The class is the spec.

```php
// src/Entity/Note.php
namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;

#[ContentEntityType(id: 'note', label: 'Note', description: 'Free-form authored content.')]
#[ContentEntityKeys(label: 'title', bundle: 'bundle', langcode: 'langcode')]
final class Note extends ContentEntityBase
{
    #[Field] public string $title;
    #[Field(type: 'text')] public ?string $body;
    #[Field(default: 'draft')] public string $status;
}
```

```php
// config/entity-types.php
return [
    \Waaseyaa\Entity\EntityType::fromClass(\App\Entity\Note::class),
];
```

(After M2 lands, the `config/entity-types.php` step disappears entirely — the framework auto-discovers attribute-decorated entity classes.)

---

## Inferred mappings (cheat sheet)

| Property declaration | Inferred field |
|---|---|
| `#[Field] public string $title;` | type=`string`, required=true |
| `#[Field] public ?string $body;` | type=`string`, required=false |
| `#[Field] public int $count;` | type=`integer`, required=true |
| `#[Field] public bool $isActive;` | type=`boolean`, required=true |
| `#[Field] public ?\DateTimeImmutable $publishedAt;` | type=`datetime`, required=false |
| `#[Field] public array $tags;` | type=`json`, required=true (single JSON blob) |
| `#[Field] public CourseStatus $status;` (backed enum) | type=`string`, required=true, settings=`['enum_class' => CourseStatus::class]` |

When inference is wrong or you want a richer type, override:

```php
#[Field(type: 'text')] public ?string $body;            // text instead of string
#[Field(type: 'email')] public string $contactEmail;    // email instead of plain string
#[Field(type: 'entity_reference', settings: ['target_type' => 'user'])]
public ?string $authorId;                               // entity reference
#[Field(default: 'draft')] public string $status;       // explicit default
```

---

## Round-trip example

Once registered, the entity round-trips through the existing `EntityRepository`:

```php
$repo = $kernel->resolve(EntityRepositoryInterface::class);

$note = new Note(['title' => 'Hello', 'body' => 'World', 'status' => 'draft']);
$repo->save($note);

$loaded = $repo->find('note', $note->id());
$loaded->title;   // 'Hello'
$loaded->body;    // 'World'
$loaded->status;  // 'draft'
```

No code changes required in the storage driver, schema controller, or AI-schema generator — they continue to consume `FieldDefinition` instances the same way; only the *source* of those definitions changed.

---

## What did we save?

| Concern | Lines before | Lines after |
|---|---|---|
| Entity class | ~20 (with constructor override) | ~10 |
| `config/entity-types.php` per-entity entry | ~10 (with field def array) | 1 (`fromClass(...)`) |
| Risk of drift between class metadata and config registration | yes (validator polices it) | none (single source) |

A new entity is now **one file, ~10 lines, zero duplicate truth.**
