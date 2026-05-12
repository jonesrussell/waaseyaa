# WP01 Review Feedback — Cycle 1

**Decision:** Request changes.

**Commit reviewed:** `05ab75a65` on `kitty/mission-entity-storage-v2-01KRCDDC-lane-a`.

**Quality gates (all passing):**
- `./vendor/bin/phpunit packages/entity-storage/tests/Unit/Backend/ packages/field/tests/Unit/FieldDefinitionStoredInTest.php` — 22 tests, 44 assertions, green.
- `composer cs-check` — clean.
- `composer phpstan` — `[OK] No errors` (1201 files).
- `bin/check-package-layers` — clean.
- `bin/check-composer-policy` — clean.

Findings are ordered by severity. The single blocking item is #1; the rest are correctness-or-polish notes that should land in the same fix-up commit.

---

## 1. (BLOCKING) `FieldStorageBackendInterface::supportsQuery()` is missing the `EntityQuery` parameter

**What's wrong.** `packages/entity-storage/src/Backend/FieldStorageBackendInterface.php` declares:

```php
public function supportsQuery(FieldDefinition $field): bool;
```

The normative contract (`kitty-specs/entity-storage-v2-01KRCDDC/contracts/field-storage-backend.md` §Interface) requires:

```php
public function supportsQuery(FieldDefinition $field, EntityQuery $query): bool;
```

The implementer dropped the second parameter because `Waaseyaa\Entity\Storage\Query\EntityQuery` does not yet exist in the codebase. The interface PHPDoc was also rewritten (the contract says "MUST throw `UnsupportedQueryException` with a precise reason"; the shipped doc says "MUST return false; callers MUST then throw"). Both edits silently rewrite the contract.

**Why it matters.**
- The interface carries `@api`. Per charter §5.3, that is a stable-surface declaration — every consumer that implements it now must change their signature when WP06 (or whichever WP introduces `EntityQuery`) lands. That is a breaking change to a stable surface within the same mission.
- FR-003 explicitly enumerates `supportsQuery(FieldDefinition, EntityQuery): bool` as part of the per-field strategy contract. Shipping a degraded signature does not satisfy FR-003 — it satisfies a different, weaker contract that the spec did not authorize.
- The semantic shift from "throw `UnsupportedQueryException`" to "return false; caller throws" is a separate, undocumented contract change. Backends written against the shipped doc will not be portable to the canonical contract.
- Spec §1.2 / §2.2 explicitly call out "no shifting of stable surfaces" as a non-goal. Future WPs introducing `EntityQuery` must not break stable surfaces — the cleanest way to honor that is to stabilize the surface correctly the first time.

**How to fix.**

Introduce `EntityQuery` as a minimal marker interface in WP01 so the canonical signature ships intact. Concretely:

1. Create `packages/entity-storage/src/Query/EntityQuery.php`:
   ```php
   <?php
   declare(strict_types=1);

   namespace Waaseyaa\EntityStorage\Query;

   /**
    * @api
    *
    * Marker contract for entity-query objects passed to
    * {@see \Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface::supportsQuery()}.
    *
    * WP06 (query-support exceptions) and later WPs flesh this out with the
    * conditions, sorts, and pagination surface; WP01 only fixes the type so
    * the backend contract is stable from the first commit.
    */
   interface EntityQuery
   {
   }
   ```
2. Create `packages/entity-storage/src/Exception/UnsupportedQueryException.php` as a `RuntimeException` subclass — the contract names it as the failure mode and the interface doc references it. It can be a minimal class with a `backendId`/`reason` constructor; WP06 enriches it.
3. Restore the interface signature exactly:
   ```php
   public function supportsQuery(FieldDefinition $field, EntityQuery $query): bool;
   ```
4. Restore the PHPDoc wording to match the contract verbatim ("MUST throw `UnsupportedQueryException` with a precise reason").
5. Update `BackendRegistrarTest` test doubles to satisfy the new signature (a one-line `EntityQuery` anonymous class in the test fixture suffices).

This keeps WP01's owned-files list intact and trivially small (~30 LOC of new scaffolding) while preserving the stable surface. WP06 then enriches `EntityQuery`/`UnsupportedQueryException` without touching the interface signature.

---

## 2. (MUST FIX) Namespace mismatch with the normative contract

**What's wrong.** All shipped classes live under `Waaseyaa\EntityStorage\…` (no separator between "Entity" and "Storage"). The contract document (and the spec snippets it embeds) consistently use `Waaseyaa\Entity\Storage\…` (with a sub-namespace). Likewise the canonical query class is documented as `Waaseyaa\Entity\Storage\Query\EntityQuery`, not `Waaseyaa\EntityStorage\Query\EntityQuery`.

