---
work_package_id: WP13
title: Contract test suite + test_translatable_entity fixture
dependencies:
- WP01
- WP04
- WP05
requirement_refs:
- FR-058
- FR-059
- FR-060
- FR-061
- NFR-003
- NFR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T067
- T068
- T069
- T070
- T071
- T072
history: []
authoritative_surface: packages/entity/testing/
execution_mode: code_change
owned_files:
- packages/entity/testing/TranslatableEntityContractTest.php
- packages/entity/testing/**
- packages/entity/composer.json
- packages/entity-storage/tests/Fixtures/TestTranslatableEntity.php
- packages/entity-storage/tests/Fixtures/test_translatable_entity*
- packages/entity-storage/tests/Contract/SqlBlob*
- packages/entity-storage/tests/Contract/SqlColumn*
tags: []
agent: "claude:opus:waaseyaa-implementer:implementer"
shell_pid: "613513"
---

# WP13 — Contract test suite + test_translatable_entity fixture

## Objective

Ship a reusable contract test base class for `TranslatableInterface`, a fixture entity type that exercises both translatable and non-translatable fields, and two backend-specific subclasses running against `sql-blob` and `sql-column`. Validates NFR-003 (memory share-by-ref) and NFR-004 (CI timing).

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.12 (FR-058..FR-061), §9 (Test surface)
- **Research:** [`../research.md`](../research.md) R7 (testing autoload pattern — CRITICAL)
- **Quickstart:** [`../quickstart.md`](../quickstart.md) (T01..T12 mapping)

## Subtasks

### T067 — `TranslatableEntityContractTest` base class

**Steps:**

1. Create `packages/entity/testing/TranslatableEntityContractTest.php`:
   ```php
   <?php
   declare(strict_types=1);
   namespace Waaseyaa\Entity\Testing;

   use PHPUnit\Framework\TestCase;
   use PHPUnit\Framework\Attributes\Test;
   use PHPUnit\Framework\Attributes\CoversNothing;

   #[CoversNothing]
   abstract class TranslatableEntityContractTest extends TestCase
   {
       abstract protected function makeRepository(): EntityRepository;
       abstract protected function fixtureEntityTypeId(): string;
       // ... T01-T12 tests below ...
   }
   ```
2. **CRITICAL:** Register this directory under `autoload-dev` in `packages/entity/composer.json`:
   ```json
   "autoload-dev": {
       "psr-4": {
           "Waaseyaa\\Entity\\Testing\\": "testing/"
       }
   }
   ```
   **DO NOT** put it under `autoload`. Production installs reflection-scan `autoload` paths; a test base class extending `PHPUnit\Framework\TestCase` placed there will crash kernel boot with `Class "PHPUnit\Framework\TestCase" not found`. This is the documented graphql alpha.107 lesson.

**Files:** `packages/entity/testing/TranslatableEntityContractTest.php` (new, scaffolding ~50 lines), `packages/entity/composer.json` (modify, ~5 lines).

### T068 — T01..T12 abstract contract tests

**Steps:**

Implement each of the 12 tests from spec §9.1 / quickstart.md:

- T01: `defaultLangcode()` returns expected; throws when unset.
- T02: `activeLangcode()` matches loaded translation.
- T03: `hasTranslation($lc)` truthy/falsy.
- T04: `getTranslation($lc)` returns instance; throws on missing.
- T05: `addTranslation($lc)` allocates; throws on duplicate.
- T06: `removeTranslation($defaultLc)` throws.
- T07: `removeTranslation($otherLc)` succeeds; row gone after save.
- T08: `translations()` lists with default first.
- T09: `fieldLangcode($field)` reports correct resolved langcode.
- T10: Non-translatable field reads identical across translations (shared by reference assertion for NFR-003).
- T11: Translatable field reads fall through configured chain.
- T12: Fallback exhaustion returns `null`, `fieldLangcode()` returns `null`.

Each test uses `$this->makeRepository()` and `$this->fixtureEntityTypeId()` so subclasses can wire in their own setup.

**Files:** ~400 lines.

### T069 — `TestTranslatableEntity` fixture entity type

**Steps:**

1. Create `packages/entity-storage/tests/Fixtures/TestTranslatableEntity.php`:
   ```php
   <?php
   declare(strict_types=1);
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
2. Create a companion EntityType + FieldDefinition registry helper (`packages/entity-storage/tests/Fixtures/test_translatable_entity_type_factory.php`):
   - Translatable fields: `title` (string), `body` (text).
   - Non-translatable fields: `created_at` (timestamp), `author_id` (entity_reference).

**Files:** ~120 lines.

### T070 — sql-blob subclass

**Steps:**

1. Create `packages/entity-storage/tests/Contract/SqlBlobTranslatableContractTest.php`:
   ```php
   final class SqlBlobTranslatableContractTest extends TranslatableEntityContractTest
   {
       protected function makeRepository(): EntityRepository
       {
           $db = DBALDatabase::createSqlite();
           // ... wire backend with sql-blob default ...
           return new EntityRepository(/* sql-blob deps */);
       }

       protected function fixtureEntityTypeId(): string
       {
           return 'test_translatable_entity';
       }
   }
   ```

**Files:** ~80 lines.

### T071 — sql-column subclass

**Steps:**

1. Create `packages/entity-storage/tests/Contract/SqlColumnTranslatableContractTest.php`. Same shape as T070 but with sql-column primary backend.

**Files:** ~80 lines.

### T072 — CI timing + reference-equality assertions

**Steps:**

1. NFR-004 (CI timing): wrap test invocations with `microtime(true)` checkpoints. Assert each backend's contract suite runs in < 10s wall.
   - Easier: use PHPUnit's `--testdox` + a wall-clock parser, or simply log per-test timing and have a separate CI check.
2. NFR-003 (memory): in T10, assert reference equality:
   ```php
   $en = $entity;
   $oj = $entity->getTranslation('oj');
   $this->assertSame($en->get('created_at'), $oj->get('created_at'));
   // Non-translatable field values shared by reference; same instance.
   ```

**Files:** Test wrapper + assertions (~60 lines).

## Definition of Done

- [ ] `TranslatableEntityContractTest` base class shipped under `packages/entity/testing/`.
- [ ] `packages/entity/composer.json` registers `testing/` under `autoload-dev`.
- [ ] 12 abstract test methods implemented (T01..T12).
- [ ] `TestTranslatableEntity` fixture + EntityType factory shipped.
- [ ] sql-blob and sql-column subclasses pass all 12 tests.
- [ ] NFR-004 timing assertion + NFR-003 reference-equality assertion.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.
- [ ] **CRITICAL:** Production install (`composer install --no-dev`) does NOT load `TranslatableEntityContractTest`. Verify by inspecting the resulting classmap.

## Risks

| Risk | Mitigation |
|---|---|
| `autoload` placement mistake → production crash. | DOUBLE-CHECK `composer.json`: `autoload-dev`, not `autoload`. Test by running `composer install --no-dev` in a scratch dir; verify `Waaseyaa\Entity\Testing\` namespace is NOT in `vendor/composer/autoload_psr4.php`. |
| Test fixtures couple too tightly to InMemoryEntityStorage / DBAL details. | Abstract via `makeRepository()` — subclasses choose how to wire. |
| CI timing assertion can be flaky on slow hardware. | Set the threshold generously (e.g. 30s for CI environments); fail-fast at 60s with a clear message. NFR-004 says <10s on CI hardware; tune to the actual CI runner. |

## Reviewer guidance

- **Verify `composer.json` placement** — this is the highest-risk item. Inspect `autoload-dev` keys; assert the path string ends in `testing/`.
- Verify the reference-equality assertion in T10 actually checks `assertSame` (not `assertEquals`).
- Verify the contract tests can run independently per backend (no shared mutable state).

## Implementation command

```bash
spec-kitty agent action implement WP13 --agent <name>
```

## Activity Log

- 2026-05-13T00:34:31Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=613513 – Started implementation via action command
- 2026-05-13T00:45:27Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=613513 – Contract test base + fixture entity type + sql-blob/sql-column subclasses. autoload-dev verified. NFR-003 share-by-ref + NFR-004 timing assertions
