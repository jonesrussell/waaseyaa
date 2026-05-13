---
work_package_id: WP01
title: Plugin contracts + provider capability + registration
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-migration-platform-v1-01KRCDE9
base_commit: e07dbb92809aa44019f698ec2f0bf19e9d955c9b
created_at: '2026-05-13T02:56:06.105055+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
shell_pid: "640344"
agent: "claude:opus:waaseyaa-reviewer:reviewer"
history:
- timestamp: '2026-05-13T02:27:32Z'
  actor: spec-kitty.tasks
  event: wp_created
  notes: Generated as part of M-002 task materialization.
authoritative_surface: packages/migration/src/Plugin/
execution_mode: code_change
mission_id: 01KRCDE9ZXK2JEFPT6THSBVKNY
mission_slug: migration-platform-v1-01KRCDE9
owned_files:
- packages/migration/composer.json
- packages/migration/src/ServiceProvider.php
- packages/migration/src/Plugin/SourcePluginInterface.php
- packages/migration/src/Plugin/ProcessPluginInterface.php
- packages/migration/src/Plugin/DestinationPluginInterface.php
- packages/migration/src/Plugin/ReservedPluginIds.php
- packages/migration/src/Discovery/HasMigrationPluginsInterface.php
- packages/migration/src/Discovery/PluginRegistry.php
- packages/migration/src/Exception/MigrationPluginCollisionException.php
- packages/migration/src/Log/Channels.php
- packages/migration/src/Plugin/SourceRecord.php
- packages/migration/src/Plugin/DestinationRecord.php
- packages/migration/src/Plugin/WriteResult.php
- packages/migration/src/Plugin/ProcessContext.php
- packages/migration/tests/Unit/Plugin/PluginInterfacesContractTest.php
- packages/migration/tests/Unit/Plugin/DtoValueObjectsTest.php
- packages/migration/tests/Unit/Plugin/ReservedPluginIdsTest.php
- packages/migration/tests/Unit/Discovery/PluginRegistryTest.php
- composer.json
priority: p1
tags:
- scaffold
- stable-surface
- layer-3
---

# WP01 — Plugin contracts + provider capability + registration

## Objective

Establish the new `packages/migration/` package at Layer 3 (Services) and ship the three top-level plugin contracts (`SourcePluginInterface`, `ProcessPluginInterface`, `DestinationPluginInterface`), the four DTO value objects (`SourceRecord`, `DestinationRecord`, `WriteResult`, `ProcessContext`), the provider capability (`HasMigrationPluginsInterface`), and the boot-time plugin registry. This WP is the substrate every other WP in this mission sits on; nothing else can start until the contracts compile and the package autoloads.

The deliverables in this WP are charter §5.8 (proposed) stable surface. Every public symbol carries `@api`. No upward layer imports. No service-locator patterns.

## Dependencies

- Internal: None.
- External: None. (`packages/foundation`, `packages/entity`, `packages/entity-storage` already exist; this WP requires no work in them.)
- Charter anchors: This WP delivers the bulk of the §5.8 surface — plugin interfaces, provider capability, DTO value objects, plugin-collision exception, `migration.deprecation` log channel constant. WP12 amends the charter to record §5.8.

## Scope (in / out)