**Why it matters.** The package PHP namespace is the public surface. Drifting from the spec means every cross-reference in `data-model.md`, `research.md`, downstream WP prompts, and future consumer code is incorrect. Resolving this later forces a coordinated rename across many files — exactly the kind of stable-surface churn spec §1.2 calls out.

**How to fix.** Check `packages/entity-storage/composer.json` autoload PSR-4 root. If it is `Waaseyaa\EntityStorage\` (current package convention — see the `database-legacy → Waaseyaa\Database` precedent), then update the contract document and downstream WP planning artifacts to match what is shipped. If the spec is authoritative, rename the namespace to `Waaseyaa\Entity\Storage\`. Either way, contract and code must agree before WP02 begins building on them. Pick one and align both in this WP. Document the resolution in a one-line note under the contract's §Interface heading.

---

## 3. (SHOULD FIX) `BackendRegistrar` discovery contract is decoupled from the spec

**What's wrong.** T005 says discovery happens via `PackageManifestCompiler` capability scan for `HasFieldStorageBackendsInterface`. The shipped `BackendRegistrar` takes a pre-built array of provider FQCNs in its constructor (and a second array of framework-FQCNs to authorize reserved-id use). There is no integration with `PackageManifestCompiler` and no test asserting the wiring.

**Why it matters.** WP05 (sql-column backend) and later WPs depend on the registrar being booted automatically. If the integration is deferred entirely to a future WP, the stable surface — "providers implementing `HasFieldStorageBackendsInterface` are discovered at boot" — is unverified. The current shape also makes "framework provider FQCN" a constructor secret rather than a discoverable property (e.g. a marker interface or `composer.json` `extra.waaseyaa.framework-storage-provider: true` flag).

**How to fix.** Either:
- (a) Add a thin `BackendRegistrarFactory` (or `boot()` method) that reads from `PackageManifestCompiler` and wires the framework-provider set from a discoverable signal, plus one integration-style test using a fake manifest; or
- (b) Document explicitly in the WP01 status note that PackageManifestCompiler wiring is deferred to WP02's coordinator, and add a `@todo` line plus a test that asserts the deferred contract (e.g. a fixture provider class is correctly indexed when fed in).

Option (a) is preferable because T005 enumerates it as in-scope.

---

## 4. (SHOULD FIX) `BackendIdCollisionException` constructor ordering is inconsistent for the reserved-id case

**What's wrong.** When a third party tries to claim `sql-blob`, the registrar throws `BackendIdCollisionException($id, $firstFqcn, $secondFqcn)`. For the reserved-id case there is no "first FQCN" — the framework has not yet registered its own `sql-blob` implementation in WP01 (that is WP03). The current tests pass a synthetic `$firstFqcn` derived from `ReservedBackendIds::class`, which makes the exception message misleading ("already registered by `Waaseyaa\…\ReservedBackendIds`").

**Why it matters.** Operators reading the exception will look for a registered provider class that does not exist. The exception message is part of the operator-diagnostics contract.

**How to fix.** Either (a) add a dedicated `ReservedBackendIdException` subclass with a clearer message ("backend id `sql-blob` is reserved by the framework; third-party providers MUST register under a different id"), or (b) keep the single exception but accept `?string $firstFqcn = null` and branch the message format. Update the two reserved-id tests to assert the new message.

---

## 5. (NICE TO HAVE) `FieldDefinition::storedIn()` accepts any string with no boot-time check in this WP

The shipped `FieldDefinition::storedIn('unknown-id')` succeeds — the WP prompt says boot-time validation lives in `BackendRegistrar::validateFieldBackendIds()`, which is implemented and tested. Good. However, neither the WP01 README nor the new methods' PHPDoc points the reader at the registrar's validator. Add a `@see \Waaseyaa\EntityStorage\Backend\BackendRegistrar::validateFieldBackendIds()` to the `storedIn()` doc-block so consumers know where the bad-id failure is raised.

---

## Summary

Items 1 and 2 are the blocking issues. Item 1 is the stable-surface defect explicitly flagged by orchestrator and confirmed here; item 2 is a namespace drift that, if left unresolved, will compound into a multi-WP rename later. Items 3–5 are smaller and could land in the same fix-up commit.

All quality gates currently pass — fixing the items above will not regress them. Re-review will focus on the interface signature, the namespace alignment, and the registrar discovery seam.
