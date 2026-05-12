---
work_package_id: WP10
title: "EntityRepository: findTranslations() + LanguageManager wire-up"
dependencies:
- WP06
requirement_refs:
- FR-040
- FR-041
- FR-042
- NFR-005
- C-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T032
- T033
- T051
- T052
- T053
- T054
- T055
- T056
history: []
authoritative_surface: packages/entity-storage/
execution_mode: code_change
owned_files:
- packages/entity-storage/src/EntityRepository.php
- packages/entity-storage/tests/Repository/FindTranslations*
- packages/entity-storage/tests/Repository/FallbackChain*
- packages/entity-storage/tests/Repository/LanguageManager*
tags: []
agent: "claude:opus:waaseyaa-implementer:implementer"
shell_pid: "599488"
---

# WP10 — EntityRepository: findTranslations() + LanguageManager wire-up

## Objective

Sole owner of `EntityRepository.php` changes for this mission. Two surfaces:

1. **`findTranslations(EntityInterface): array<string, EntityInterface>`** — return all translations of an entity, langcode-keyed, default-first, single query (NFR-005).
2. **LanguageManager wire-up on `find()`** — accept nullable `LanguageManagerInterface` + `readActiveLanguage` flag; when both set, `find()` returns the active-language translation if available (FR-040, FR-041, C-004).

## Context

- **Spec:** [`../spec.md`](../spec.md) FR-040, FR-041, FR-042, NFR-005, C-004
- **Contracts:** [`../contracts/EntityRepository.findTranslations.md`](../contracts/EntityRepository.findTranslations.md)
- **Research:** [`../research.md`](../research.md) R10, R12

## Subtasks

### T032 — `EntityRepository` accepts nullable `LanguageManagerInterface`

**Steps:**

1. Open `packages/entity-storage/src/EntityRepository.php`. Extend the constructor:
   ```php
   public function __construct(
       // ... existing params ...
       private ?LanguageManagerInterface $languageManager = null,
       private bool $readActiveLanguage = false,
   ) {}
   ```
2. Document `C-004` — `LanguageManager` is optional DI; absence yields default-langcode reads always. CLI / queue contexts (no HTTP) get deterministic default behavior.

**Files:** modify, ~10 lines.

### T033 — Wire `LanguageManager` into `find()`

**Steps:**

1. In `EntityRepository::find($id)`:
   ```php
   $entity = $this->loadDefaultTranslation($id);                     // existing behaviour
   if ($entity instanceof TranslatableInterface
       && $this->languageManager !== null
       && $this->readActiveLanguage
   ) {
       $active = $this->languageManager->getCurrent()->id();
       if ($active !== $entity->defaultLangcode() && $entity->hasTranslation($active)) {
           return $entity->getTranslation($active);
       }
   }
   return $entity;
   ```

2. Tests:
   - `EntityRepository::find()` without LanguageManager: always returns default langcode.
   - With LanguageManager + `readActiveLanguage: false`: returns default.
   - With LanguageManager + `readActiveLanguage: true` + active='oj' + hasTranslation('oj'): returns 'oj' translation.
   - With LanguageManager + `readActiveLanguage: true` + active='oj' + !hasTranslation('oj'): returns default ('en').

**Files:** modify, ~20 lines + ~150 lines of tests.

## Subtasks (continued — findTranslations)

### T051 — `findTranslations()` method

**Steps:**

1. Open `packages/entity-storage/src/EntityRepository.php`. Add:
   ```php
   /**
    * @return array<string, EntityInterface>
    */
   public function findTranslations(EntityInterface $entity): array
   {
       if (!$entity->getEntityType()->isTranslatable()) {
           return [];
       }
       // dispatch to backend per primary storage
       return $this->backend->findAllTranslations($entity);
   }
   ```
2. The actual SQL execution lives in the backend (sql-blob OR sql-column); the repository just dispatches.

**Files:** `packages/entity-storage/src/EntityRepository.php` (modify, ~20 lines).

### T052 — sql-column INNER JOIN

**Steps:**

