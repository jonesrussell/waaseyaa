# M-006 Translation Hardening

**Mission:** `m006-translation-hardening-01KS3RY9`
**Status:** Spec
**Target branch:** `main`
**Predecessor:** M-006 (`entity-storage-translations-v1-01KRF0FQ`, squash `0f7e1809a`)
**Closes:** #1445 (HIGH), #1446 (MEDIUM), #1447 (MEDIUM)

## Why this mission exists

M-006 shipped the translation substrate end-to-end: schema, storage, controller, CLI generators. A post-merge audit flagged three follow-ups that **do not block the merge** but **must land before any consumer flips an entity type to `translatable: true`**. The HIGH-severity item is a missing access gate on `TranslationController`; the two MEDIUMs are a CLI code-injection risk and a `TranslatableInterface` contract gap.

The framework's "no vaporware" stance applies: when a consumer turns translatable on, every route, generator, and contract that touches translations must enforce the same access posture as the rest of the entity surface. Right now they don't. This mission closes that gap.

### Concrete state at mission start

1. **`packages/api/src/Controller/TranslationController.php`** has 5 mutation/read endpoints (`GET`, `POST`, `PATCH`, `DELETE`) and **no `EntityAccessHandler` injection** at all. The constructor takes only `EntityTypeManagerInterface` + `ResourceSerializer`. Any authenticated session can list, create, update, or delete translations on any translatable entity for which the route is reachable — no `view`/`update`/`delete` ability is enforced. This violates the framework invariant that every entity mutation passes through `EntityAccessHandler::check(...)`.

2. **`packages/cli/src/Handler/AddTranslationsMigrationGenerator.php`** interpolates `--default-langcode` and `--add-translations` argument values into generated PHP source at lines 75 (docblock), 77 (provenance), 86 (backend constant), 191 (audit). Only `phpStringLiteral()` is called on `$defaultLc` (line 60→83), `$table`, and `$translationsTable`. The other interpolation sites accept whatever string the operator typed — so `--default-langcode 'foo'); /* malicious code */` lands inside a generated migration file unfiltered. `MakeMigrationHandler.php:86` passes the raw operator string through.

3. **`packages/entity/src/TranslatableInterface.php`** does not declare `fieldLangcode(string $fieldName): ?string`. The method exists only on `TranslatableEntityTrait::fieldLangcode()` at line 264. FR-015 of M-006 specifies `fieldLangcode()` as part of the public translation contract. Consumers writing a non-trait implementation of `TranslatableInterface` get **no compile-time enforcement** of that contract — they can ship a class that satisfies the interface but lacks the method, and runtime calls then fatal.

## User scenarios

### Primary flow: an authenticated user attempts a translation mutation they are not authorized for

1. Consumer has a `Node` entity type with `translatable: true` and an `AccessPolicyInterface` that denies `update` to user `viewer-only`.
2. `viewer-only` issues `PATCH /api/node/42/translations/fr` with a valid translation payload.
3. `TranslationController::update()` resolves the entity, checks the `update` ability via `EntityAccessHandler::check($entity, 'update', $account)`, sees Forbidden.
4. Response: `403 Forbidden` with a JSON:API error document. The translation is **not** modified.
5. Audit log records the denied attempt with user, route, entity id, langcode (via the existing access-denial emit path; no new audit subsystem).

### Recovery flow: an authorized operator generates a translations migration without code injection

1. Operator runs `bin/waaseyaa migration:make --add-translations --default-langcode "en-CA;DROP TABLE users;"`.
2. The CLI validates `--default-langcode` against the canonical BCP-47-tolerant regex and **rejects** the value with a clear error before any file is written.
3. The operator retries with a valid value (`en-CA`) and the generator writes a migration whose interpolated values are guaranteed-safe.
4. The generated migration is the only artifact written; no stray PHP, comments, or SQL leaks into the codebase.

### Recovery flow: a consumer writes a non-trait `TranslatableInterface` implementation

