# Contract — `SaveContext::withTranslations(array)` builder

**Status:** Normative for WP03.
**Refs:** FR-012, FR-013, FR-014; spec §3.2 + §6.2.

---

## 1. Scope

Extends the existing `Waaseyaa\EntityStorage\SaveContext` value object (shipped by M-006 with `withoutNewRevision()`, `withLangcode($langcode)`, `asImport()`) with one new builder and one new readonly property for multi-language atomic saves.

## 2. Class shape (post-extension)

```php
namespace Waaseyaa\EntityStorage;

final class SaveContext
{
    private function __construct(
        public readonly bool $withoutNewRevision = false,
        public readonly ?string $langcode = null,
        public readonly bool $isImport = false,
        public readonly ?array $translations = null,   // NEW: ?list<string>
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public function withoutNewRevision(): self { /* unchanged */ }
    public function withLangcode(string $langcode): self { /* unchanged */ }
    public function asImport(): self { /* unchanged */ }

    /**
     * Pin the save to a list of langcodes for atomic multi-language write.
     *
     * @param list<non-empty-string> $langcodes
     * @throws \InvalidArgumentException When $langcodes is empty or contains an empty string.
     */
    public function withTranslations(array $langcodes): self
    {
        if ($langcodes === []) {
            throw new \InvalidArgumentException('SaveContext::withTranslations requires a non-empty list of langcodes.');
        }
        foreach ($langcodes as $lc) {
            if (!\is_string($lc) || $lc === '') {
                throw new \InvalidArgumentException('SaveContext::withTranslations requires non-empty string langcodes.');
            }
        }
        return new self(
            withoutNewRevision: $this->withoutNewRevision,
            langcode: $this->langcode,
            isImport: $this->isImport,
            translations: array_values($langcodes),
        );
    }
}
```

## 3. Precedence rule

When BOTH `$langcode` and `$translations` are non-null:

- The storage coordinator uses `$translations` (multi-language atomic save).
- `$langcode` is ignored for the duration of the save.
- This is NOT an error — callers may legitimately layer `withLangcode()` then `withTranslations()` in fluent style.

When `$translations` is null:

- Coordinator falls back to single-language save semantics (M-006 unchanged): `$langcode` if set, otherwise `$entity->activeLangcode()`.

## 4. Save algorithm extension (§6.2)

```
$ctx = SaveContext::default()->withTranslations(['en', 'oj', 'fr']);
$storage->save($entity, $ctx);

  1. coordinator opens transaction.
  2. for each langcode in $ctx->translations:
       a. fire BeforeSaveEvent (langcode=current).
       b. apply per-langcode write per single-language path (§6.1).
       c. (collect affectedLangcodes for the post-commit event).
  3. if any write raises an exception: rollback transaction; raise PartialSaveException (ADR 010 §6.5).
  4. fire AfterSaveEvent ONCE with affectedLangcodes = ['en', 'oj', 'fr'].
  5. commit transaction.
```

### 4.1 Event semantics (FR-014)

- `BeforeSaveEvent` fires **per langcode** (one event per `$translations` element). Subscribers may inspect `$event->saveContext->langcode` to see which langcode is being processed (the coordinator sets `langcode` on the per-iteration `SaveContext` clone).
- `AfterSaveEvent` fires **once at commit** with `affectedLangcodes()` returning the full list. This drives the cache-invalidation path (M-007's `ListingCacheInvalidator`) once per atomic save, not once per langcode.

  - **Rationale.** N `AfterSaveEvent` fires would cause `ListingCacheInvalidator` to issue N invalidation calls, each with a single-langcode list. One event with the full list is semantically equivalent (the invalidator iterates over `affectedLangcodes()` to emit tags) and simpler for subscribers.

## 5. Edge cases

| Case | Behavior |
|---|---|
| `withTranslations([])` | `\InvalidArgumentException` from the builder. |
| `withTranslations(['en'])` (single langcode) | Equivalent semantics to `withLangcode('en')` but still routes through the multi-language transaction wrapper (atomicity guarantee). |
| `withTranslations(['en', 'en'])` (duplicate) | Builder accepts (no dedup); coordinator MUST deduplicate internally (sets are not stable in PHP arrays). Test asserts duplicate is processed once. |
| `withTranslations(['xx'])` where `'xx'` is not a registered langcode | Coordinator raises `EntityTranslationException::translationNotFound('xx')` on first write attempt; rollback. |
| `withTranslations(['en', 'fr'])` on a non-translatable entity type | Coordinator raises `EntityTranslationException::notTranslatable($entityTypeId)` on first write attempt; rollback. |
| `withTranslations(['en', 'fr'])` AND `withLangcode('de')` | `withLangcode('de')` is silently ignored; save proceeds for ['en', 'fr']. |
| `withTranslations(['en', 'fr'])` AND `withoutNewRevision()` | Each per-langcode write suppresses revision creation; updates current revisions in place. |
| `withTranslations(['en', 'fr'])` AND `asImport()` | Migration-platform multi-language import (FR-022). Subscribers see `$saveContext->isImport === true` on each per-langcode event. |

## 6. Test contract (WP03)

`SaveContextTranslationsTest` (unit):

1. `withTranslations([])` raises `\InvalidArgumentException`.
2. `withTranslations([''])` raises `\InvalidArgumentException`.
3. `withTranslations(['en', 'fr'])` returns NEW instance; original unchanged.
4. Builder is composable with `withoutNewRevision()`, `asImport()`, `withLangcode()`.
5. `$ctx->translations === ['en', 'fr']` after `withTranslations(['en', 'fr'])`.

`TwoAxisSaveLoadIntegrationTest` (integration, `tests/Integration/PhaseN/`):

1. Multi-language save of `teaching` with `['en', 'oj', 'fr']` produces 3 new translation-revision rows.
2. `AfterSaveEvent` fires once; `affectedLangcodes()` = `['en', 'oj', 'fr']`.
3. Partial failure (e.g. fixture langcode `'broken'` rigged to throw) raises `PartialSaveException`; all 3 saves rolled back.
4. `withTranslations(['en'])` and `withLangcode('en')` produce identical persisted state.

## 7. Stable surface

`SaveContext::withTranslations(array $langcodes): self` lands on charter §5.3 stable-surface map at mission close (WP08). The `?array $translations` readonly property is implementation detail (not stable surface) — consumers MUST use the builder, not direct property access via reflection.
