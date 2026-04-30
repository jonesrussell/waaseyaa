# Sub-task 2 — Issue closure comments (DRAFT)

**Pattern:** Path X (mission spec.md line 38) — close the seven sibling issues with a cross-link comment pointing back to the mission. Anchor `#1257` stays open per user keep-flag; its body gets annotated with merged-commit references at WP11 acceptance.

**Status:** Drafts only. Each comment cites the K-grade ratification and the WP that subsumes the issue. Surfaces the K6(c) prerequisite (already merged).

**Verification before posting** (all true as of 2026-04-30):
- `bin/check-package-layers` `KERNEL_EXEMPT_FILES` contains `foundation/src/Diagnostic/HealthChecker.php` with rationale citing K6(c).
- `EntityTypeRegistrationCollisionException::duplicate(...)` already names both registrants (D2 verified — WP07 part B is no-op).
- `Waaseyaa\Groups\Group` is `final`; Minoo declares the dep + has a parallel-Group transition in flight.

---

## Closure comment template (used by every issue)

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP{NN} — {wp-title}**.

Convention/contract: **{K-grade}** — see `.kittify/missions/1257-entity-storage-hardening/spec.md` §{K-grade-section}.

{ONE-LINE-CONVENTION-SUMMARY}

Implementation will land via WP{NN}. The anchor issue (#1257) remains open per Path X — this issue's content is preserved in the mission's decomposition.md row.
```

---

## Per-issue closure text

### `#1298` — Centralize bundle-subtable name helper with `__` separator guard

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP03 — bundle-naming-centralization**.

Convention: **K1** — `.kittify/missions/1257-entity-storage-hardening/spec.md` §K1.

`{base}__{bundle}` and the `__`-in-bundle-id structural guard belong to a single shared helper consumed by `SqlEntityStorage`, `SqlEntityQuery`, and `SqlSchemaHandler`. The structural guard also fires at registration time in `EntityTypeManager::addBundleFields()` (belt and suspenders). Implementation lands via WP03; specs ratified in `docs/specs/bundle-scoped-storage.md` §Naming and `docs/specs/entity-system.md` §EntityTypeManagerInterface.

Anchor issue (#1257) remains open per Path X.
```

### `#1299` — Log warning when load() encounters a registered-field bundle with no subtable

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP06 — bundle-load-drift-logging**.

Convention: **K4** — `.kittify/missions/1257-entity-storage-hardening/spec.md` §K4.

`SqlEntityStorage::mergeBundleSubtableRow()` and `mergeBundleSubtableRowsBatch()` log once per `(entity_type, bundle)` per process — memoized on `bundleSubtableCache` — when the subtable is absent. Diagnostic-loop tightening, not throwing: preserves the open-by-default model from `docs/specs/operator-diagnostics.md`. Implementation lands via WP06; spec ratified in `docs/specs/bundle-scoped-storage.md` §Lifecycle.

Anchor issue (#1257) remains open per Path X.
```

### `#1300` — Extract HealthChecker's entity/entity-storage dependencies to preserve layer discipline

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP08 — healthchecker-layer-placement**.

Convention: **K6 option (c)** — `.kittify/missions/1257-entity-storage-hardening/spec.md` §K6.

**Decision: option (c) — codified kernel-adjacent exemption.** `HealthChecker` stays in `packages/foundation/src/Diagnostic/` and imports from L1; the cross-layer privilege is granted explicitly via `KERNEL_EXEMPT_FILES` in `bin/check-package-layers`.

Hard prerequisite **already merged** in mission #824 WP02 surface C (commit a07d80f4f) — the named-file exemption surface itself — and the explicit HealthChecker entry sits there with a citation back to K6(c) (mission #1257 / #1300). Verifiable in `bin/check-package-layers`:

```
"foundation/src/Diagnostic/HealthChecker.php":
    "kernel-adjacent diagnostic wired only from ConsoleKernel; #1300 / mission 1257 K6(c)",
```

WP08 is therefore a no-op verification step — the entry is already present from #824 surface C. Spec ratified in `docs/specs/bundle-scoped-storage.md` §Drift diagnostic and `docs/specs/infrastructure.md` §Kernel exemption surface.

Anchor issue (#1257) remains open per Path X.
```

### `#1301` — Portable ORPHAN_BUNDLE_SUBTABLE detection for MySQL and PostgreSQL

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP09 — portable-orphan-detection**.

Convention: **K5** — `.kittify/missions/1257-entity-storage-hardening/spec.md` §K5.

`HealthChecker::findOrphanSubtables()` enumerates tables via DBAL `AbstractSchemaManager::listTableNames()` filtered against `{base}__%`; SQLite's `sqlite_master` is retained as a fast-path. Test matrix gates a non-SQLite run behind a docker-compose env var so MySQL/PostgreSQL coverage is mechanical. Implementation lands via WP09; spec ratified in `docs/specs/bundle-scoped-storage.md` §Drift diagnostic.

Anchor issue (#1257) remains open per Path X.
```

### `#1304` — Move tenancy opt-in from HasCommunityInterface marker to EntityType/storage registration

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP10 — tenancy-opt-in-via-entitytype**.

Contract: **C1 (option 1)** — `.kittify/missions/1257-entity-storage-hardening/spec.md` §C1.

**Decision: declarative `tenancy:` key on `EntityType`.** `EntityType` gains a named constructor parameter `tenancy: ['scope' => 'community']` (or `null` for non-tenant types). `SqlStorageDriver` / `MemoryStorageDriver` wire `CommunityScope` from this declaration; the entity class needs no marker interface. `HasCommunityInterface` enters a deprecation cycle (one-time `LoggerInterface::warning()` per `(entity-type id, process)` on first wiring); removal in the next minor release. Migration recipe in `packages/groups/CHANGELOG.md`.

Implementation lands via WP10; spec ratified in `docs/specs/entity-system.md` §Community Scoping (new §Tenancy declaration + §Migration subsections).

This is the **only public-contract addition** in mission #1257 — flagged as breaking, accepted under the modern stance (PHP 8.4+, no legacy).

Anchor issue (#1257) remains open per Path X.
```

### `#1308` — entity-storage: symmetric query-side handling for FieldStorage::Data when legacy column exists

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP04 — read-write-symmetry-fieldstorage-data**.

Convention: **K2** — `.kittify/missions/1257-entity-storage-hardening/spec.md` §K2.

`SqlEntityQuery::routeFields()` consults `FieldDefinitionRegistry::isDataStored()` — the same source `SqlEntityStorage::splitForStorage()` uses on write via `getDataStoredCoreFieldNames()`. The registry hint wins over `SchemaInterface::fieldExists()` on the query side too; no silent dual-source. Closes the asymmetry that produced silently stale reads when a legacy column lingered after a field gained the storage hint. Implementation lands via WP04; spec ratified in `docs/specs/entity-system.md` §SqlEntityQuery substrate.

Anchor issue (#1257) remains open per Path X.
```

### `#1313` — Shadow-collision guard + clear duplicate-registration error

```
Closing per **mission #1257 entity-storage-hardening** WP02 (Path X) — ratified 2026-04-30.

Subsumed by **WP06 (part A) + WP07 (part B)**.

Two-part body:

**Part A — `addBundleFields()` notice when bundle subtable absent.** Convention: **K4 part A**. `EntityTypeManager::addBundleFields()` emits `LoggerInterface::notice()` with diagnostic code `MISSING_BUNDLE_SUBTABLE` once per `(entity_type, bundle)` when the subtable is not yet materialized. Registration is pre-materialization; this is informational, not a throw. Implementation lands via WP06.

**Part B — `EntityTypeRegistrationCollisionException::duplicate` names both registrants.** Convention: **K7**. **Already correct as of `EntityTypeManager.php:108`** — the message includes existing-registrant FQCN, existing entity class, incoming registrant FQCN, and incoming entity class via `self::registrantLabel()`. WP02 D2 verification confirmed this on 2026-04-30. WP07 ships **only part A** (the notice); part B is a documented no-op.

Spec ratified in `docs/specs/entity-system.md` §EntityTypeManagerInterface.

Anchor issue (#1257) remains open per Path X.
```

---

## Posting commands (review only — do not run until approved)

```bash
# Each issue gets a comment, then is closed.
# Comment text comes from the per-issue sections above.
# DO NOT RUN until user approves each block.

gh issue comment 1298 --body-file <(cat <<'EOF'
[paste #1298 body here]
EOF
) && gh issue close 1298 --reason completed

# repeat for: 1299 1300 1301 1304 1308 1313
```

**Anchor handling.** `#1257` is **NOT** in the close list. Its body gets a separate annotation comment at WP11 acceptance referencing the merged-commit hashes — that's a separate action, not part of WP02.

## Open question for review

The `#1300` closure text claims "WP08 is therefore a no-op verification step — the entry is already present from #824 surface C." That overlaps with WP08's defined scope ("apply chosen K6 option (c/a/b); if (c), add explicit allowlist entry"). Two reads:

- **(a) Strict.** WP08 still runs as a verification-only WP — confirms the entry is present, runs the gate, ratifies acceptance evidence in mission state. Real but light.
- **(b) Collapse.** WP08 is closed at WP02 acceptance because surface C already shipped the work. Saves a WP cycle, but bypasses the spec-kitty review gate for WP08.

My read: (a). The verification step has value (someone has to confirm the gate is green and write the acceptance evidence). Doesn't change the closure text materially — just clarifies what WP08 actually does post-Surface-C.

Decide before posting: keep WP08 as verification-only (a), or collapse it (b).