1. In the sql-column backend (probably `SqlColumnBackend`):
   ```php
   public function findAllTranslations(EntityInterface $entity): array
   {
       $sql = "SELECT pri.*, t.langcode AS _t_langcode, t.<translatable_columns>
               FROM {$table}__translation t
               INNER JOIN {$table} pri ON pri.entity_id = t.entity_id
               WHERE pri.entity_id = ?
               ORDER BY CASE WHEN t.langcode = pri.default_langcode THEN 0 ELSE 1 END, t.langcode";
       $rows = $this->db->fetchAllAssociative($sql, [$entity->id()]);
       $result = [];
       foreach ($rows as $row) {
           $lc = $row['_t_langcode'];
           $result[$lc] = $this->hydrator->hydrate($row, $entity->getEntityType(), $lc);
       }
       return $result;
   }
   ```
2. Use DBAL identifier quoting consistently — never raw-interpolate column names from user input.

**Files:** sql-column backend (~40 lines added).

### T053 — sql-blob query

**Steps:**

1. In the sql-blob backend:
   ```php
   public function findAllTranslations(EntityInterface $entity): array
   {
       $sql = "SELECT *
               FROM {$table}
               WHERE entity_id = ?
               ORDER BY CASE WHEN langcode = default_langcode THEN 0 ELSE 1 END, langcode";
       $rows = $this->db->fetchAllAssociative($sql, [$entity->id()]);
       $result = [];
       foreach ($rows as $row) {
           $lc = $row['langcode'];
           $result[$lc] = $this->hydrator->hydrate($row, $entity->getEntityType(), $lc);
       }
       return $result;
   }
   ```

**Files:** sql-blob backend (~30 lines added).

### T054 — Hydration with activeLangcode

**Steps:**

1. Each result row hydrates into an entity instance with `activeLangcode = $row['langcode']` (or `_t_langcode` in sql-column).
2. Verify `_setTranslationData($map, $activeLangcode)` is called once with the full map AND the active langcode for THIS instance — each result-set entity instance has its own `activeLangcode`, but they share the `translationData` map by reference (memory NFR-003).

**Files:** Hydrator (~20 lines added).

### T055 — Query-count assertion (NFR-005)

**Steps:**

1. Create a `QueryCountingProxy` (or use existing in `packages/entity-storage/tests/Helpers/`):
   - Wraps `DatabaseInterface`; increments a counter on each `executeQuery`/`fetchAllAssociative` call.
2. In test:
   ```php
   $repo = new EntityRepository(/* deps */, $this->countingDb);
   $repo->findTranslations($entity);
   $this->assertSame(1, $this->countingDb->queryCount(), 'findTranslations MUST be a single query');
   ```

**Files:** Helper + test (~80 lines).

### T056 — Unit + integration tests

**Steps:**

1. `findTranslations()` returns `[]` for non-translatable types.
2. `findTranslations()` returns `[default_lc => $entity]` for translatable entity with only default translation.
3. `findTranslations()` returns all extant translations, default-langcode-first.
4. Each result instance's `activeLangcode()` matches its key.
5. Each result instance shares non-translatable field values by reference (NFR-003 — verify identity).
6. Query-count assertion (T055).
7. Cross-backend test: run all assertions on both sql-blob and sql-column.

**Files:** ~250 lines.

## Definition of Done

- [ ] `findTranslations()` on `EntityRepository` returns langcode-keyed map.
- [ ] Single SQL query per backend (asserted).
- [ ] Order: default-langcode first, then ascending lex.
- [ ] All tests pass on both backends.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| File-ownership overlap with WP06 (same `EntityRepository.php`). | Lane sequencing: WP06 lands first; WP10 starts from WP06's HEAD. |
| ORDER BY CASE syntax differs across SQL dialects. | Verify SQLite, MySQL, PostgreSQL all parse identically (they do for this form). |

## Reviewer guidance

- Verify the query-count assertion uses a real counting proxy, not a mock.
- Verify non-translatable types short-circuit (no query) and return `[]`.
- Verify the hydrator does not allocate one full entity-field-array per translation (shared by reference per NFR-003).

## Implementation command

```bash
spec-kitty agent action implement WP10 --agent <name>
```

## Activity Log

- 2026-05-12T23:54:48Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=599488 – Started implementation via action command
