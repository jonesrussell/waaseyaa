# Contract: Lifecycle events with langcode

**Mission:** M-006 · **Status:** stable surface on merge per stability-charter §5.3
**Normative spec:** [`spec.md` §3.8](../spec.md#38-lifecycle-events)
**Work package:** WP08

---

## Event names (NEW)

| Constant | String value | Dispatched class |
|---|---|---|
| `EntityEvents::PRE_TRANSLATION_INSERT` | `waaseyaa.entity.pre_translation_insert` | `TranslationEvent` |
| `EntityEvents::POST_TRANSLATION_INSERT` | `waaseyaa.entity.post_translation_insert` | `TranslationEvent` |
| `EntityEvents::PRE_TRANSLATION_UPDATE` | `waaseyaa.entity.pre_translation_update` | `TranslationEvent` |
| `EntityEvents::POST_TRANSLATION_UPDATE` | `waaseyaa.entity.post_translation_update` | `TranslationEvent` |
| `EntityEvents::PRE_TRANSLATION_DELETE` | `waaseyaa.entity.pre_translation_delete` | `TranslationEvent` |
| `EntityEvents::POST_TRANSLATION_DELETE` | `waaseyaa.entity.post_translation_delete` | `TranslationEvent` |

---

## Event payload shape

### `EntityEvent` (EXTENDED)

```php
namespace Waaseyaa\Entity\Event;

final class EntityEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public function __construct(
        public readonly EntityInterface $entity,
        public readonly ?EntityInterface $originalEntity = null,
        public readonly ?string $langcode = null,                    // NEW (M-006)
    ) {}
}
```

Backward-compatible: existing callers passing 1 or 2 args continue to work; `$langcode` defaults to `null`.

For non-translatable entities, `$langcode === null`.
For translatable entities, `$langcode` carries the langcode being saved/deleted.

### `TranslationEvent` (NEW)

```php
namespace Waaseyaa\Entity\Event;

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

Listeners that need to react to translation-specific changes catch `TranslationEvent`. Listeners catching `EntityEvent` see both entity-level events and translation events (because `TranslationEvent extends EntityEvent`); use `$event instanceof TranslationEvent` to narrow if needed.

---

## Dispatch order

For a save of an existing translatable entity that adds the French translation while updating the English default:

```
PRE_UPDATE                  (entity-level, $langcode=null)
PRE_TRANSLATION_UPDATE      (TranslationEvent, $langcode='en')
PRE_TRANSLATION_INSERT      (TranslationEvent, $langcode='fr')
[ persist ]
POST_TRANSLATION_INSERT     (TranslationEvent, $langcode='fr')
POST_TRANSLATION_UPDATE     (TranslationEvent, $langcode='en')
POST_UPDATE                 (entity-level, $langcode=null)
```

For a delete of an entity with three extant translations (default `en`, plus `oj` and `fr`):

```
PRE_DELETE                  (entity-level)
PRE_TRANSLATION_DELETE      ('fr')
PRE_TRANSLATION_DELETE      ('oj')
PRE_TRANSLATION_DELETE      ('en')                  -- default last
[ persist ]
POST_TRANSLATION_DELETE     ('en')
POST_TRANSLATION_DELETE     ('oj')
POST_TRANSLATION_DELETE     ('fr')
POST_DELETE                 (entity-level)
```

---

## Atomicity

All events of a single `EntityRepository::save()` / `delete()` call MUST be dispatched within the surrounding `UnitOfWork::transaction()`. A listener throwing aborts the transaction; no partial events leak into post-commit reality.

---

## Test coverage (I04 in spec §9.3)

```php
$collected = [];
$dispatcher->addListener(
    EntityEvents::PRE_TRANSLATION_INSERT,
    function (TranslationEvent $event) use (&$collected) {
        $collected[] = [$event::class, $event->langcode];
    }
);

$teaching->addTranslation('oj');
$repository->save($teaching, SaveContext::default()->withLangcode('oj'));

$this->assertContains([TranslationEvent::class, 'oj'], $collected);
```
