# Quickstart — Two-axis (revision × translation) entities

**Date:** 2026-05-16
**Spec:** [`spec.md`](spec.md)
**Plan:** [`plan.md`](plan.md)

A walkthrough using Minoo's `teaching` entity type — the canonical use case driving M-004. Demonstrates the surface that WP01..WP07 ship and WP08 validates (FR-043, FR-044).

---

## 1. Declare a revisionable + translatable entity type

```php
namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Entity\RevisionableEntityTrait;
use Waaseyaa\Entity\TranslatableInterface;
// (TranslatableInterface composed via ContentEntityBase; no explicit declaration needed.)

final class Teaching extends ContentEntityBase implements RevisionableEntityInterface
{
    use RevisionableEntityTrait;

    public function __construct(array $values = [])
    {
        parent::__construct($values, 'teaching', [
            'id' => 'tid',
            'uuid' => 'uuid',
            'label' => 'title',
            'langcode' => 'langcode',
            'default_langcode' => 'default_langcode',
        ]);
    }
}
```

Register the entity type with the framework:

```php
$entityTypeManager->addEntityType(new EntityType(
    id: 'teaching',
    label: 'Teaching',
    class: Teaching::class,
    revisionable: true,         // <- one axis
    translatable: true,         // <- second axis (composes with revisionable → two-axis storage shape)
    keys: ['id' => 'tid', 'uuid' => 'uuid', 'label' => 'title', 'langcode' => 'langcode', 'default_langcode' => 'default_langcode'],
));
```

Field definitions partition into translatable vs non-translatable:

```php
$fields = [
    FieldDefinition::create('title')->setType('string')->translatable(),   // per-langcode
    FieldDefinition::create('body')->setType('text')->translatable(),       // per-langcode
    FieldDefinition::create('community_id')->setType('integer'),            // non-translatable (stored once on default-langcode revision)
    FieldDefinition::create('starts_at')->setType('datetime'),              // non-translatable
];
```

## 2. Generate the storage migration

```bash
bin/waaseyaa make:storage-migration teaching --add-translations
```

Output: a migration that creates `teaching`, `teaching__translation`, `teaching__revision`, and `teaching__translation__revision` per the schema in [`contracts/composite-pk.md`](contracts/composite-pk.md).

Apply:

```bash
bin/waaseyaa migrate
```

## 3. Create a teaching in English

```php
$teaching = new Teaching([
    'default_langcode' => 'en',
    'langcode' => 'en',
    'title' => 'How to make maple syrup',
    'body' => 'Wait for the spring thaw...',
    'community_id' => 12,
    'starts_at' => '2026-03-01T00:00:00Z',
]);
$teaching->enforceIsNew();
$repository->save($teaching);
// → INSERT teaching (tid=42, vid=1).
// → INSERT teaching__revision (vid=1, community_id=12, starts_at=...).
// → INSERT teaching__translation (tid=42, langcode='en', vid=2).
// → INSERT teaching__translation__revision (vid=2, langcode='en', title='How to...', body='Wait for...').
```

## 4. Add an Anishinaabemowin translation

```php
$teaching = $repository->find(42);
$ojTranslation = $teaching->addTranslation('oj');
$ojTranslation->set('title', '<Anishinaabemowin title>');
$ojTranslation->set('body', '<Anishinaabemowin body>');
$repository->save($ojTranslation, SaveContext::default()->withLangcode('oj'));
// → INSERT teaching__translation__revision (vid=3, tid=42, langcode='oj', title='...', body='...').
// → INSERT teaching__translation (tid=42, langcode='oj', vid=3).
// → (no change to teaching.vid; no new teaching__revision row; English revision history untouched.)
```

## 5. Edit the English text three times

```php
foreach (['First edit', 'Second edit', 'Third edit'] as $newBody) {
    $teaching = $repository->find(42);   // loads at default-langcode current revision
    $teaching->set('body', $newBody);
    $repository->save($teaching, SaveContext::default()->withLangcode('en'));
}
// After three saves:
// - teaching__translation__revision has 4 rows for (42, 'en'): the original + 3 edits.
// - teaching__translation__revision has 1 row for (42, 'oj'): the original Anishinaabemowin (untouched).
// - teaching__translation.vid for (42, 'en') points to the latest English revision.
// - teaching__translation.vid for (42, 'oj') unchanged.
```

## 6. Edit the Anishinaabemowin text twice

```php
foreach (['First oj edit', 'Second oj edit'] as $newBody) {
    $teaching = $repository->find(42);
    $oj = $teaching->getTranslation('oj');
    $oj->set('body', $newBody);
    $repository->save($oj, SaveContext::default()->withLangcode('oj'));
}
// After two saves:
// - teaching__translation__revision has 4 rows for (42, 'en') (unchanged from §5).
// - teaching__translation__revision has 3 rows for (42, 'oj'): the original + 2 edits.
// - English revision count unchanged. (FR-009, FR-010 satisfied.)
```

## 7. List revisions across both languages

```php
$teaching = $repository->find(42);
$all = iterator_to_array($teaching->listRevisions());        // interleaved by creation order
// → 7 revisions total: 4 English + 3 Anishinaabemowin (1 initial + 2 edits) + the initial oj revision.
// (Wait — let's count again: §3 creates 1 en. §4 adds 1 oj. §5 adds 3 more en (4 total). §6 adds 2 more oj (3 total). Total = 7.)

$enOnly = iterator_to_array($teaching->listRevisions('en'));  // → 4 English revisions
$ojOnly = iterator_to_array($teaching->listRevisions('oj'));  // → 3 Anishinaabemowin revisions
```

