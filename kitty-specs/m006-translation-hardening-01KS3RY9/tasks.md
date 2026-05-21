# M-006 Translation Hardening — Tasks

**Mission**: `m006-translation-hardening-01KS3RY9`
**Branch**: `main` → `main`
**Generated**: 2026-05-20T23:57:09Z
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

## Summary

Four work packages close the post-M-006 audit items before any consumer flips
`translatable: true`. Recommended execution order: WP03 → WP02 → WP01 → WP04
(safest: contract gap first, CLI hardening second, access gate last to allow
M-B WP02 to merge first if running in parallel).

---

## Subtask Index

| ID   | Description                                                      | WP   | Parallel |
|------|------------------------------------------------------------------|------|----------|
| T001 | Add `fieldLangcode(string $fieldName): ?string` to `TranslatableInterface` | WP03 | — | [D] |
| T002 | Contract test: reflection asserts method + signature; trait satisfies via anonymous class | WP03 | [D] |
| T003 | Create `packages/entity/src/LangcodeValidator.php` with `BCP47_PATTERN` const + `validate()` | WP02 | — |
| T004 | Unit test `LangcodeValidatorTest`: acceptance + rejection cases | WP02 | [P] |
| T005 | Wire `LangcodeValidator::validate()` into `AddTranslationsMigrationGenerator` (both render paths + `generate()`) | WP02 | — |
| T006 | Audit `$backend` interpolation in generator; document PHP type narrowing; add assertion per FR-007 | WP02 | [P] |
| T007 | Unit test `AddTranslationsMigrationGeneratorTest`: injection payload rejection + valid langcode acceptance | WP02 | [P] |
| T008 | Add `EntityAccessHandler` constructor parameter to `TranslationController` | WP01 | — |
| T009 | Insert per-method `check()` calls with FR-002 ability mapping (index/show→view, store→create, update→update, destroy→delete) | WP01 | — |
| T010 | Implement 403 response shape: JSON:API error document with `status: "403"`, `code: "FORBIDDEN"`, stable title | WP01 | — |
| T011 | Unit test `TranslationControllerTest`: happy path + 403 for each of 5 endpoints | WP01 | [P] |
| T012 | Integration test `TranslationAccessControlTest` in `tests/Integration/Phase29/` | WP01 | [P] |
| T013 | Update `docs/specs/entity-storage-two-axis.md`: access-gate convention + BCP-47 regex constant reference | WP04 | — |
| T014 | Add `CHANGELOG.md` `[Unreleased]` entry covering #1445, #1446, #1447 | WP04 | [P] |
| T015 | Run `composer verify` and confirm green; document result in WP04 commit | WP04 | — |

---

## Work Packages

### WP03 — TranslatableInterface contract

**Goal**: Declare `fieldLangcode(string $fieldName): ?string` on `TranslatableInterface`; prove via contract test that the interface enforces it and `TranslatableEntityTrait` satisfies it. Closes #1447.
**Priority**: HIGH (implement first — no dependencies, unblocks nothing but closes a compile-time safety gap)
**Estimated prompt size**: ~180 lines
**Dependencies**: none

#### Subtasks

- [x] T001 Add `fieldLangcode(string $fieldName): ?string` to `TranslatableInterface` (WP03)
- [x] T002 Contract test: reflection asserts method + signature; trait satisfies via anonymous class (WP03)

#### Implementation sketch

