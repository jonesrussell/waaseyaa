# Contract: Waaseyaa\EntityStorage\EntityRepository::findTranslations

**Mission:** M-006 · **Status:** stable surface on merge per stability-charter §5.3
**File:** `packages/entity-storage/src/EntityRepository.php`
**Normative spec:** [`spec.md` FR-042](../spec.md#37-load-semantics--fallback-chain), [`spec.md` NFR-005](../spec.md#4-non-functional-requirements)
**Work package:** WP10

---

## Signature

```php
namespace Waaseyaa\EntityStorage;

class EntityRepository
{
    /**
     * Return every translation of the given entity, keyed by langcode.
     *
     * @return array<string, EntityInterface>
     *   Keys are langcodes; values are entity instances with activeLangcode()
     *   set to the row's langcode.
     */
    public function findTranslations(EntityInterface $entity): array;
}
```

---

## Behaviour

- Returns `[]` if the entity is non-translatable (`EntityType::isTranslatable() === false`). Caller MUST NOT assume non-empty.
- Returns `[$defaultLangcode => $entity]` if the entity has only one translation (the default).
- Returns all extant translations otherwise. Order: default-langcode first, then ascending lex.
- MUST be a single SQL query (no N+1). Asserted via query-count assertion in repository contract test (NFR-005).

---

## Query shape

`sql-column` backend:

```sql
SELECT pri.*, t.langcode, t.<translatable_fields...>
FROM <table>__translation t
INNER JOIN <table> pri ON pri.entity_id = t.entity_id
WHERE pri.entity_id = ?
ORDER BY CASE WHEN t.langcode = pri.default_langcode THEN 0 ELSE 1 END, t.langcode
```

`sql-blob` backend:

```sql
SELECT *
FROM <table>
WHERE entity_id = ?
ORDER BY CASE WHEN langcode = default_langcode THEN 0 ELSE 1 END, langcode
```

Each row materializes to one `EntityInterface` instance via the existing hydrator path, with `activeLangcode` set to the row's `langcode` value.

---

## Test coverage (I03 in spec §9.3)

```php
$conn = $this->countQueries();
$translations = $repository->findTranslations($entity);
$this->assertCount(2, $translations);
$this->assertEquals(1, $conn->queryCount(), 'findTranslations must be a single query');
```
