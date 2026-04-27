# WP09 Review — Cycle 1 (Changes Requested)

## Summary

PHPStan clean, benchmarks within budget, SC-001/003/005 verified, and the seven WP07-missed integration test files were correctly identified and patched. **However, SC-002 is not actually met.** The implementer's note states the SC-002 grep returns "8 hits, all documented exclusions" — re-running the exact grep from the WP09 prompt returns **30 hits**, and one of them is a real defect that escapes mission scope: the `make:provider --domain` codegen stub emits the old API.

## Issue 1 — `provider-domain.stub` codegen still emits the deleted `fieldDefinitions:` named arg (BLOCKING)

**File**: `packages/cli/stubs/provider-domain.stub` (line 27)

```php
$this->entityType(new EntityType(
    id: '{{ entity_type_id }}',
    label: '{{ entity_label }}',
    class: {{ entity_class }}::class,
    keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
    fieldDefinitions: [        // <-- generates broken code
        'id' => ['type' => 'integer'],
        ...
    ],
));
```

`EntityType::__construct` no longer has a public `$fieldDefinitions` parameter — it has `$_fieldDefinitions` (intentionally underscore-prefixed and `@internal`). Any user running `php vendor/bin/waaseyaa make:provider Blog --domain` gets generated code that throws `\Error: Unknown named parameter $fieldDefinitions` the first time the provider is loaded. There is even a regression test in `packages/entity/tests/Unit/EntityTypeFromClassTest.php::testPassingFieldDefinitionsAsNamedArgumentToConstructorThrows` that codifies exactly this break.

This should have been picked up by WP05 (which migrated `make:entity-type` codegen) but the `make:provider --domain` path was missed — same pattern as the seven WP07 fixtures you correctly caught and patched in this WP. Fix it under WP09's authority (mechanical, single stub + one test assertion) and document the override the same way you documented the seven integration-test patches.

**Required fix**:
1. Rewrite `packages/cli/stubs/provider-domain.stub` to use the attribute-first form: generate a `#[ContentEntityType]`-decorated entity class scaffold and a provider that calls `EntityType::fromClass({{ entity_class }}::class)`. (Mirror what `make:entity-type` now emits — that was the WP05 deliverable.)
2. Update `packages/cli/tests/Unit/Command/Make/MakeProviderCommandTest.php:62` (the `assertStringContainsString('fieldDefinitions:', $output)` assertion) to assert on the new shape (e.g. `EntityType::fromClass(`).

## Issue 2 — SC-002 evidence is inaccurate; please re-grep and re-classify (BLOCKING for sign-off)

The activity log says: *"SC-002 grep effectively 0 (only documented exclusions)"* with a count of 8.

Actual count using the prompt's grep:
```
grep -rn 'fieldDefinitions:' packages/ | grep -v 'tests/Helper/TestEntityType' \
  | grep -v 'EntityType.php' | grep -v 'ContentEntityBase' \
  | grep -v 'GroupsServiceProvider' | grep -v 'parent::__construct'
```
Returns **30 hits**, distributed as:

| Bucket | Count | Status |
|---|---|---|
| `Node.php` / `User.php` / `OidcClient.php` (entity-instance `parent::__construct(... fieldDefinitions: ...)` to `ContentEntityBase`) | 6 | LEGITIMATE — different param namespace; documented exclusion class but the grep filter `parent::__construct` doesn't catch them because they span multiple lines. Update the exclusion or pre-filter. |
| `TestEntityType::stub(fieldDefinitions: ...)` callers (admin-surface, ai-vector, genealogy, ssr, testing tests) | ~21 | LEGITIMATE — `stub()` exposes `fieldDefinitions:` as its own public param and translates to `_fieldDefinitions:` internally. These should also be filtered (e.g. `grep -v 'TestEntityType::stub'` or assert via AST). |
| `EntityTypeFromClassTest:144` | 1 | LEGITIMATE — explicit regression test for the API break (`expectException(\Error::class)`). |
| `provider-domain.stub` + `MakeProviderCommandTest:62` | 2 | **REAL DEFECT** — see Issue 1. |

Once Issue 1 is fixed, please re-run the grep, list each remaining hit with its bucket, and update the activity-log evidence. SC-002 stands as "zero genuine entity-class field declarations outside `#[Field]`" once the stub is migrated.

## Verified items (no action needed)

- **PHPStan**: `vendor/bin/phpstan analyse --memory-limit=2G` → `[OK] No errors`.
- **NFR-001 / NFR-002 benchmarks**: `EntityTypeFromClassBenchmarkTest` 2/2 in 16ms. Well within 5ms first-call / 0.1ms cached budget.
- **PHPUnit**: ran to ~55% before hitting the documented pre-existing GraphQL parser memory exhaustion (`webonyx/graphql-php` Parser.php). Same env failure mode as the activity log claims; not a mission regression. Tests up to that point were green or showing pre-existing OIDC/Windows-path failures.
- **SC-001**: `Note.php` declares `#[ContentEntityType]`; `NoteServiceProvider` calls `EntityType::fromClass(Note::class)`. Confirmed.
- **SC-003**: `grep -rn 'assertClassMetadataMatchesEntityType' packages/` → 0 hits. Confirmed.
- **SC-005**: deferred to T045 once the two issues above land cleanly.
- **WP07 miss patches** (7 integration test files): correctly identified, mechanically bounded (rename `fieldDefinitions:` → `_fieldDefinitions:`), and within WP09's documented override authority. Approved.

## What we need to see in cycle 2

1. `provider-domain.stub` migrated to attribute-first form.
2. `MakeProviderCommandTest` updated accordingly (still passing).
3. Re-run SC-002 grep with corrected exclusion list; record the precise residual count and bucket each hit. If any bucket changes, update the activity log.
4. PHPStan + benchmark re-run (should still be clean).
