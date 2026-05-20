# Implementation Plan: M-006 Translation Hardening

**Branch**: `main` | **Date**: 2026-05-20 | **Spec**: [spec.md](spec.md)
**Input**: `kitty-specs/m006-translation-hardening-01KS3RY9/spec.md`

**Branch contract (confirmed twice per skill rule):**
- Current branch at plan start: `main`
- Planning/base branch: `main`
- Final merge target: `main`

## Summary

This mission hardens the translation substrate shipped in M-006 before any consumer
flips `translatable: true`. Three targeted edits close a HIGH-severity access-gate
absence on `TranslationController` (#1445), a MEDIUM-severity CLI code-injection
risk in `AddTranslationsMigrationGenerator` (#1446), and a MEDIUM-severity
`TranslatableInterface` contract gap (#1447). No new public framework concepts are
introduced (C-002). Schema and storage are untouched (C-001).

## Technical Context

**Language/Version**: PHP 8.5+, `declare(strict_types=1)` everywhere
**Primary Dependencies**: `waaseyaa/entity` (L1), `waaseyaa/access` (L1), `waaseyaa/api` (L4), `waaseyaa/cli` (L6), PHPUnit 10.5
**Storage**: No changes to storage or schema
**Testing**: PHPUnit 10.5 unit + integration (in-memory SQLite via `DBALDatabase::createSqlite()`); `#[Test]`, `#[CoversClass]` attributes
**Target Platform**: PHP 8.5+ / Linux server (same as rest of framework)
**Performance Goals**: Access-check call adds ≤2% p95 overhead (NFR-001)
**Constraints**: No upward layer imports; `composer verify` green on merge commit (C-005); layer L4→L1 import is allowed downward edge

## Charter Check

Charter loaded in compact mode. Governance: software-dev-default, DDD paradigm.
Directives DIR-001, DIR-002, DIR-003 in scope.

- **DIR-001 (test coverage)**: Satisfied — FR-010..FR-013 mandate four test files.
- **DIR-002 (no breaking public API changes)**: Satisfied — `TranslatableInterface` gains a method already provided by `TranslatableEntityTrait`; no existing implementors break. `TranslationController` constructor gains a new parameter (internal L4 class, not a public-API extension point). C-005 preserved.
- **DIR-003 (layer discipline)**: Satisfied — all imports follow the L0→L6 graph; `LangcodeValidator` placed at L1 (`packages/entity/`); CLI (L6) imports entity (L1) which is already in `cli/composer.json`.

No violations. Charter Check: **PASS**.

## Assumptions

1. **M-B sequencing (CRITICAL cross-mission dependency):** M-B (mission `access-fail-closed-completeness-01KS3RJT`) WP02 replaces the `AccessPolicyRegistry` with a container-resolved variant. `EntityAccessHandler::check()` calls into the registry. If M-B and M-C go to implementation in parallel, **M-C WP01 must not merge before M-B WP02 merges** — otherwise `TranslationController`'s new `EntityAccessHandler` injection will exercise the pre-fix registry that silently skips policies with injected dependencies. Safe paths: (a) implement M-C only after M-B ships, or (b) implement M-C WP01 last in any parallel sprint and rebase on M-B's merge commit before merging WP01. This assumption is documented here so the implementer or sprint planner can enforce it.

2. `phpStringLiteral()` already exists in `AddTranslationsMigrationGenerator` and correctly escapes string values. WP02 adds regex pre-validation and audits the `$backend` bare-string interpolation (line 88: `private const BACKEND = '{$backend}'`) as an additional injection surface not covered by the spec's numbered lines but present in the code.

3. The `$backend` value is constrained to `'sql-blob'|'sql-column'` by the PHP type system on the `render()` method signature, making it practically safe — but FR-007's "defense in depth" principle requires the audit to document this and optionally add an assertion.

4. Integration test goes in `tests/Integration/Phase29/` (next in the existing numeric sequence after Phase28; M-006 substrate tests established the Phase27/28 range).

5. The access-gate convention (FR-002 per-method ability mapping) and BCP-47 regex constant reference are documented in `docs/specs/entity-storage-two-axis.md` (not the M-006 spec) — this spec is the canonical home for the translation storage contract. WP04 updates that file.

6. `TranslationController` is not a public extension point (no `@api` docblock), so adding a constructor parameter is not a breaking-API change for the dead-code detector.

## Regex Constant Location Decision

**Decision**: `packages/entity/src/LangcodeValidator.php` (namespace `Waaseyaa\Entity`)

**Rationale**:
- `TranslatableInterface` and `TranslatableEntityTrait` already live in `packages/entity/src/`. The langcode is an entity-level concept (entity key `default_langcode`, `activeLangcode()`, `defaultLangcode()` on the interface).
- `packages/i18n/` is L0 Foundation and owns UI-string translation (`Translator`, `Language`, `LanguageManager`). Its `Language::$id` is a free-form string with no regex validation. Placing a BCP-47 validator there would introduce entity-level concerns into a Foundation package.
- `packages/cli/` already imports `waaseyaa/entity` (`cli/composer.json` line 70). Adding a `use Waaseyaa\Entity\LangcodeValidator` in the CLI generator requires no new dependency.
- Layer discipline: L1 entity ← L6 CLI is a valid downward import.

**BCP-47-tolerant regex** (language + optional script + optional region subtags; variants/private-use out of scope for v1):
```
/^[a-zA-Z]{2,8}(-[a-zA-Z]{4})?(-[a-zA-Z]{2}|\d{3})?$/
```
Accepts: `en`, `en-US`, `zh-Hant`, `zh-Hant-TW`, `en-CA`, `fr-CA`
Rejects: injection payloads, empty string, control characters, whitespace, variant subtags

The constant is `LangcodeValidator::BCP47_PATTERN` (a `public const string`).

## Project Structure

### Documentation (this feature)

```
kitty-specs/m006-translation-hardening-01KS3RY9/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
└── tasks.md             ← Phase 2 output (/spec-kitty.tasks — NOT created by /spec-kitty.plan)
```

### Source Code (repository root)

```
packages/entity/src/
└── LangcodeValidator.php          [NEW] BCP47_PATTERN const + validate() method

packages/entity/tests/Unit/
└── LangcodeValidatorTest.php      [NEW] acceptance + rejection cases (FR-011 partial)

packages/api/src/Controller/
└── TranslationController.php      [EDIT] add EntityAccessHandler param; per-method check(); 403 shape

packages/api/tests/Unit/
└── TranslationControllerTest.php  [NEW or EDIT] FR-010 happy+forbidden for each of 5 endpoints

packages/cli/src/Handler/
└── AddTranslationsMigrationGenerator.php  [EDIT] validate langcodes; audit $backend interpolation

packages/cli/tests/Unit/
└── AddTranslationsMigrationGeneratorTest.php  [NEW or EDIT] FR-011 regex acceptance + rejection

packages/entity/src/
└── TranslatableInterface.php      [EDIT] add fieldLangcode(string $fieldName): ?string declaration

packages/entity/tests/Unit/
└── TranslatableInterfaceContractTest.php  [NEW] FR-012 reflection + trait satisfaction

tests/Integration/Phase29/
└── TranslationAccessControlTest.php  [NEW] FR-013 kernel boot + 403 + unmodified entity checks

docs/specs/
└── entity-storage-two-axis.md     [EDIT] access-gate convention + BCP-47 regex constant reference

CHANGELOG.md                       [EDIT] [Unreleased] entry
```

## Work Package Outline

### WP01 — TranslationController access gate
**Closes**: #1445 (HIGH)
**Files**:
- `packages/api/src/Controller/TranslationController.php` — add `EntityAccessHandler` constructor parameter; insert `check()` call at the top of each public method before entity load; 403 response shape with JSON:API error document (`status: "403"`, `code: "FORBIDDEN"`). Account read via `$request->attributes->get('_account')` (CLAUDE.md gotcha). Methods: `index`/`show` → `view`; `store` → `create`; `update` → `update`; `destroy` → `delete`.
- `packages/api/tests/Unit/TranslationControllerTest.php` — FR-010: per-method happy path + 403 path.
- `tests/Integration/Phase29/TranslationAccessControlTest.php` — FR-013: boots kernel, registers translatable entity type with deny-update policy, exercises `PATCH /api/{type}/{id}/translations/{lc}` as that user, asserts 403 + entity unmodified. Also covers SC-002 (anonymous `view` denied).

**Dependency note**: Must not merge before M-B WP02 (container-resolved registry). See Assumptions §1.

### WP02 — CLI regex validation + escaping audit
**Closes**: #1446 (MEDIUM)
**Files**:
- `packages/entity/src/LangcodeValidator.php` — new class with `BCP47_PATTERN` const and `validate(string $langcode): void` (throws `\InvalidArgumentException` on failure).
- `packages/cli/src/Handler/AddTranslationsMigrationGenerator.php` — call `LangcodeValidator::validate($defaultLangcode)` and validate each entry in `$addTranslations` before rendering; audit `$backend` interpolation (document that PHP type narrows to `'sql-blob'|'sql-column'`; add assertion for defense in depth per FR-007). Ensure all operator-provided strings pass through `phpStringLiteral()`.
- `packages/cli/tests/Unit/AddTranslationsMigrationGeneratorTest.php` — FR-011: acceptance (`en`, `en-US`, `zh-Hant`, `fr-CA`) and rejection (injection payload, empty string, control chars, whitespace).
- `packages/entity/tests/Unit/LangcodeValidatorTest.php` — unit tests for the validator class directly.

**No M-B dependency** — can be implemented in any order relative to WP01.

### WP03 — TranslatableInterface contract
**Closes**: #1447 (MEDIUM)
**Files**:
- `packages/entity/src/TranslatableInterface.php` — add `public function fieldLangcode(string $fieldName): ?string;` declaration with PHPDoc.
- `packages/entity/tests/Unit/TranslatableInterfaceContractTest.php` — FR-012: reflection asserts method declared on interface with expected signature; `TranslatableEntityTrait` satisfies via anonymous class; PHP autoloader emits no warnings.

**No M-B dependency** — fully self-contained; safest WP to implement first.

### WP04 — Wrap-up and spec update
**Files**:
- `docs/specs/entity-storage-two-axis.md` — add §"Translation access-gate convention" documenting FR-002 per-method mapping and reference to `LangcodeValidator::BCP47_PATTERN`; SC-007.
- `CHANGELOG.md` — `[Unreleased]` entry covering all three closed issues.
- Full `composer verify` green confirmation.

**Dependency**: All of WP01, WP02, WP03 must be complete and passing before WP04 merges.

## Implementation Sequence

**Recommended sequential order** (safest, no parallel M-B risk):
1. WP03 (TranslatableInterface — no dependencies, safest, unblocks nothing)
2. WP02 (CLI validator — no M-B dependency, self-contained)
3. WP01 (access gate — wait for M-B WP02 if parallel mission; otherwise implement here)
4. WP04 (wrap-up — depends on WP01+WP02+WP03 all green)

**If M-B is not in parallel**: no sequencing constraint; WP01 can go before WP02.
**If M-B is in parallel**: implement WP01 last, rebase its PR on M-B's merge commit before landing.

## Gates

| Gate | Status | Notes |
|------|--------|-------|
| Charter Check | PASS | No violations |
| Layer discipline | PASS | L4→L1 (TranslationController→EntityAccessHandler), L6→L1 (CLI→LangcodeValidator), both valid downward edges |
| No new public concepts | PASS | One new internal class, one const, one new interface method, one new constructor param |
| composer verify green | Required on merge | C-005 |
| Closes #1445, #1446, #1447 | Required on merge | C-003 |

---

**Branch contract (repeated per skill rule):**
- Planning/base branch: `main`
- Final merge target: `main`

**Next step**: Run `/spec-kitty.tasks` to generate work package files.
