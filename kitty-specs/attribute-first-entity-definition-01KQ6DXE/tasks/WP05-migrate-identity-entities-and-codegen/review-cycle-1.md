# WP05 Review — Cycle 1

## Verdict: Changes Requested

The bulk of WP05 is solid, but there is one concrete regression that must be fixed before approval.

---

## Validated Deviations (acceptable)

1. **`GroupsServiceProvider.php` retains `new EntityType(...)` for `Group` content entity** — VALIDATED.
   - Confirmed `packages/entity/src/Attribute/Field.php` exposes only `type, required, default, label, description, settings, readOnly, translatable, revisionable` — no `stored:` parameter.
   - `packages/field/src/FieldStorage.php` enum exists. Group's `status`/`created_at`/`updated_at` legitimately need `stored: FieldStorage::Data` so registry-aware queries can resolve them via json_extract on the bundle-partitioned data table.
   - The `_fieldDefinitions:` slot is documented as `@internal` in `EntityType`, used here as a transitional bridge with a clear explanatory comment (lines 24-33 of `GroupsServiceProvider.php`). Acceptable as a bounded workaround. Adding `stored:` to `#[Field]` is a follow-on mission.

2. **`GroupType` config entity registration stays `new EntityType(...)`** — VALIDATED. Per AD-3 and consistent with WP04's `Vocabulary`/`NodeType` pattern. Comment on lines 72-74 explains it.

3. **OidcClient 33 RSA-key test failures** — VALIDATED PRE-EXISTING.
   - On `HEAD~1` (pre-WP05): `Tests: 182, Assertions: 343, Errors: 5, Failures: 33`.
   - On `HEAD` (post-WP05): `Tests: 182, Assertions: 358, Failures: 33` (no errors).
   - WP05 fixed the 5 errors and did not introduce any new failures. All 33 RSA failures are environment-specific (missing OpenSSL crypto setup in this env), not WP05's regression.

4. **OidcClient `client_secret_hash` marked `readOnly: true`** — VALIDATED. Correct for a hashed secret that should be set once. Acceptable.

5. **`text_long`/`timestamp` collapse to base type + subtype** — VALIDATED. Same gap as WP04; covered by Phase 2 follow-on missions.

6. **PHPStan & runtime tests** — Clean.
   - `vendor/bin/phpstan analyse` across all 5 packages + `MakeEntityTypeCommand.php`: **0 errors**.
   - `vendor/bin/phpunit packages/engagement/ packages/groups/ packages/messaging/ packages/path/`: **111/111 passing**.
   - `vendor/bin/phpunit packages/cli/tests/Unit/Command/Make/`: **22/22 passing**.

---

## Issue 1 (BLOCKING): MakeEntityTypeCommandTest is now failing 2/2

**File**: `packages/cli/tests/Unit/Command/MakeEntityTypeCommandTest.php` (note: this is the file directly under `Command/`, not under `Command/Make/`).

**Reproduction**:
```
php vendor/bin/phpunit packages/cli/tests/Unit/Command/MakeEntityTypeCommandTest.php
# Tests: 2, Assertions: 13, Failures: 2
```

**Pre-WP05 state** (`git checkout HEAD~1 -- packages/cli/src/Command/MakeEntityTypeCommand.php`): test passes 2/2.
**Post-WP05 state**: test fails 2/2.

This is a direct regression introduced by WP05. The two failing assertions are:

- `it_generates_a_config_entity_by_default`: asserts `CHANGE_ME` appears in the config template output. The new `renderConfigTemplate()` no longer emits `CHANGE_ME`.
- `it_generates_a_content_entity_with_flag`: asserts the content template output contains `HydratableFromStorageInterface`, `function fromStorage`, and `function make`. The new attribute-first template intentionally omits these (correct under the new design), so the assertions are now stale.

**Why this is in WP05's scope**:

WP05 task prompt T028 step 4 explicitly says:
> *"Update any tests for the command (e.g. `packages/cli/tests/Unit/Command/Make/MakeProviderCommandTest.php`)."*

The "Note" excusing test work to WP07 is scoped to `MakeProviderCommandTest.php` (a *companion* test for a different command). `MakeEntityTypeCommandTest.php` is the *direct unit test for `MakeEntityTypeCommand`*, the one CLI source file owned by WP05. Under WP05's owned-files contract the source file is `packages/cli/src/Command/MakeEntityTypeCommand.php`; updating its dedicated test to match the new template output is the natural completion of T028, not WP07 territory.

**How to fix**:

1. Update `packages/cli/tests/Unit/Command/MakeEntityTypeCommandTest.php` so:
   - `it_generates_a_config_entity_by_default` no longer asserts `CHANGE_ME`. Adjust expectations to match the new config-template output (it should still assert `extends ConfigEntityBase`, `use Waaseyaa\Entity\ConfigEntityBase;`, and the new `id: '<typeId>'` / `'label' => 'label'` keys block).
   - `it_generates_a_content_entity_with_flag` removes the assertions for `HydratableFromStorageInterface`, `function fromStorage`, and `function make`. Replace with assertions that match the new attribute-first scaffold: `#[ContentEntityType(`, `#[ContentEntityKeys(`, `#[Field]`, and the `EntityType::fromClass(<Class>::class, group: 'content')` registration hint comment.
2. Confirm the test passes:
   ```
   php vendor/bin/phpunit packages/cli/tests/Unit/Command/MakeEntityTypeCommandTest.php
   # Expected: OK (2 tests, ≥6 assertions)
   ```

**Note on owned_files boundary**: The owned_files frontmatter lists `packages/cli/src/Command/MakeEntityTypeCommand.php` (production only). Strictly, modifying a test under `packages/cli/tests/` is outside that path. However, T028 step 4 in the prompt overrides the path-only frontmatter for this specific test. If the implementer disagrees, the alternative is to roll the regression forward as a documented hand-off to WP07 — but currently the WP07 frontmatter does not list this file either, and leaving the test broken on master would block CI for all subsequent WPs. Recommend updating the test in WP05.

---

## Optional follow-up suggestions (not blocking)

- The `_fieldDefinitions:` workaround in `GroupsServiceProvider.php` is acceptable but a follow-on mission to add `stored:` to `#[Field]` should be filed (likely lives in the same Phase 2 follow-on as the `text_long`/`timestamp` collapse work).
- The 33 OIDC RSA test failures are environment-specific in *this* CI environment but could be investigated separately to determine if the test setup should generate keys lazily / use fixture keys to be portable.

---

## Summary

5 of 6 deviations validated as acceptable. One blocking regression in `MakeEntityTypeCommandTest.php` requires the test to be updated to match the new attribute-first template output. After that fix, WP05 is ready to approve.
