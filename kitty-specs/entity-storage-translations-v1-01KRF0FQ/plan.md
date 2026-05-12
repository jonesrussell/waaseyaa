# Implementation Plan: Entity Storage — Single-Axis Translations v1

**Branch:** `main` (planning-and-merge target)
**Date:** 2026-05-12
**Spec:** [`spec.md`](spec.md)
**Doctrine spec:** [`docs/specs/entity-storage-translations-v1.md`](../../docs/specs/entity-storage-translations-v1.md)
**Mission ID:** M-006 (display) / `01KRF0FQ0AA42F434JNAA56WFB` (Spec Kitty)
**Slug:** `entity-storage-translations-v1-01KRF0FQ`

**Branch contract** (deterministic, from `spec-kitty agent mission setup-plan --json`):
- `current_branch`: `main`
- `planning_base_branch`: `main`
- `merge_target_branch`: `main`
- `branch_matches_target`: `true` ✓

## Summary

Ship the framework substrate for single-axis per-field translation on entity types. M-001 left `EntityType::isTranslatable(): bool` as a tombstone flag with no implementation — this mission gives it real semantics: `TranslatableInterface` (expand the existing minimal stub), `FieldDefinition::translatable()` builder, per-langcode storage on both `sql-blob` and `sql-column` backends, configurable fallback chain, lifecycle events with langcode, `translate` access-policy operation, schema-migration generator extension, contract test suite, and a framework-internal fixture entity type. Beta-gate per stability-charter §3.2 criterion 9. Unblocks one of two M-004 prerequisites.

## Technical Context

| Field | Value |
|---|---|
| **Language / Version** | PHP 8.5+ (project minimum; `declare(strict_types=1)` mandatory) |
| **Primary Dependencies** | Symfony 7.x (Console, EventDispatcher, Routing, Validator, Uid, Yaml, Messenger), Doctrine DBAL 4.x, `waaseyaa/foundation` (LoggerInterface, SaveContext value-object pattern), existing `waaseyaa/i18n` (LanguageManager) |
| **Storage** | SQLite (dev / CI), MySQL/MariaDB & PostgreSQL (prod). DBAL abstracts the translation table CREATE / ALTER. |
| **Testing** | PHPUnit 10.5 (do NOT use `-v` flag; rejected at this version). Framework packages use PHPUnit, not Pest. Namespaces: `Waaseyaa\PackageName\Tests\Unit\…`, `Waaseyaa\Tests\Integration\PhaseN\…`. `#[Test]`, `#[CoversClass]`, `#[CoversNothing]` (for contract tests). |
| **Target Platform** | PHP CLI + PHP-FPM under Caddy in production; PHP built-in server in dev. Linux x86_64 / WSL2. |
| **Project Type** | Monorepo PHP framework (62 packages, 7 layers). Touched layers: L1 (entity, entity-storage, field, access), L0 (i18n consumer wire-up). Layer discipline enforced by `bin/check-package-layers`. |
| **Performance Goals** | NFR-001 p95 load latency delta ≤ 0% on non-translatable types vs. pre-mission baseline. NFR-004 contract suite executes both backends in < 10s wall on CI. NFR-005 `findTranslations()` single query, asserted via query-count. |
| **Constraints** | C-001 no revisions composition (M-004). C-002 no listing pipeline (ADR 015). C-003 backwards compatibility for `translatable: false` types. C-004 i18n is optional DI, not hard dep. C-005 no machine translation / BCP-47 validation. C-006 no consumer flips `translatable: true` before mission ships. |
| **Scale / Scope** | 64 FRs, 5 NFRs, 6 Cs, 14 WPs. Spans `packages/entity/`, `packages/entity-storage/`, `packages/field/`, `packages/access/`, `packages/cli/`, `packages/i18n/` (read-only consumer). |

## Charter Check

