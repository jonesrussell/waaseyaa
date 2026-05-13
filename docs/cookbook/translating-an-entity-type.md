# Adding translations to an entity type

This recipe walks through making one of your entity types translatable so that the same logical record can carry content in more than one language.

## When to use translatable entities

Reach for entity-level translation when:

- A single record represents the same real-world thing in multiple languages (a teaching, an article, a recipe, a place name).
- You want per-field control over what gets translated (translate `title` and `body`, but share `created_at` and `author_id`).
- You need a stable identifier for each record across languages (one `tid`, one `uuid`, many language variants).

## When NOT to use translatable entities

Skip translatable entities and use separate records when:

- The entity is a **config entity** (settings, definitions, schema). Config entities live in YAML and use the project's i18n config-translation layer instead.
- You have a **low-cardinality lookup table** (a few rows that rarely change). Hand-author each language as a separate row, or embed translations in a single JSON column.
- Each "translation" is really a separate authored piece (a French article and an English article that share a topic but are not literal translations of each other). Use a relationship between independent entities instead.

## What you'll do

Eight steps. Roughly ten lines of consumer code to make one entity bilingual.

### 1. Declare the entity type translatable

Set `translatable: true` on the `EntityType` and add the `default_langcode` entity key alongside the existing `langcode` key.

```php
new EntityType(
    id: 'teaching',
    label: 'Teaching',
    class: Teaching::class,
    translatable: true,
    keys: [
        'id' => 'tid',
        'uuid' => 'uuid',
        'label' => 'title',
        'langcode' => 'langcode',
        'default_langcode' => 'default_langcode',
    ],
);
```

The `default_langcode` key is required for translatable types. Boot validation rejects translatable types that omit it.

### 2. Mark individual fields as translatable

Call `translatable()` on the fields that should carry per-language values. Anything you do not call `translatable()` on stays shared across translations.

```php
new FieldDefinition(name: 'title', type: 'string')->translatable();
new FieldDefinition(name: 'body', type: 'text')->translatable();
new FieldDefinition(name: 'created_at', type: 'timestamp'); // shared
```

Calling `translatable()` on a field of a non-translatable type fails at boot — it's a programming error, not a runtime check.

### 3. Generate and run the schema migration

The CLI generates an idempotent migration that adjusts the storage shape for your chosen backend (column-per-translation or blob-per-translation) and backfills existing rows with the default langcode you supply.

```bash
bin/waaseyaa make:migration --add-translations teaching --default-langcode en
bin/waaseyaa migrate
```

Sample output:

```text
$ bin/waaseyaa make:migration --add-translations teaching --default-langcode en
Created migrations/2026_05_12_000001_add_translations_to_teaching.php
Backfill: 1247 existing rows will be marked langcode=default_langcode='en'
Reverse-migration WARNING: drops non-default-langcode rows (data loss)

$ bin/waaseyaa migrate
Running 1 migration:
  -> 2026_05_12_000001_add_translations_to_teaching ... OK
Done in 0.412s.
```

### 4. Create an entity and add a translation

Set the default and active langcodes explicitly when you construct a new translatable entity. Use `addTranslation()` to allocate a new language variant, then save it with a `SaveContext` that names the target langcode.

```php
$teaching = new Teaching([
    'langcode' => 'en',
    'default_langcode' => 'en',
    'title' => 'How birch bark is harvested',
    'body' => '...',
]);
$repository->save($teaching);

$oj = $teaching->addTranslation('oj');
$oj->set('title', 'Aaniin wiigwaas mawagishkemaagong');
$oj->set('body', '...');
$repository->save($oj, SaveContext::default()->withLangcode('oj'));
```

That's the entire "make this entity bilingual" path — ten lines of consumer code.

### 5. Read translations

`find()` returns the default translation. Switch to another translation with `getTranslation()` on the loaded entity. Non-translatable fields read the same value across all translations.

```php
$teaching = $repository->find($tid);          // default-langcode (en)
echo $teaching->activeLangcode();              // 'en'
echo $teaching->get('title');                  // 'How birch bark is harvested'

$oj = $teaching->getTranslation('oj');
echo $oj->activeLangcode();                    // 'oj'
echo $oj->get('title');                        // 'Aaniin wiigwaas mawagishkemaagong'
echo $teaching->get('created_at') === $oj->get('created_at'); // true
```

Want to know which langcode a value actually came from after fallback resolution? Ask `fieldLangcode()`:

```php
echo $teaching->fieldLangcode('title');        // 'en'
```

### 6. Remove a translation

Removing the default translation is forbidden — that's the anchor that identifies the entity. Remove any other translation freely; the save call applies the delete inside the same transaction as any other pending writes.

```php
$teaching->removeTranslation('oj');
$repository->save($teaching);
```