1. Edit `packages/entity/src/TranslatableInterface.php` — add PHPDoc'd method declaration after `getTranslationLanguages()`.
2. Create `packages/entity/tests/Unit/TranslatableInterfaceContractTest.php` — use `ReflectionClass` to assert the method is declared on the interface with correct parameter and return type; construct an anonymous class that uses `TranslatableEntityTrait` and asserts `instanceof TranslatableInterface` passes (PHP's own type system enforces trait satisfaction).

#### Risks

- Existing anonymous-class / non-trait implementations of `TranslatableInterface` will get a compile-time failure — **this is the desired outcome** (the spec's goal is to surface them at autoload, not runtime). No framework classes do this today.

---

### WP02 — CLI regex validation + escaping audit

**Goal**: Harden `AddTranslationsMigrationGenerator` against code injection: define a canonical BCP-47 regex constant, validate operator-supplied langcodes before any file is written, and audit all interpolation sites for `phpStringLiteral()` coverage. Closes #1446.
**Priority**: MEDIUM
**Estimated prompt size**: ~360 lines
**Dependencies**: none (can go before or after WP03; no M-B dependency)

#### Subtasks

- [ ] T003 Create `packages/entity/src/LangcodeValidator.php` with `BCP47_PATTERN` const + `validate()` method (WP02)
- [ ] T004 Unit test `LangcodeValidatorTest`: acceptance and rejection cases (WP02)
- [ ] T005 Wire `LangcodeValidator::validate()` into generator `render()`, `renderTwoAxisFromRevisionable()`, and `generate()` (WP02)
- [ ] T006 Audit `$backend` interpolation; document PHP type narrowing; add assertion per FR-007 defense-in-depth (WP02)
- [ ] T007 Unit test `AddTranslationsMigrationGeneratorTest`: injection payload rejection + acceptance (WP02)

#### Implementation sketch

1. New class `LangcodeValidator` in `packages/entity/src/` — `public const string BCP47_PATTERN`, `public static function validate(string $langcode): void` (throws `\InvalidArgumentException`).
2. Validate `$defaultLangcode` in `generate()` and `render()` before the heredoc; validate each entry in `$addTranslations` (comma-split) in `generate()`.
3. Audit: `$backend` is PHP-typed `'sql-blob'|'sql-column'` — document this narrows the injection surface; add a runtime `\assert(in_array($backend, ['sql-blob', 'sql-column'], true))` for defense in depth.
4. Unit tests for the validator class; unit tests for the generator validating that an injection payload throws before any render logic runs.

#### Parallel opportunities

T004, T006, T007 can be written in parallel with T003/T005 (different files).

#### Risks

- `renderTwoAxisFromRevisionable()` also interpolates `$defaultLangcode` — must be covered in T005 or the guard only covers the simple path.

---

### WP01 — TranslationController access gate

**Goal**: Wire `EntityAccessHandler` into `TranslationController` so every read and mutation is gated by the entity's access policy. Implement the 403 JSON:API response shape. Add unit + integration tests. Closes #1445 (HIGH severity).
**Priority**: HIGH (but implement after WP02/WP03; must not merge before M-B WP02 if running in parallel)
**Estimated prompt size**: ~430 lines
**Dependencies**: WP03 (TranslatableInterface — interface contract must be final before testing against it)

#### Subtasks

- [ ] T008 Add `EntityAccessHandler` constructor parameter to `TranslationController` (WP01)
- [ ] T009 Insert per-method `check()` calls with FR-002 ability mapping (WP01)
- [ ] T010 Implement 403 response shape with JSON:API error document (WP01)
- [ ] T011 Unit test `TranslationControllerTest`: happy path + 403 for each endpoint (WP01)
- [ ] T012 Integration test `TranslationAccessControlTest` in `tests/Integration/Phase29/` (WP01)

#### Implementation sketch

1. Add `private readonly EntityAccessHandler $accessHandler` constructor parameter (second after `$entityTypeManager`, before `$serializer`).
2. Add `private function checkAccess(EntityInterface $entity, string $operation, Request $request): ?JsonApiDocument` helper — reads `_account` from `$request->attributes->get('_account')`, calls `$this->accessHandler->check($entity, $operation, $account)`, returns 403 document if `!$result->isAllowed()`.
3. Call `checkAccess()` in each public method after `loadTranslatableEntity()` returns, using FR-002 mapping.
4. Unit test: mock `EntityAccessHandler` to return `AccessResult::allowed()` / `AccessResult::forbidden()`; assert status codes.
5. Integration test: boots kernel with in-memory SQLite, registers translatable entity type with a deny-update policy for `viewer-only` account; exercises PATCH and anonymous GET; asserts 403 + entity unmodified.

#### CRITICAL dependency note

WP01 must not merge before M-B (`access-fail-closed-completeness-01KS3RJT`) WP02 lands. If M-B is not in flight, this constraint does not apply. See plan.md §Assumptions.

#### Parallel opportunities

T011, T012 can be written in parallel once T008–T010 are done (different files).

#### Risks

- `_account` vs `account` request attribute — use `$request->attributes->get('_account')` exclusively (CLAUDE.md gotcha).
- `TranslationController` has `@api` docblock — adding a constructor parameter is not a dead-code-detector issue, but review the `@api` annotation to confirm it covers the controller class (it does — `@api` is on the class level).

---

### WP04 — Wrap-up and spec update

**Goal**: Document the access-gate convention and BCP-47 regex in the canonical two-axis spec, write the CHANGELOG entry, and confirm `composer verify` is green on the merge commit. Closes SC-007.
**Priority**: LOW (depends on WP01 + WP02 + WP03 all merged and green)
**Estimated prompt size**: ~140 lines
**Dependencies**: WP01, WP02, WP03

#### Subtasks

- [ ] T013 Update `docs/specs/entity-storage-two-axis.md` with access-gate convention and BCP-47 regex constant reference (WP04)
- [ ] T014 Add `CHANGELOG.md` `[Unreleased]` entry covering #1445, #1446, #1447 (WP04)
- [ ] T015 Run `composer verify` green; document result in commit message (WP04)

#### Implementation sketch

1. Edit `docs/specs/entity-storage-two-axis.md` — add §"Translation access-gate convention" section after the existing schema section: document FR-002 per-method ability mapping, note the `EntityAccessHandler` injection pattern, and cross-reference `LangcodeValidator::BCP47_PATTERN` (FQCN + constant name).
2. Add `CHANGELOG.md` bullet under `[Unreleased]`: `- fix(api,entity,cli): translation access gate, BCP-47 regex validation, TranslatableInterface contract (Closes #1445, #1446, #1447)`.
3. Run `composer verify` to confirm all checks pass; include the green status in the commit message.

#### Risks

- If WP01 PR is blocked on M-B, WP04 must wait — this is a sequencing concern for the sprint planner, not a code risk.

---

## Execution Order

```
WP03 → WP02 → WP01 → WP04
```

WP03 and WP02 have no inter-dependencies and can be reviewed/merged in either order. WP01 depends on WP03 (interface final) and on M-B WP02 (external). WP04 depends on all three.

## Success Criteria

| SC | Verified by |
|----|-------------|
| SC-001 PATCH denied → 403, entity unmodified | FR-013 integration test |
| SC-002 Anonymous GET denied → 403 | FR-013 integration test |
| SC-003 Injection payload rejected, no file written | FR-011 unit test |
| SC-004 Interface declares fieldLangcode(), trait satisfies | FR-012 contract test |
| SC-005 `composer verify` green | CI |
| SC-006 Issues #1445, #1446, #1447 closed | GitHub auto-close on merge |
| SC-007 Two-axis spec updated | Spec diff in WP04 PR |