| Charter section | Gate | Status | Notes |
|---|---|---|---|
| **Testing Standards** | Contract + integration tests for new public surface. | PASS | Spec §3.12 lists 12 contract tests T01–T12 + 7 integration tests I01–I07. |
| **Quality Gates** | `composer phpstan` level 5, `composer cs-check`, `bin/check-package-layers`, `bin/check-composer-policy` green. | PASS | All changes confined to L1 + L0. No new packages; no new internal `waaseyaa/*` deps. |
| **Performance Benchmarks** | NFR thresholds quantified. | PASS | NFR-001..NFR-005 each have a measurable threshold. |
| **Branch Strategy** | Plan/base/merge explicit and matched. | PASS | main → main → main. `branch_matches_target = true`. |
| **DIR-001 / DIR-002 / DIR-003** | Project directives. | PASS | No mission-specific override needed. |
| **Paradigm: domain-driven-design** | Entity / value-object / repository discipline. | PASS | New surface lives on entities (`TranslatableInterface` on `ContentEntityBase`), value objects (`SaveContext::withLangcode`, `EntityTranslationException`), repository (`findTranslations`). |

**Re-evaluation post-Phase-1**: All gates re-checked after `data-model.md` and `contracts/` generation. PASS unchanged.

## Project Structure

### Mission documentation

```
kitty-specs/entity-storage-translations-v1-01KRF0FQ/
├── spec.md                          # 515 lines, committed at 327dd10ad
├── plan.md                          # this file
├── research.md                      # Phase 0 — naming reconciliation, readonly-builder pattern
├── data-model.md                    # Phase 1 — entity / storage / event shapes
├── quickstart.md                    # Phase 1 — cookbook-style developer scenario
├── contracts/                       # Phase 1 — stable-surface signatures
│   ├── TranslatableInterface.md
│   ├── EntityRepository.findTranslations.md
│   ├── lifecycle-events.md
│   └── migration-generator.md
├── checklists/requirements.md       # specify-phase validation, 21/21 green
├── meta.json
└── status.events.jsonl
```

### Source paths touched

```
packages/entity/
├── src/
│   ├── TranslatableInterface.php           # WP01 — expand existing stub
│   ├── EntityType.php                      # WP02 — boot validation
│   ├── ContentEntityBase.php               # WP01 — implement TranslatableInterface
│   ├── Exception/
│   │   ├── EntityTranslationException.php  # WP01 — NEW
│   │   └── InvalidEntityTypeException.php  # WP02 — extend
│   └── Event/
│       ├── EntityEvent.php                 # WP08 — add ?string $langcode
│       └── TranslationEvent.php            # WP08 — NEW (extends EntityEvent)
└── testing/                                # autoload-dev only
    └── TranslatableEntityContractTest.php  # WP13 — NEW

packages/field/
└── src/
    ├── FieldDefinition.php                 # WP03 — translatable(bool): self builder
    └── Exception/
        └── InvalidFieldDefinitionException.php  # WP03 — extend

packages/entity-storage/
├── src/
│   ├── SaveContext.php                     # WP07 — add withLangcode
│   ├── EntityStorageCoordinator.php        # WP07 — write-path langcode honoring
│   ├── EntityRepository.php                # WP10 — findTranslations() helper
│   ├── EntitySchemaSync.php                # WP04, WP05 — translation table sync
│   ├── Backend/                            # WP04, WP05 — backend translation read/write
│   └── Hydration/                          # WP06 — fallback hydrator + fieldLangcode tracking
└── tests/Fixtures/
    └── TestTranslatableEntity.php          # WP13 — NEW fixture

packages/access/
└── src/
    └── Gate/AccessChecker.php              # WP09 — translate op recognition

packages/cli/
└── src/
    └── Command/MakeMigrationCommand.php    # WP11 — --add-translations flag

config/waaseyaa.php (skeleton)              # WP06, WP12 — translation config

docs/
├── specs/
│   ├── entity-storage-translations-v1.md   # committed at 327dd10ad
│   ├── public-surface-map.md               # WP14 — stable-surface table update
│   └── stability-charter.md                # WP14 — §5.3 update
└── cookbook/
    └── translating-an-entity-type.md       # WP14 — NEW

CHANGELOG.md                                # WP14 — [Unreleased] bullet
```