1. Consumer implements `TranslatableInterface` directly on a custom entity class (not using `TranslatableEntityTrait`).
2. They forget to implement `fieldLangcode(string $fieldName): ?string`.
3. PHP itself fails the class definition (interface contract violation), surfacing immediately at autoload / dev-server boot, not at runtime when a caller invokes `fieldLangcode()`.

### Edge cases

- **Mixed operation semantics.** Reads (`index`, `show`) need `view`; writes (`store`, `update`, `destroy`) need `create`/`update`/`delete`. The mission settles on this per-method mapping (FR-002) and documents it in the M-006 spec or `docs/specs/entity-storage-two-axis.md` (planner picks the canonical home).
- **Anonymous account on `view`.** When `$account` is `AnonymousUser`, the same access pipeline applies — `EntityAccessHandler::check` already handles the anonymous case. Translation reads are not implicitly public; they pass through the policy.
- **Per-language access.** Out of scope. All translations of a given entity share the parent entity's ability check. Per-langcode policy is a follow-up if a consumer needs it.
- **CLI regex edge.** A langcode like `zh-Hant` (BCP-47 with script subtag) is **valid** and must be accepted. The mission settles on a broader BCP-47-tolerant regex (accepts language + script + region subtags). Variants and private-use are out of scope for v1.
- **Trait users unaffected.** Adding `fieldLangcode()` to the interface does not break consumers using `TranslatableEntityTrait` — the trait already provides the method (line 264). Only direct-interface implementors get a new compile-time requirement, and the framework ships none of those today.

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | `TranslationController` accepts an `EntityAccessHandler` in its constructor and calls `EntityAccessHandler::check($entity, $operation, $account)` before every read and mutation. `$operation` follows FR-002. `$account` is read from the request via `$request->attributes->get('_account')`. |
| FR-002 | Mandatory | Per-method ability mapping: `index` and `show` check `view`; `store` checks `create`; `update` checks `update`; `destroy` checks `delete`. The translation surface uses the **same ability namespace** as the parent entity's CRUD — no parallel `view_translation` set is introduced. |
| FR-003 | Mandatory | When access is denied (entity-level `isAllowed()` returns false per CLAUDE.md's asymmetric semantics), `TranslationController` returns `403 Forbidden` with a JSON:API error document carrying `status: "403"`, `code: "FORBIDDEN"`, and a stable error title. The response does not leak whether the entity exists. |
| FR-004 | Mandatory | The four routes registered for `TranslationController` carry route options consistent with other entity routes. The controller does not trust route-layer guards alone — it checks per-call (defense in depth, since the access gate **must** survive a route-option misconfiguration). |
| FR-005 | Mandatory | `AddTranslationsMigrationGenerator` validates `--default-langcode` against a canonical BCP-47-tolerant regex before any file is written. An invalid value causes the generator to throw a framework-standard validation exception with a clear message naming the offending argument. |
| FR-006 | Mandatory | `AddTranslationsMigrationGenerator` validates each entry in `--add-translations` (comma-separated langcodes) against the same regex. An invalid entry rejects the entire run; no partial output. |
| FR-007 | Mandatory | Every operator-provided string interpolated into generated PHP source — `$defaultLc`, `$table`, `$translationsTable`, items in `$addTranslations` — passes through `phpStringLiteral()` regardless of regex validation. Defense in depth: regex stops injection at the door, escaping closes the second-line gap. |
| FR-008 | Mandatory | `TranslatableInterface` declares `public function fieldLangcode(string $fieldName): ?string;`. |
| FR-009 | Mandatory | `TranslatableEntityTrait::fieldLangcode()` continues to satisfy the interface declaration without source changes. |
| FR-010 | Mandatory | Unit test: `TranslationControllerTest` covers per-method access checks — happy path (allowed) and 403 path (forbidden) for each of the 5 endpoints. |
| FR-011 | Mandatory | Unit test: `AddTranslationsMigrationGeneratorTest` covers regex acceptance (`en`, `en-US`, `zh-Hant`, `fr-CA`) and regex rejection (injection payload, empty string, control characters, leading/trailing whitespace). |
| FR-012 | Mandatory | Unit test: `TranslatableInterfaceContractTest` asserts (via PHPUnit + reflection) that `fieldLangcode(string): ?string` is declared on the interface, has the expected signature, and is satisfied by `TranslatableEntityTrait`. |
| FR-013 | Mandatory | Integration test under `tests/Integration/Phase??/TranslationAccessControlTest.php` boots the kernel, registers a translatable entity type with a policy that denies `update` to a specific user, exercises `PATCH /api/{type}/{id}/translations/{lc}` as that user, asserts 403, and asserts the entity is unmodified. |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | The access-check call in `TranslationController` adds ≤2% p95 overhead to existing translation endpoint latency, measured against existing M-006 tests or a new benchmark added in WP01. |
| NFR-002 | Mandatory | The BCP-47-tolerant regex documented in WP02 is a **single canonical string** referenced from a named constant (location TBD by planner — likely `packages/i18n/` or `packages/entity/`). Future code that needs the same validation imports the constant rather than re-deriving the pattern. |
| NFR-003 | Mandatory | Error responses from `TranslationController` conform to the JSON:API error-object shape already in use elsewhere (`code`, `title`, `status`, `meta` per `docs/specs/jsonapi.md`). |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | No changes to the M-006 schema, storage driver, or migration shape. This mission is a contract + access-gate hardening pass, not a schema refactor. |
| C-002 | Mandatory | No new public framework concepts. `TranslatableInterface` gains one method declaration; `TranslationController` gains one constructor parameter; one new regex constant. No new attribute classes, no new interfaces, no new abilities. |
| C-003 | Mandatory | The merge commit closes #1445, #1446, #1447 via `Closes #N` footer. |
| C-004 | Mandatory | The mission preserves the L0→L6 layer architecture. `TranslationController` (L4 API) accepts an `EntityAccessHandler` (L1 Core Data) by interface — already an allowed downward edge. The CLI generator (L6 Interfaces) calls into L0 validation utilities; no upward import. |
| C-005 | Mandatory | `composer verify` is green on the merge commit (cs-fix, phpstan, phpunit, check-dead-code, check-composer-policy, check-package-layers). |
| C-006 | Mandatory | No CI hooks bypassed during this mission's PRs. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | An authenticated user without `update` permission cannot mutate a translation. | Integration test `TranslationAccessControlTest::patchDeniedReturnsForbidden` passes (FR-013). |
| SC-002 | An anonymous user cannot read a translation when the entity policy denies `view` to anonymous. | Integration test `TranslationAccessControlTest::indexDeniedForAnonymousReturnsForbidden` passes. |
| SC-003 | A CLI invocation with an injection payload in `--default-langcode` is rejected with a clear error and no migration file is written. | `vendor/bin/phpunit --filter "AddTranslationsMigrationGeneratorTest::rejects.*"` passes (FR-011). |
| SC-004 | `TranslatableInterface` declares `fieldLangcode()` and `TranslatableEntityTrait` satisfies the declaration. | Contract test passes (FR-012); PHP autoloader emits no interface-contract warnings during `composer dump-autoload --optimize`. |
| SC-005 | `composer verify` is green on the merge commit. | CI status check `verify` passes on the merging PR. |
| SC-006 | Issues #1445, #1446, #1447 close on merge. | GitHub auto-closes via `Closes #N` footer on the merge commit. |
| SC-007 | The canonical M-006 / two-axis spec documents the access-gate convention and the BCP-47 regex. | `docs/specs/entity-storage-two-axis.md` (or the M-006 spec, planner picks) diff shows the new section. |

## Key entities

| Entity | Role | Net change in this mission |
|---|---|---|
| `TranslationController` | L4 API controller for translation CRUD. | Edit: add `EntityAccessHandler` constructor parameter; per-method `check()` calls; 403 response shape. |
| `AddTranslationsMigrationGenerator` | L6 CLI handler that writes migration files. | Edit: add regex validation; ensure all interpolated values pass through `phpStringLiteral()`. |
| Langcode regex constant (new) | A single canonical BCP-47-tolerant pattern. | +1 file (or +1 constant on an existing utility class). |
| `TranslatableInterface` | L1 interface declaring the translation contract. | Edit: add `fieldLangcode(string): ?string` declaration. |
| Three unit tests + one integration test | New test coverage per FR-010..FR-013. | +4 test files (or edits to existing test files in the same packages). |
| `docs/specs/entity-storage-two-axis.md` (or M-006 spec) | Update with access-gate convention + regex constant reference. | Edit. |
| `CHANGELOG.md` | `[Unreleased]` entry. | Edit. |

## Assumptions

- The HIGH-severity issue (#1445) is **the gating item** — without an access gate, any consumer that flips `translatable: true` exposes their entity surface to broken access semantics. The mission's value-of-shipping is dominated by FR-001..FR-004.
- The per-method ability mapping in FR-002 follows the framework's existing CRUD ability convention (`view`/`create`/`update`/`delete`). No new ability namespace is introduced. If a future requirement needs per-langcode policies, that is a separate mission.
- The BCP-47-tolerant regex (WP02) accepts language + script + region subtags (`en`, `en-US`, `zh-Hant`, `zh-Hant-TW`). Variants and private-use are out-of-scope for v1; the regex documents what it accepts and what it does not.
- `phpStringLiteral()` already exists as a utility in the generator's package. WP02 confirms its presence and extends it if any interpolation site is missed.
- The integration test in FR-013 / SC-001 follows the established `tests/Integration/Phase??/` pattern — the planner picks the appropriate phase directory based on M-006's phase number.

## Out of scope

- Per-langcode access policies (e.g. "user X can edit `fr` but not `de`").
- Schema changes to translation storage.
- Refactoring `TranslationController` to inherit from a base controller class.
- Encryption or signing of translation payloads.
- UI changes in `packages/admin/`.
- Audit log infrastructure changes — FR-001's "audit log records the denied attempt" relies on whatever the existing access denial path emits; no new audit subsystem.

## WP outline (for /spec-kitty.plan)

The planner is free to revise. Indicative shape:

- **WP01 — TranslationController access gate.** Add `EntityAccessHandler` dependency; per-method `check()` calls; 403 response shape; route option alignment. Unit tests (FR-010). Integration test (FR-013). Closes #1445.
- **WP02 — CLI regex validation + escaping audit.** Define canonical BCP-47-tolerant regex constant; wire validation in `AddTranslationsMigrationGenerator`; audit all interpolation sites; ensure `phpStringLiteral()` covers every operator-provided string. Unit tests (FR-011). Closes #1446.
- **WP03 — TranslatableInterface contract.** Declare `fieldLangcode()` on `TranslatableInterface`. Confirm trait satisfies. Contract test (FR-012). Closes #1447.
- **WP04 — Wrap-up.** Update `docs/specs/entity-storage-two-axis.md` (or M-006 spec) with access-gate convention + regex constant reference. `CHANGELOG.md` entry. Full `composer verify` green.

## References

- M-006 mission: `kitty-specs/archive/entity-storage-translations-v1-01KRF0FQ/` (squash `0f7e1809a`).
- Post-merge audit: cited inline in #1445 / #1446 / #1447.
- `docs/specs/entity-storage-two-axis.md` (canonical two-axis spec, updated in WP04).
- CLAUDE.md gotcha: "Request attribute is `_account` not `account`" (FR-001 source).
- Memory: `feedback_modern_php_rules.md` — typed interfaces only, contract tests for every extension point (FR-012 motivation).
- Memory: `feedback_regression_tests.md` — always write regression tests when fixing bugs (FR-010..FR-013).