This matches FR-043's validation gate (5 revisions across 2 languages is the minimum, but the example shows the pattern scales).

## 8. View a non-translatable field via fallback

```php
$teaching = $repository->find(42);
$oj = $teaching->getTranslation('oj');
echo $oj->get('community_id');   // 12 — read via single-step fallback to default-langcode revision
echo $oj->get('title');           // <Anishinaabemowin title from the oj current revision>
```

The runtime reads non-translatable values from `teaching__revision` at `teaching.vid` (one extra row lookup; NFR-A bounds the cost).

## 9. Change a non-translatable field

```php
$teaching = $repository->find(42);
$teaching->set('community_id', 13);
$repository->save($teaching, SaveContext::default()->withLangcode('en'));
// → INSERT teaching__revision (new vid; community_id=13).
// → INSERT teaching__translation__revision (new vid; English fields snapshot).
// → UPDATE teaching.vid to point at the new entity-revision.
// → UPDATE teaching__translation.vid for (42, 'en') to point at the new translation-revision.
// → (No new oj translation-revision. Reading $teaching->getTranslation('oj')->get('community_id') now returns 13 via fallback.)
```

## 10. Multi-language atomic save

```php
$teaching = $repository->find(42);
$teaching->set('body', 'Updated English body');
$teaching->getTranslation('oj')->set('body', 'Updated Anishinaabemowin body');
$teaching->getTranslation('fr')->set('body', 'Updated French body');

$repository->save($teaching, SaveContext::default()->withTranslations(['en', 'oj', 'fr']));
// → Single transaction.
// → 3 new translation-revision rows (one per langcode).
// → AfterSaveEvent fires once with affectedLangcodes() === ['en', 'oj', 'fr'].
// → ListingCacheInvalidator emits entity:teaching:42, entity:teaching:42:en, entity:teaching:42:oj, entity:teaching:42:fr.
```

If any per-langcode save throws: rollback all 3; raise `PartialSaveException`.

## 11. Historical revision read (and write guard)

```php
$teaching = $repository->find(42);
$historical = $teaching->getTranslation('oj')->loadRevision(3);  // first oj revision
echo $historical->get('body');   // <original Anishinaabemowin body>
echo $historical->isCurrentRevision();   // false

$historical->set('body', 'Trying to edit history');
$repository->save($historical);
// → EntityTranslationException::historicalRevisionWrite(3, 'oj') raised.
```

## 12. Per-language access policy (FR-044 demo)

The policy in [`contracts/access-policy-revision.md`](contracts/access-policy-revision.md) §4 demonstrates the Minoo fixture:

- **Coordinator** role sees English revision history of teaching 42; access to Anishinaabemowin revision history is forbidden.
- **Knowledge-Keeper** role sees both English and Anishinaabemowin revision history.

Test fixture lives at `tests/Integration/PhaseN/TwoAxisAccessPolicyIntegrationTest.php`.

## 13. Listing two-axis entities by langcode

```php
$listing = (new ListingDefinition('teachings.published'))
    ->withFilter(Filter::eq('status', 'published'))
    ->withFilter(Filter::langcode('oj'));   // M-007's canonical factory

$result = $resolver->resolve($listing);
// → Returns teachings with an oj translation, read at the per-(entity, 'oj') current-revision pointer (FR-033a).
// → Cache key includes language.content = 'oj' (M-007 auto-injection).
// → Save of teaching 42 in oj invalidates the listing cache via entity:teaching:42:oj tag.
```

## 14. Migrate an existing single-axis type to two-axis

```bash
# Promote revisionable-only → two-axis:
bin/waaseyaa make:storage-migration teaching --add-translations
# (After running migrate, existing teaching__revision rows are preserved as default-langcode translation-revision rows.)

# OR promote translatable-only → two-axis:
bin/waaseyaa make:storage-migration teaching --add-revisions
# (After running migrate, existing teaching__translation rows become initial per-langcode revisions.)
```

If `teaching` is already two-axis, the generator raises `StorageMigrationException::noOpPromotion('teaching')`.

## 15. Failure modes summary

| Symptom | Likely cause | Fix |
|---|---|---|
| `EntityTranslationException::historicalRevisionWrite` | Saving an instance returned by `loadRevision()` | Load the current revision (`$repository->find()` or `$entity->getTranslation($lc)`) and save that. |
| `EntityTranslationException::cannotRemoveDefault` | `removeTranslation()` called with the default langcode | To remove the default "translation," delete the entity (`$repository->delete([$teaching])`). |
| `StorageMigrationException::noOpPromotion` | Generator called against an already-two-axis type | Verify the entity type's current axis via `$entityType->isRevisionable() && $entityType->isTranslatable()` before re-running. |
| `StorageMigrationException::unsupportedTwoAxisField` | Field marked `translatable()` on `vector` or `remote` backend | Move translatable fields to `sql-column` or `sql-blob` backend; non-translatable fields can stay. |
| `PartialSaveException` from multi-language save | One langcode's write failed; transaction rolled back | Inspect `$exception->getPrevious()` for the originating cause; retry once the underlying issue is resolved. |
| Default-langcode anchor invariant violation (`teaching.vid != teaching__translation.vid` for default langcode) | Storage corruption — should never occur with `RevisionableStorageDriver` | Run integrity check; restore from backup. Filing a bug report. |
| Single-step fallback reads return stale non-translatable value | New default-langcode revision wasn't created on the latest save (e.g. caller suppressed via `withoutNewRevision()`) | Verify the save path called `withLangcode($default_langcode)` and didn't suppress revision creation. |