**In scope**
- New `packages/migration/composer.json` with `extra.waaseyaa.providers` declaring `Waaseyaa\Migration\ServiceProvider`.
- Root `composer.json` updated: `repositories` entry for `packages/migration` (path repo) and the package added to the workspace require block.
- Three plugin interfaces (`SourcePluginInterface`, `ProcessPluginInterface`, `DestinationPluginInterface`) — FR-001, FR-003, FR-005.
- Four DTO value objects (`SourceRecord`, `DestinationRecord`, `WriteResult`, `ProcessContext`) — FR-002, FR-004, FR-006.
- `HasMigrationPluginsInterface` provider capability + `PluginRegistry` boot scanner — FR-007.
- `MigrationPluginCollisionException` typed exception with both registering FQCNs — FR-008.
- `id()` + `stability()` shape on every plugin interface; deprecation notice emitted on first use of an experimental plugin per process — FR-009.
- Process-plugin chain support via the array shape (chain semantics encoded in `ProcessContext`'s contract, not in the interface — chains compose at runtime) — FR-010.
- Reserved-id constants (`ReservedPluginIds::PASS_THROUGH`, etc.) — backing for §5.4 of the spec.
- `migration.deprecation` log channel constant (referenced from FR-009 deprecation notices).

**Out of scope**
- Concrete process-plugin implementations (WP03).
- `MigrationDefinition` value object + manifest discovery (WP02).
- `EntityDestination` (WP05).
- ID-map and `SourceId` (WP04). `SourceId` is declared *as a parameter type* on `SourcePluginInterface::sourceIdFor()` — the interface signature lives here, but the concrete `SourceId` value object lives in WP04. To unblock WP01, declare a forward `Waaseyaa\Migration\SourceId` namespace import and document that the concrete class lands in WP04. The interface compiles because PHP resolves the symbol lazily.

Wait — re-evaluating: the safer pattern is for WP01 to ship a minimal `SourceId` stub (final readonly class with `public string $sourceType` + `public array $keys` + a method-stub `hash(): string` that throws `\LogicException('Implemented in WP04')`) under `packages/migration/src/SourceId.php`, then have WP04 own the full implementation. **WP04 will replace the stub.** Document the stub in T002 below.

Same pattern for `WriteResult` returning `?SourceId` and for `ProcessContext` lookup callable — minimal forward stubs here, full implementations in WP02/WP04.

## Branch strategy

Planning/base branch: `main`. Merge target: `main`. Execution worktree allocated per-lane at finalize-tasks time. The owning agent runs `spec-kitty agent action implement WP01 --agent opus` from the project root to enter its worktree.

## Cross-cutting modifications

This WP creates a new package and therefore must touch the **root `composer.json`** of the monorepo (the workspace manifest). Two edits:

1. Add `{"type": "path", "url": "packages/migration", "options": {"symlink": true}}` to `repositories`.
2. Add `"waaseyaa/migration": "self.version"` to the `require` block.

Both edits are governed by the codified Composer policy (`bin/check-composer-policy`); WP01 must leave the policy green. The `self.version` form is required (CP006) because the root `composer.json` is the published `waaseyaa/framework` metapackage.

After the package is declared, run `composer dump-autoload --optimize` so `PackageManifestCompiler` can find `Waaseyaa\Migration\ServiceProvider`.

## Implementation guidance

### Subtask T001 — Scaffold `packages/migration/composer.json` + `ServiceProvider.php` + workspace wiring

**Purpose**: Create the new Layer 3 package skeleton so the rest of WP01 can use the `Waaseyaa\Migration\` namespace. Establishes the autoload-dev convention WP10 will rely on.

**FRs covered**: FR-007 (provider capability registration scaffold).

**Files**:
- `packages/migration/composer.json` (new) — model on `packages/seo/composer.json` and `packages/workflows/composer.json` (same Layer 3 shape).
- `packages/migration/src/ServiceProvider.php` (new, ~80 lines).
- `packages/migration/.gitkeep` (or any minimal file so the directory commits — optional).
- Root `composer.json` (modify) — add to `repositories` + `require`.

**Steps**:
1. Copy the structure of `packages/seo/composer.json` verbatim. Replace `seo`→`migration`, `Seo`→`Migration`. Drop `twig/twig` from `require` (this package has no Twig surface). Keep PHP `>=8.5` and `phpunit/phpunit: ^10.5` in `require-dev`. Add `"waaseyaa/foundation": "^<current-tag>"`, `"waaseyaa/entity": "^<current-tag>"`, `"waaseyaa/entity-storage": "^<current-tag>"`, `"waaseyaa/access": "^<current-tag>"`, `"waaseyaa/cli": "^<current-tag>"`. Determine `<current-tag>` by running `git describe --tags --abbrev=0 --match='v*.*.*'` at implementation time; `bin/sync-internal-versions` will keep these in lockstep at release-cut.
2. Add the `autoload` block: `{"psr-4": {"Waaseyaa\\Migration\\": "src/"}}`.
3. Add the `autoload-dev` block: `{"psr-4": {"Waaseyaa\\Migration\\Tests\\": "tests/", "Waaseyaa\\Migration\\Testing\\": "testing/"}}`. The `Testing/` namespace is required so WP10 can ship `SourceConformanceTestCase`/`DestinationConformanceTestCase` as `autoload-dev` only (CLAUDE.md gotcha: "Never put classes that extend dev-only deps under autoload").
4. Add `extra.waaseyaa.providers`: `["Waaseyaa\\Migration\\ServiceProvider"]`. Add `extra.branch-alias` block mirroring `packages/seo/composer.json`. Add `config.sort-packages: true` (CP001).
5. Create `packages/migration/src/ServiceProvider.php` extending `Waaseyaa\Foundation\Plugin\ServiceProvider`. In `register()`: bind `PluginRegistry` as a singleton (lazy — populated at boot). In `boot()`: discover `HasMigrationPluginsInterface` providers via the existing `PackageManifestCompiler` capability scan, populate the registry, mark immutable. No event subscribers in this WP.
6. Edit root `composer.json`: add the path repository (`{"type": "path", "url": "packages/migration", "options": {"symlink": true}}`) and append `"waaseyaa/migration": "self.version"` to `require`.
7. Run `composer dump-autoload --optimize` (verify locally; the merging agent will rerun in CI).

**Validation**:
- [ ] `composer validate packages/migration/composer.json` exits 0.
- [ ] `composer check-composer-policy` exits 0 (CP001, CP002, CP003, CP006, CP-NEW all green).
- [ ] `composer dump-autoload --optimize` succeeds with no warnings about missing classes.
- [ ] `bin/check-package-layers` exits 0 (no upward edges).

**Edge cases**:
- If `bin/check-composer-policy` flags CP-NEW (current-tag literal mismatch), the implementer must use the exact output of `git describe --tags --abbrev=0 --match='v*.*.*'` from the implementation worktree, not a guessed value. CP-NEW reads the literal from disk.

### Subtask T002 — `SourcePluginInterface` + `SourceRecord` DTO + `SourceId` stub

**Purpose**: Ship the canonical source-plugin contract and the minimal stable-surface DTOs it returns.

**FRs covered**: FR-001, FR-002.

**Files**:
- `packages/migration/src/Plugin/SourcePluginInterface.php` (new, ~50 lines including PHPDoc).
- `packages/migration/src/Plugin/SourceRecord.php` (new, ~40 lines; `final readonly class`).
- `packages/migration/src/SourceId.php` (new, ~50 lines; stub — full impl in WP04).

**Steps**:
1. Define `SourcePluginInterface` (interface, `@api`):
   ```php
   public function id(): string;
   public function stability(): string;             // 'stable' | 'experimental'
   public function records(): iterable;             // yields SourceRecord
   public function sourceIdFor(SourceRecord $record): SourceId;
   public function count(): ?int;
   ```
2. Define `SourceRecord` (`final readonly class`, `@api`): `public string $sourceType`, `public array $fields` (associative). Constructor validates `$sourceType` is non-empty and matches `/^[a-z][a-z0-9_]*$/`. Add `field(string $name, mixed $default = null): mixed` convenience accessor.
3. Define `SourceId` stub (`final readonly class`, `@api`): `public string $sourceType`, `public array $keys`. `hash(): string` throws `\LogicException('SourceId::hash() not yet implemented — landing in WP04')` so accidental early callers fail loudly. Constructor validates both fields. WP04 will replace this file.
4. All three classes get `declare(strict_types=1);` and `@api` PHPDoc.

**Validation**:
- [ ] `composer phpstan` clean for `packages/migration/`.
- [ ] PHPUnit fixture instantiates a `SourceRecord` and asserts the field accessor works.

**Edge cases**:
- A source plugin that yields zero records is valid — `count(): ?int` may return 0 or null. Document in PHPDoc.
- `sourceType` is the source-format identifier (e.g. `'wordpress_post'`), not the destination entity type. Clarify in PHPDoc.

### Subtask T003 — `ProcessPluginInterface` + `ProcessContext` DTO

**Purpose**: Ship the canonical process-plugin contract and the context object passed to every `transform()` call.

**FRs covered**: FR-003, FR-004, FR-010 (the interface admits chaining; the runner composes chains).

**Files**:
- `packages/migration/src/Plugin/ProcessPluginInterface.php` (new, ~45 lines).
- `packages/migration/src/Plugin/ProcessContext.php` (new, ~80 lines; `final readonly class`).

**Steps**:
1. Define `ProcessPluginInterface` (interface, `@api`):
   ```php
   public function id(): string;
   public function stability(): string;
   public function transform(mixed $value, ProcessContext $context): mixed;
   ```
2. Define `ProcessContext` (`final readonly class`, `@api`):
   - `public SourceRecord $sourceRecord` — the source row currently being processed.
   - `public string $migrationId` — the id of the running migration.
   - `public string $destinationField` — the destination field name being computed.
   - `public \Closure $lookup` — `(string $migrationId, SourceId $sourceId): ?WriteResult`. The runner injects this so process plugins (notably `LookupProcessor`) can resolve cross-migration references. Stub the closure here; WP04/WP06 wire the real implementation.
3. PHPDoc on `transform()` explains chain semantics: when multiple plugins are declared for one destination field, the runner threads the output of plugin N into the input of plugin N+1 via the same `ProcessContext` (only the `$value` argument changes).

**Validation**:
- [ ] Unit test: passing a `ProcessContext` to a fake processor that calls `$context->lookup` confirms the closure is invocable.
- [ ] PHPStan clean.

**Edge cases**:
- `transform()` may return any type — chain plugins are responsible for type compatibility. The framework does not enforce per-step type signatures.

### Subtask T004 — `DestinationPluginInterface` + `DestinationRecord` + `WriteResult` DTOs

**Purpose**: Ship the canonical destination-plugin contract.

**FRs covered**: FR-005, FR-006.

**Files**:
- `packages/migration/src/Plugin/DestinationPluginInterface.php` (new, ~55 lines).
- `packages/migration/src/Plugin/DestinationRecord.php` (new, ~50 lines; `final readonly class`).
- `packages/migration/src/Plugin/WriteResult.php` (new, ~50 lines; `final readonly class`).

**Steps**:
1. Define `DestinationPluginInterface` (interface, `@api`):
   ```php
   public function id(): string;
   public function stability(): string;
   public function write(DestinationRecord $record): WriteResult;
   public function rollback(WriteResult $result): void;
   public function lookup(SourceId $sourceId): ?WriteResult;
   ```
2. Define `DestinationRecord`:
   - `public string $migrationId`
   - `public SourceId $sourceId`
   - `public array $values` — destination field name → processed value.
   - `public ?string $bundle` — optional bundle id; resolved at write time per FR-024 / decision D8.
   - `public ?string $langcode` — optional language code.
3. Define `WriteResult`:
   - `public string $destinationEntityType`
   - `public string $destinationUuid`
   - `public string $sourceRecordHash` — sha256 of canonical-form `DestinationRecord::$values` (full implementation in WP04; for WP01 the field is just a string).
   - `public string $runId` — UUIDv7 of the producing run.
   - `public string $writtenAt` — ISO 8601 UTC.

**Validation**:
- [ ] PHPStan clean.
- [ ] Unit test: instantiate `WriteResult` with stub strings and assert the readonly invariants hold.

**Edge cases**:
- `rollback()` may be called on a `WriteResult` whose underlying entity has already been deleted by other means. Implementations must treat that as a no-op + warn (handled by WP05 / WP08; the interface admits it).

### Subtask T005 — `HasMigrationPluginsInterface` provider capability + `PluginRegistry`

**Purpose**: Wire the boot-time scan that discovers plugins via the existing provider mechanism.

**FRs covered**: FR-007, FR-008, FR-009.

**Files**:
- `packages/migration/src/Discovery/HasMigrationPluginsInterface.php` (new, ~30 lines).
- `packages/migration/src/Discovery/PluginRegistry.php` (new, ~180 lines).
- `packages/migration/src/Plugin/ReservedPluginIds.php` (new, ~25 lines; `final class` holding `public const` for the six reserved ids).
- `packages/migration/src/Log/Channels.php` (new, ~20 lines; `final class` with `public const MIGRATION_DEPRECATION = 'migration.deprecation'`).

**Steps**:
1. Define `HasMigrationPluginsInterface` (marker capability, `@api`):
   ```php
   /** @return iterable<SourcePluginInterface|ProcessPluginInterface|DestinationPluginInterface> */
   public function migrationPlugins(): iterable;
   ```
2. Define `PluginRegistry` (`final class`, `@api`):
   - Constructor accepts `?LoggerInterface $logger = null` and `array $providers = []`.
   - `boot()` iterates providers in Composer `installed.json` order, calls `migrationPlugins()`, and indexes plugins by `id()` into three internal maps (source, process, destination).
   - Duplicate id → `MigrationPluginCollisionException` carrying both registering FQCNs.
   - Third-party registration of a reserved id (anything matching `ReservedPluginIds::*`) → `MigrationPluginCollisionException` with the reserved-id flag set, unless the registering FQCN is under `Waaseyaa\\Migration\\`.
   - First-use deprecation: a per-process set `$deprecationFired` tracks plugin ids that have emitted on `Channels::MIGRATION_DEPRECATION`; experimental plugins fire once via `LoggerInterface::warning()`. Use the project's `Waaseyaa\Foundation\Log\LoggerInterface` (CLAUDE.md gotcha: no `psr/log`).
   - `getSource(string $id): SourcePluginInterface`, `getProcess(string $id): ProcessPluginInterface`, `getDestination(string $id): DestinationPluginInterface`. Missing id → `\OutOfBoundsException` with a clear message.
   - Mark immutable after `boot()`; post-boot mutations throw `\LogicException` (programmer error).
3. Define `ReservedPluginIds`: six public-const strings (`PASS_THROUGH`, `HTML_SANITIZE`, `LOOKUP`, `CONCAT`, `TYPE_COERCE`, `DEFAULT_VALUE`) + an `ALL` array constant.
4. Define `Channels::MIGRATION_DEPRECATION`.

**Validation**:
- [ ] Unit test: registering two plugins with the same id raises `MigrationPluginCollisionException` with both FQCNs in the message.
- [ ] Unit test: a third-party provider registering `pass_through` raises the reserved-id variant.
- [ ] Unit test: an experimental plugin emits once on `migration.deprecation` and not twice.
- [ ] Unit test: `boot()` is idempotent — calling it twice is a no-op (or raises `\LogicException`, document the choice; recommended: idempotent).

**Edge cases**:
- A provider that returns an empty iterable from `migrationPlugins()` is valid — the package is in scope but ships no plugins (e.g. a source-reader package that only defines `MigrationDefinition`s in WP02).
- A plugin instance returned for the wrong category (e.g. a `SourcePluginInterface` returned through `migrationPlugins()` but the implementing class hierarchy mismatches) must classify by `instanceof` checks at registration time, not by FQCN string matching.

### Subtask T006 — `MigrationPluginCollisionException`

**Purpose**: Ship the first of the eight typed exceptions on §5.8 stable surface.

**FRs covered**: FR-008 (collision typing), FR-045 (exception surface — partial; remaining exceptions land in WP02, WP04, WP06).

**Files**:
- `packages/migration/src/Exception/MigrationPluginCollisionException.php` (new, ~50 lines).

**Steps**:
1. Extends `\RuntimeException`. Carries `public readonly string $pluginId`, `public readonly string $firstFqcn`, `public readonly string $secondFqcn`, `public readonly bool $reservedIdViolation = false`. Stable `public const CODE = 'MIGRATION_PLUGIN_COLLISION'`.
2. Message format: `"Plugin id '<id>' registered twice: first by <firstFqcn>, second by <secondFqcn>"`. When `reservedIdViolation` is true, append `" (reserved id; only Waaseyaa\\Migration\\* may register this id)"`.
3. `@api` annotation.

**Validation**:
- [ ] Unit test: assert all four properties round-trip.
- [ ] Assert `$e->getCode()` resolution behaves (since we use a string `CODE`, the integer constructor `$code` stays at 0; document in PHPDoc).

**Edge cases**:
- When the same plugin instance is registered twice from the same provider (rare; would indicate a provider bug), still raise — the second registration is what the registry sees.

### Subtask T007 — Unit tests for plugin contracts + registry

**Purpose**: Cover the interfaces, DTOs, and registry with PHPUnit 10.5 unit tests. Establish the testing pattern WP02–WP09 will follow.

**FRs covered**: FR-001..FR-010 (test coverage).

**Files**:
- `packages/migration/tests/Unit/Plugin/SourceRecordTest.php` (new).
- `packages/migration/tests/Unit/Plugin/ProcessContextTest.php` (new).
- `packages/migration/tests/Unit/Plugin/WriteResultTest.php` (new).
- `packages/migration/tests/Unit/Plugin/DestinationRecordTest.php` (new).
- `packages/migration/tests/Unit/Discovery/PluginRegistryTest.php` (new, ~250 lines).
- `packages/migration/tests/Unit/Exception/MigrationPluginCollisionExceptionTest.php` (new).
- `packages/migration/tests/Unit/Plugin/ReservedPluginIdsTest.php` (new).
- `packages/migration/phpunit.xml` (new) — Unit + Integration suites, autoload-dev via composer.

**Steps**:
1. For every DTO, assert constructor validation (invalid arguments raise `\InvalidArgumentException`) and readonly behavior (re-assignment is a fatal error — verified via PHPStan, not PHPUnit).
2. `PluginRegistryTest`: cover collision, reserved-id collision, experimental-plugin deprecation single-fire, third-party-vs-framework FQCN namespacing, post-boot immutability.
3. Tests use anonymous classes implementing the three plugin interfaces — no need for fixture files. Pattern matches CLAUDE.md guidance on intersection-type tests.
4. `phpunit.xml` declares Unit + Integration test suites + `<source>` block listing `src/` for coverage. Use the convention from `packages/entity-storage/phpunit.xml` as the template.

**Validation**:
- [ ] `./vendor/bin/phpunit packages/migration/tests/Unit/` green.
- [ ] `./vendor/bin/phpunit` (whole suite) green — verifies no incidental breakage elsewhere (M-006 reviewer lesson: per-package suite is not sufficient).
- [ ] Coverage of `PluginRegistry` ≥ 90% line.

**Edge cases**:
- Tests must not depend on real provider discovery — pass providers explicitly into `PluginRegistry`'s constructor for unit isolation. The real `PackageManifestCompiler` integration is exercised by WP02's tests.

## Tests

- **Unit**: as listed in T007. All under `packages/migration/tests/Unit/`.
- **Integration**: none in this WP. WP02/WP05 introduce integration tests once their dependencies exist.
- **Conformance**: not yet — WP10 ships the conformance suite.

## Definition of Done

- [ ] All seven subtasks (T001–T007) complete.
- [ ] All ten FRs (FR-001..FR-010) cited in code comments or PHPDoc as `@spec FR-xxx`.
- [ ] `composer phpstan` clean for `packages/migration/`.
- [ ] `composer cs-check` clean for changed files (run twice — feedback_cs_fix_two_passes.md).
- [ ] `bin/check-composer-policy` clean.
- [ ] `bin/check-package-layers` clean (Layer 3 imports Layer 0/1 only).
- [ ] `bin/audit-dead-code` reports no new findings (intentional scaffolding marked `@api`).
- [ ] `./vendor/bin/phpunit` passes the full suite — not just the new tests.
- [ ] All new public symbols carry `@api` PHPDoc.
- [ ] No `psr/log` imports anywhere in `packages/migration/`. `Waaseyaa\Foundation\Log\LoggerInterface` only.
- [ ] No service locators; no class-string registries (CLAUDE.md `feedback_modern_php_rules.md`).
- [ ] `composer dump-autoload --optimize` clean — no missing-class warnings from `PackageManifestCompiler`.

## Risks

- **R1 — `SourceId` stub leaks**: WP04 must replace the stub. If WP04 lands before WP01's stub is removed, the codepath ends up with two `SourceId` declarations. Mitigation: WP04's first subtask is the literal file replacement.
- **R2 — Root `composer.json` merge conflict with concurrent WPs**: only WP01 touches the root manifest. No other WP in this mission edits it. Mitigation: this WP must merge before any sibling.
- **R3 — Layer drift**: a process plugin in WP03 might inadvertently import Layer 2/3 packages. Mitigation: WP01's `bin/check-package-layers` baseline is the canonical layering check for the package going forward.
- **R4 — Reserved-id list drift**: the six reserved ids must match WP03's six concrete plugins exactly. Mitigation: WP03's first DoD bullet asserts equality with `ReservedPluginIds::ALL`.

## Reviewer guidance

- Check: every new public class/interface carries `@api`. Use `rg '^final readonly class|^interface' packages/migration/src/Plugin/ packages/migration/src/Discovery/` and pair against PHPDoc.
- Check: no `Illuminate\*` or `psr/log` imports. `rg 'use (Illuminate|Psr\\\\Log)' packages/migration/` must return empty.
- Check: the `SourceId` stub explicitly throws on `hash()` so accidental early callers fail loudly. Confirm the throw message names WP04.
- Verify: root `composer.json` diff is exactly two lines added (one to `repositories`, one to `require`); no other key reorder.
- Verify: `extra.waaseyaa.providers` lists exactly one entry (the `ServiceProvider`).
- Verify: `composer dump-autoload --optimize` after merge succeeds locally and in CI.
- Confirm: CLAUDE.md gotcha "Never put classes that extend dev-only deps under autoload" is honoured — the `testing/` namespace is wired into `autoload-dev` only, even though `testing/` is empty in this WP (WP10 populates it).

## Activity Log

- 2026-05-13T02:56:07Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=635463 – Assigned agent via action command
- 2026-05-13T03:12:08Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=635463 – Ready for review: all 7 subtasks complete; cs-check + phpstan + check-composer-policy + check-package-layers + full phpunit (7894 tests, 0 failures, 0 errors) all clean
- 2026-05-13T03:12:51Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=640344 – Started review via action command