Trying to remove the default throws `EntityTranslationException::cannotRemoveDefault('en')`.

### 7. Query all translations

`findTranslations()` returns every language variant of a single entity as a keyed array. It uses a single SQL query — safe to call in hot paths.

```php
$translations = $repository->findTranslations($teaching);
// ['en' => Teaching($title='How birch bark is harvested'),
//  'oj' => Teaching($title='Aaniin wiigwaas mawagishkemaagong')]
```

### 8. Iterate the available translations

```php
foreach ($teaching->translations() as $lc) {
    echo "$lc: " . $teaching->getTranslation($lc)->get('title') . "\n";
}
// en: How birch bark is harvested
// oj: Aaniin wiigwaas mawagishkemaagong
```

`translations()` lists the default langcode first, then the rest in insertion order.

## Reading a non-default translation eagerly

`find()` returns the default. If you already know the user's active language and want that translation in a single round trip, use the language manager or pass an explicit langcode through your application's language resolver — `EntityRepository::find()` consults the language manager when one is wired and the `translation.read_active_language` config flag is set. Otherwise call `getTranslation()` after loading the default.

## Configuring the language fallback chain

When code requests a translation that doesn't exist for a given field, the resolver walks a configurable chain of langcodes before returning `null`. Configure it in your application config:

```php
// config/translation.php
return [
    'fallback_chain' => ['en', 'fr'],
    'read_active_language' => true,
];
```

Both keys are surfaced through the standard config provider. Default chain is empty (no fallback beyond the entity's default langcode).

## Listening for translation lifecycle events

Six event-name constants fire around translation persistence:

- `PRE_TRANSLATION_INSERT` / `POST_TRANSLATION_INSERT`
- `PRE_TRANSLATION_UPDATE` / `POST_TRANSLATION_UPDATE`
- `PRE_TRANSLATION_DELETE` / `POST_TRANSLATION_DELETE`

Each event is a `TranslationEvent` carrying the entity, the langcode being affected, and (where relevant) the prior values. They are siblings of the existing `EntityEvent` family.

```php
$dispatcher->addListener(TranslationEvent::POST_TRANSLATION_INSERT, function (TranslationEvent $event) {
    // ... index the new translation, invalidate caches, etc.
});
```

## Access control for translations

Translation operations route through an extra access-policy operation: `'translate'`. Implement `ContextAwareAccessPolicyInterface` (the companion to `AccessPolicyInterface`) on your policy if you need to allow or deny a specific langcode:

```php
public function access(string $operation, EntityInterface $entity, AccountInterface $account, array $context = []): AccessResult
{
    if ($operation === 'translate' && ($context['langcode'] ?? null) === 'fr') {
        return $this->hasFrenchEditorRole($account) ? AccessResult::allowed() : AccessResult::forbidden();
    }
    // ...
}
```

The `$context` array carries the target langcode for `translate` operations and any read-time langcode for `view`/`update` checks.

## When things go wrong

### Missing `default_langcode` on save

Saving a translatable entity without a `default_langcode` value throws `EntityTranslationException::langcodeRequired()`. Set it explicitly in the constructor array, or set it on the entity before save:

```php
$teaching->set('default_langcode', 'en');
```

### Trying to remove the default translation

`removeTranslation($defaultLangcode)` throws `EntityTranslationException::cannotRemoveDefault($lc)`. If you really want to retire a record entirely, delete the entity — that removes all translations atomically. If you want to change which language is the default, first promote another translation to be the default, then remove the old default.

### Fallback exhaustion returns `null`

When neither the requested langcode nor any langcode in the configured fallback chain has a non-null value for a translatable field, `get()` returns `null`. This is intentional — empty content fallback is a presentation concern, not a storage concern. Decide at the view layer whether to fall back further, render a placeholder, or hide the field.

### `addTranslation()` on a langcode that already exists

Throws `EntityTranslationException::translationAlreadyExists($lc)`. Use `getTranslation($lc)` to mutate an existing translation instead.

### `getTranslation()` for a langcode that doesn't exist

Throws `EntityTranslationException::translationNotFound($lc)`. Check `hasTranslation($lc)` first, or call `addTranslation($lc)` if you want to create one on the fly.

### Calling `translatable()` on a field of a non-translatable type

Boot fails with a clear validation error. Either flip the type's `translatable: true` flag or stop calling `translatable()` on the field.

## See also

- **ADR 017** — Architectural decision record governing the single-axis translation substrate and its trade-offs vs. multi-axis (translations + revisions) designs.
- **Migration generator contract** — Spec for `bin/waaseyaa make:migration --add-translations`, including the storage-shape decision matrix (column-per-translation vs. blob-per-translation) and the backfill/reverse-migration semantics.
- **`docs/specs/entity-storage-translations-v1.md`** — Mission spec with full FR list, contract tests, and edge-case matrix.
