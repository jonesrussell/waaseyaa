# Quickstart: Declaring an enum field

**Mission**: `field-type-enum-plugin-01KQ6SJG`

After this mission lands, declaring a backed-enum-constrained field on an entity becomes a one-liner: type the property as a backed enum and the framework does the rest.

## Define the enum

```php
namespace App\Domain\Subscription;

enum SubscriptionTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Enterprise = 'enterprise';
}
```

Optionally, opt into custom widget labels:

```php
use Waaseyaa\Field\Item\LabeledCase;

enum SubscriptionTier: string implements LabeledCase
{
    case Free = 'free';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    public function getLabel(): string {
        return match ($this) {
            self::Free       => 'Free tier',
            self::Pro        => 'Professional',
            self::Enterprise => 'Enterprise',
        };
    }
}
```

## Declare the field on an attribute-first entity

```php
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\Attribute\Entity;
use Waaseyaa\Entity\ContentEntityBase;

#[Entity(id: 'subscription', label: 'Subscription')]
final class Subscription extends ContentEntityBase
{
    #[Field]
    public SubscriptionTier $tier;
}
```

That is the entire declaration. `FieldTypeInferrer` recognises the backed-enum type and emits `type: 'enum', settings: ['enum_class' => SubscriptionTier::class]`.

## What you get

| Surface | Behavior |
|---------|----------|
| Storage column | `varchar(255)` (string-backed). Int-backed enums get an `int` column. |
| Validation | Writing `$entity->tier = 'invalid'` is rejected before storage with an `EnumFieldType.InvalidInputValue` error. Writing `'pro'` or `SubscriptionTier::Pro` succeeds. |
| Hydration | Reading the entity back returns `SubscriptionTier::Pro` (an enum case), not the raw string. |
| JSON Schema | `waaseyaa/ai-schema` emits `{"type": "string", "enum": ["free", "pro", "enterprise"]}` for the `tier` field. AI agents that generate payloads against this schema cannot produce illegal values. |
| Admin widgets | Form widgets render an option list. With `LabeledCase`: "Free tier / Professional / Enterprise". Without: "Free / Pro / Enterprise" (case names). |

## What changed (compared to before this mission)

- **Before**: The framework inferred backed-enum properties as `type: 'string'` with a side-channel `settings.enum_class` hint. Each consumer (validator, schema emitter, admin widget) re-derived enum semantics from that hint independently.
- **After**: A single `type: 'enum'` plugin owns all four contracts. There is one canonical shape; the `'string' + enum_class` bridge is gone.

## Verification recipe

After this mission lands you can verify the cutover locally:

```bash
# In waaseyaa-framework repo root:
git grep -n "enum_class" -- ':!packages/field/src/Item/EnumItem.php' \
                           ':!packages/entity/src/Attribute/FieldTypeInferrer.php' \
                           ':!packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php' \
                           ':!packages/*/tests/**'
# Expected: no results (or only documentation/CHANGELOG entries describing the migration).
```

```bash
./vendor/bin/phpunit packages/field/tests/Unit/Item/EnumItemTest.php
./vendor/bin/phpunit packages/entity/tests/Unit/Attribute/FieldTypeInferrerTest.php
./vendor/bin/phpunit packages/entity/tests/Unit/Validation/
# Expected: all green.
```
