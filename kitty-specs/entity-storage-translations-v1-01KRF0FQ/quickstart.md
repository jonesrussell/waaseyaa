# Phase 1 Quickstart: Entity Storage — Single-Axis Translations v1

**Mission:** M-006 / `01KRF0FQ0AA42F434JNAA56WFB`
**Date:** 2026-05-12

Developer-facing scenario validating SC-01 ("≤10 lines of consumer code"). This becomes the cookbook recipe at `docs/cookbook/translating-an-entity-type.md` in WP14.

---

## Scenario: Make an entity type translatable

Consumer wants to add Anishinaabemowin (`oj`) translations to `teaching` entities that currently only have English (`en`) content.

### Step 1 — Declare the type translatable

```php
new EntityType(
    id: 'teaching',
    label: 'Teaching',
    class: Teaching::class,
    translatable: true,                                              // NEW
    keys: [
        'id' => 'tid',
        'uuid' => 'uuid',
        'label' => 'title',
        'langcode' => 'langcode',
        'default_langcode' => 'default_langcode',                    // NEW required key
    ],
);
```

### Step 2 — Mark which fields are translatable

```php
new FieldDefinition(name: 'title', type: 'string')->translatable();   // NEW builder
new FieldDefinition(name: 'body', type: 'text')->translatable();
new FieldDefinition(name: 'created_at', type: 'timestamp');           // non-translatable, default
```

### Step 3 — Generate and run the schema migration

```bash
bin/waaseyaa make:migration --add-translations teaching --default-langcode en
bin/waaseyaa migrate
```

### Step 4 — Create a teaching and add its translation (within 10 lines)

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

That's the SC-01 contract: 10 lines of consumer code total.

### Step 5 — Read translations

```php
$teaching = $repository->find($tid);                                  // returns default-langcode (en)
echo $teaching->activeLangcode();                                     // 'en'
echo $teaching->get('title');                                          // 'How birch bark is harvested'

$ojVersion = $teaching->getTranslation('oj');
echo $ojVersion->activeLangcode();                                    // 'oj'
echo $ojVersion->get('title');                                        // 'Aaniin wiigwaas mawagishkemaagong'

// Non-translatable field — same value across translations:
echo $teaching->get('created_at') === $ojVersion->get('created_at');  // true (FR-022)

// Fallback observability:
$missingLang = $teaching->getTranslation('en');
echo $missingLang->fieldLangcode('title');                            // 'en' (resolved at requested langcode)
```

### Step 6 — Remove a translation (NOT the default)

```php
$teaching->removeTranslation('oj');
$repository->save($teaching);                                         // applies the delete in same transaction
```

Attempting `$teaching->removeTranslation('en')` (the default) throws `EntityTranslationException::cannotRemoveDefault('en')`.

### Step 7 — Query all translations

```php
$translations = $repository->findTranslations($teaching);
// ['en' => Teaching($title='How birch bark is harvested'),
//  'oj' => Teaching($title='Aaniin wiigwaas mawagishkemaagong')]
```

Single SQL query (NFR-005).

### Step 8 — Iterate

```php
foreach ($teaching->translations() as $lc) {
    echo "$lc: " . $teaching->getTranslation($lc)->get('title') . "\n";
}
// en: How birch bark is harvested
// oj: Aaniin wiigwaas mawagishkemaagong
```

---

## Edge cases this scenario validates

| Edge case | FR | Demonstrated above |
|---|---|---|
| Missing default_langcode on save | FR-034 | Step 4 sets it explicitly; omitting it throws `langcodeRequired()` |
| Translatable field on non-translatable type | FR-017 | Step 2 declares `created_at` non-translatable; calling `translatable()` on a field of a non-translatable EntityType throws at boot |
| Remove default translation | FR-012 | Step 6 note |
| Add duplicate translation | FR-011 | Calling `addTranslation('oj')` a second time throws `translationAlreadyExists()` |
| Get missing translation | FR-010 | Calling `getTranslation('fr')` when fr doesn't exist throws `translationNotFound()` |
| Non-translatable read across translations | FR-022 | Step 5 `created_at` identity assertion |
| Fallback observability | FR-015 | Step 5 `fieldLangcode()` call |
| Single-query find | NFR-005 | Step 7 |

---

## Quickstart maps to contract tests

The 12 contract tests T01..T12 in spec §9.1 each exercise one step or edge case above:

| Test | Step |
|---|---|
| T01 (`defaultLangcode()` returns expected; throws when unset) | Step 4 (set) + Step 4 negative (omitted) |
| T02 (`activeLangcode()` matches loaded translation) | Step 5 |
| T03 (`hasTranslation` truthy/falsy) | Step 7 + Step 6 |
| T04 (`getTranslation` returns / throws) | Step 5 + Step 6 |
| T05 (`addTranslation` allocates / throws on duplicate) | Step 4 + edge |
| T06 (`removeTranslation($defaultLc)` throws) | Step 6 negative |
| T07 (`removeTranslation($otherLc)` succeeds) | Step 6 |
| T08 (`translations()` lists with default first) | Step 8 |
| T09 (`fieldLangcode()` reports resolved langcode) | Step 5 |
| T10 (non-translatable reads identical) | Step 5 |
| T11 (translatable reads fall through chain) | Step 5 negative — request 'fr' when only 'en'/'oj' exist; resolver yields fallback |
| T12 (fallback exhaustion returns null) | Step 5 ultra-negative — empty content in all chain langcodes returns null |

---

## CLI experience (developer-facing)

```bash
$ bin/waaseyaa make:migration --add-translations teaching --default-langcode en
✓ Created migrations/2026_05_12_000001_add_translations_to_teaching.php
✓ Backfill SQL: 1247 existing rows will be marked langcode=default_langcode='en'
✓ Reverse-migration WARN: drops non-default-langcode rows (data loss)

$ bin/waaseyaa migrate
Running 1 migration:
  → 2026_05_12_000001_add_translations_to_teaching ............... OK
Done in 0.412s.

$ bin/waaseyaa migrate:status
   migrated 2026_05_12_000001_add_translations_to_teaching
```

---

## Developer ergonomics summary

- **Three new constructor / method calls** to flip a type translatable: `translatable: true`, `default_langcode` key, `FieldDefinition::translatable()`.
- **One CLI invocation** to migrate existing data.
- **One method (`addTranslation` + `save` with `SaveContext::withLangcode`)** to author a translation.
- **One method (`getTranslation`)** to read a translation.
- **Symmetry**: removing a translation mirrors adding one.
- **Observability**: `fieldLangcode()` answers "where did this value come from?" without re-running the resolver.

If any step here grows beyond what's shown, the spec's developer-ergonomics goal (SC-01) is broken and the WP must be rejected at review.