## Phase 0: Research

See [`research.md`](research.md). Open questions resolved:

1. **TranslatableInterface naming reconciliation** — existing minimal stub at `packages/entity/src/TranslatableInterface.php` is unused (no implementations). Keep the existing class name (not `TranslatableEntityInterface` from spec FR-006); expand with the missing methods. Spec WP01 implementer task brief must include "update spec FR-006/FR-010/FR-014 references to canonical name `TranslatableInterface` and add `language(): string` as an alias for `activeLangcode()` for one minor cycle then deprecate."
2. **FieldDefinition readonly-builder pattern** — follow `storedIn()` and `indexed()` precedent: builder returns a new instance with the modified field set.
3. **EntityEvent extension shape** — append `?string $langcode` as a third constructor parameter with default `null`. Backward-compatible.
4. **Translation event class hierarchy** — single new class `TranslationEvent extends EntityEvent` with the langcode property promoted. Six new event-name constants live on `EntityEvents` (or equivalent registry); they share the same dispatched class.
5. **`SqlSchemaHandler` translation table sync** — extend the existing schema sync routine to allocate `<table>__translation` when `EntityType::translatable === true`. No new schema-sync architecture.
6. **`bin/check-package-layers` impact** — zero. All new surface is L1 + L0 consumer. No upward edges.
7. **Test fixture autoload pattern** — `TranslatableEntityContractTest` base class lives under `packages/entity/testing/` registered via `autoload-dev`, not `autoload` (production-install reflection scan gotcha; lesson from waaseyaa/graphql alpha.106 → alpha.107).

## Phase 1: Design

See:
- [`data-model.md`](data-model.md) — entity / storage / event domain shapes
- [`contracts/`](contracts/) — stable-surface contracts
- [`quickstart.md`](quickstart.md) — cookbook-style developer scenario

Re-evaluating Charter Check after Phase 1 design: PASS unchanged.

## Complexity tracking

| Item | Why it could be complex | Mitigation |
|---|---|---|
| `sql-blob` PK widening from `(entity_id)` to `(entity_id, langcode)` | Existing rows backfill required; FK relationships unchanged. | Comprehensive integration tests on `sql-blob`. Migration generator (WP11) handles backfill with required `--default-langcode`. Reverse migration emits data-loss warning. |
| `sql-column` JOIN coordination on hydration | Hydrator currently loads from a single primary table; translation requires a left-join to `<table>__translation`. | New hydrator path active ONLY when `EntityType::translatable === true`. Non-translatable types keep existing code path verbatim (NFR-001 invariant). |
| Fallback chain configurability | A callable in `config/waaseyaa.php` could carry side effects. | NFR-002 caps chain at 8 elements; chain function output is the only consumed value; no side-channel for state mutation. Default chain is deterministic. |
| Translation event proliferation | 6 new event names. | Single `TranslationEvent` class with name constants. Loop entered only when entity has translations to event over; single-translation path is zero overhead. |
| Coordinator write-semantics matrix | new × existing × default-langcode × non-default-langcode × delete = many cells. | `data-model.md` includes the decision table; coordinator behaviour normatively in spec §7.3. WP07 isolates this scope. |

## Progress tracking

| Phase | Status | Date |
|---|---|---|
| Specify | ✅ DONE | 2026-05-12 (commit `327dd10ad`) |
| Plan (this file) | 🔄 IN PROGRESS | 2026-05-12 |
| Tasks outline | ⏳ pending | — |
| Tasks packages | ⏳ pending | — |
| Tasks finalize | ⏳ pending | — |
| Implement-review loop | ⏳ pending | — |
| Merge | ⏳ pending | — |

## ⛔ Mandatory Stop

This command (`/spec-kitty.plan`) is COMPLETE after generating the planning artifacts above. The next commands are `/spec-kitty.tasks-outline` → `/spec-kitty.tasks-packages` → `/spec-kitty.tasks-finalize` → implement-review loop dispatch.
