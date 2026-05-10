# Quickstart: Verifying PHP 8.4 Lazy Object Hydration

**Mission**: php84-lazy-object-hydration-01KR82KZ
**Audience**: Maintainer reviewing or smoke-testing the merged change.

---

## 1. Run the full test suite

```bash
./vendor/bin/phpunit
```

Expected: green. Includes the new `LazyHydrationParityContractTest` and `EntityTypeManagerLazyStorageTest`.

## 2. Run the new contract tests in isolation

```bash
./vendor/bin/phpunit packages/entity-storage/tests/Contract/LazyHydrationParityContractTest.php
./vendor/bin/phpunit packages/entity/tests/Unit/EntityTypeManagerLazyStorageTest.php
```

Expected: green. These prove parity (lazy ghost behaves like eager instance) and deferral (storage factories not invoked until needed).

## 3. Run the benchmark group

```bash
./vendor/bin/phpunit --group=benchmark
```

Expected: NFR thresholds met.

- **NFR-001**: Cold `find()` key-only read ≥30% faster than the eager baseline.
- **NFR-002**: List-100 key-only read ≥40% fewer `LazyInitCounter::$invocations` than entries returned.
- **NFR-004**: Peak memory regression of the test suite ≤5%.

If any threshold fails, the change is not ready to merge — re-run benchmarks twice to rule out noise, then investigate.

## 4. Layer + manifest checks

```bash
bin/check-package-layers
bin/check-composer-policy
```

Expected: both green. No new layer violations; no manifest churn.

## 5. Static analysis + style

```bash
composer phpstan
composer cs-check
```

Expected: both green.

## 6. Hand smoke-test in dev

```bash
composer dev
```

Hit the admin SPA at `http://localhost:8000`, exercise:

- A list endpoint (e.g., `GET /api/users`) — should return correctly, no observable change.
- A single-entity detail endpoint — should return all fields correctly.
- Create + update + delete an entity via the admin UI — lifecycle hooks must fire (verify via logs or any PRE_SAVE listener).

If any user-visible behavior differs from before the merge, file a regression — laziness must be transparent (constraint **C-001**).

## 7. Rollback path (if a production regression is found post-merge)

The change is contained to two packages. Revert candidates:

- `packages/entity-storage/src/Hydration/EntityInstantiator.php` — fall back to eager `new` of the entity class.
- `packages/entity-storage/src/SqlEntityStorage.php` — call the eager path of `EntityInstantiator`.
- `packages/entity/src/EntityTypeManager.php` — restore the hand-rolled `?\Closure $storageFactory` invocation.

A revert PR should re-run steps 1–5 above to confirm restoration of prior behavior.
