---
work_package_id: WP11
title: Composer manifest array form (Phase 6)
dependencies:
- WP03
requirement_refs:
- FR-003
- C-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T062
- T063
- T064
- T065
- T066
- T067
- T068
history:
- date: '2026-05-02'
  note: Initial generation by /spec-kitty.tasks-packages.
authoritative_surface: packages/foundation/src/Discovery
execution_mode: code_change
mission_id: 01KQN41MQD3Y6PG0PES8XX166F
mission_slug: 529-schema-evolution-v2
owned_files:
- packages/foundation/src/Discovery/PackageManifestCompiler.php
- packages/foundation/src/Migration/MigrationLoader.php
- packages/foundation/tests/Unit/Discovery/PackageManifestCompilerArrayMigrationsTest.php
- packages/foundation/tests/Unit/Migration/MigrationLoaderArrayFormTest.php
tags:
- foundation
- manifest
- composer
- phase-6
---

# WP11 — Composer manifest array form (Phase 6)

## Objective

Add ordered-array support to `extra.waaseyaa.migrations` per Q9. After this WP:

- `composer.json`'s `extra.waaseyaa.migrations` accepts EITHER:
  - the existing string path form (e.g. `"migrations": "migrations"`) — still supported, no removal date,
  - OR an ordered array of entries: namespace FQCNs and/or path strings, in apply order.
- `PackageManifestCompiler` accepts both shapes; `migrations` widens to `string|list<string>` while preserving order.
- `MigrationLoader` discovers v2 migrations via FQCN namespace roots and legacy migrations via path strings; both can coexist in one array entry list.
- Discovery rules ADR documents what each entry kind means and the validation applied (no opaque class-string registries — every namespace is validated against composer's autoload map at boot).
- CHANGELOG + workflow.md announce: array form is preferred for new packages; string path remains supported indefinitely.

## Context

Read before starting:

- `docs/specs/schema-evolution-v2.md` §8 (manifest evolution), §15 Q9.
- WP03: `MigrationInterfaceV2` — what FQCN classes implement.
- Existing `packages/foundation/src/Discovery/PackageManifestCompiler.php` — current single-string `migrations` parsing.
- Existing `packages/foundation/src/Migration/MigrationLoader.php` — current directory-based discovery.
- `docs/specs/infrastructure.md` § package-declared migrations — current contract.

## Subtasks

### T062 — `PackageManifest` widens `migrations` to `string|list<string>`

**Purpose:** Type the new shape end-to-end.

**Steps:**
1. Modify `PackageManifestCompiler::scanPackageManifests()` (or equivalent) to accept either a string or a list of strings under `extra.waaseyaa.migrations`.
2. Preserve list order; PHP arrays are ordered, but ensure JSON decoding doesn't reorder.
3. Validate non-string entries fail loud at compile time with `INVALID_MIGRATION_ENTRY` diagnostic naming the offending package.

**Files:** modify `Discovery/PackageManifestCompiler.php`, `Discovery/PackageManifest.php` (if a typed DTO exists for this field).

### T063 — `MigrationLoader` parses ordered array entries

**Purpose:** Resolve each entry to a list of migrations.

**Steps:**
1. For each entry:
   - Looks like an FQCN namespace (`Vendor\\Package\\Migrations\\v2`)? → discover all `MigrationInterfaceV2` classes under that namespace via composer's classmap (validate at discovery time; no `::class` strings unchecked).
   - Looks like a path? → existing directory-loader behavior (load `*.php` files in lex order — this stays as the legacy escape hatch).
2. Concatenate per-entry results in array order; `MigrationGraph` (WP06) handles cross-entry / cross-package dependency edges.
3. Reject entries that match neither shape (e.g. starting with `::` or empty) with a structured error.

**Files:** modify `Migration/MigrationLoader.php`.

### T064 — Coexistence rule: string path remains supported

**Purpose:** Q9's hybrid resolution — no hard removal date.

**Steps:**
1. The `string` form continues to work exactly as it does today (#1286): one string = one directory, lex-order load.
2. The string form is internally treated as `[$string]` for unified processing through the same array path; this avoids two parallel code paths.
3. Document that mixing forms within one package is technically possible but not recommended (one or the other per package).

**Files:** modify `Migration/MigrationLoader.php`.

### T065 — Discovery rules ADR

**Purpose:** Answer "what does each entry kind mean?" once.

**Steps:**
1. Author `docs/adr/009-migration-manifest-discovery.md`.
2. Cover: heuristic for FQCN vs path detection; what constitutes a valid namespace entry; how composer classmap is consulted; what happens for entries with no matches (warn? error? silent skip?); ordering semantics within a package vs across packages.
3. Recommended heuristic: contains `\\` AND first char is uppercase letter ⇒ FQCN; starts with `./` or `/` or contains `/` only ⇒ path; otherwise → error.

**Files:** `docs/adr/009-migration-manifest-discovery.md`.

### T066 — Validate array order is preserved

**Purpose:** PHP `array` lists preserve order, but tests should lock this against any future deserialization regression.

**Steps:**
1. Test fixture: a `composer.json` with `migrations: ["Z\\Migrations", "A\\Migrations", "../patches/v2"]`.
2. Assert `PackageManifestCompiler` returns the entries in `Z, A, patches` order — NOT alphabetized.
3. Assert `MigrationLoader` resolves them and concatenates in that order.

**Files:** `tests/Unit/Discovery/PackageManifestCompilerArrayMigrationsTest.php`.

### T067 — CHANGELOG + workflow.md update

**Purpose:** Announce the preferred form.

**Steps:**
1. CHANGELOG entry: "Composer manifest `extra.waaseyaa.migrations` now accepts an ordered array of namespace and/or path entries. Recommended for new packages where ordering matters. String path form remains supported indefinitely."
2. `docs/specs/workflow.md`: update the migration authoring section to show the array form as the canonical example, with a note that string path is the older escape hatch.
3. Subsystem spec `docs/specs/infrastructure.md`: § package-declared migrations gains a "v2 array form" sub-section.

**Files:** `CHANGELOG.md`, `docs/specs/workflow.md`, `docs/specs/infrastructure.md`.

### T068 — Unit + integration tests

**Cases:**
1. String form: `"migrations": "migrations"` → loads as today, tests preserve current behavior.
2. Array with mixed kinds: `["Vendor\\Migrations", "../local/patches"]` → both load, in order.
3. Empty array: `"migrations": []` → no migrations loaded, no error.
4. Invalid entry (object instead of string): `"migrations": [{ "type": "..." }]` → `INVALID_MIGRATION_ENTRY`.
5. Namespace with no matching classes: `"migrations": ["NonExistent\\Migrations"]` → warning logged, no migrations loaded (do NOT silently skip — log).

**Files:** `tests/Unit/Discovery/PackageManifestCompilerArrayMigrationsTest.php`, `tests/Unit/Migration/MigrationLoaderArrayFormTest.php`.

## Definition of Done

- [ ] `extra.waaseyaa.migrations` accepts both string and list-of-strings.
- [ ] FQCN entries discovered via composer classmap; path entries via directory scan.
- [ ] Order preserved end-to-end.
- [ ] ADR `009-migration-manifest-discovery.md` exists.
- [ ] CHANGELOG + workflow.md + infrastructure.md updated.
- [ ] All test cases pass.
- [ ] PHPStan level 5 clean. `bin/check-package-layers`, `bin/check-composer-policy` clean.

## Risks / Reviewer guidance

- **No hard removal date.** Q9 is explicit: string path is supported indefinitely. If the diff includes a `@deprecated` notice with a removal version, push back — that requires a separate ADR + major version.
- **No opaque class-string registries.** Every FQCN entry is validated at discovery time against composer's autoload map. Untrusted JSON containing arbitrary `::class` strings would let someone register a class that doesn't exist as a "migration" — silent skip. Reject loud.
- **Heuristic fragility:** the FQCN-vs-path detector in T065 has edge cases (Windows paths with `\\`?). The ADR should call this out and propose explicit override syntax (`{"type": "namespace", "value": "..."}`) for a future iteration if real-world ambiguity emerges.
- **Don't reorder arrays.** Some JSON parsers / type widenings can silently key-rewrite. Test it explicitly with a fixture that would reorder under associative-array assumptions.
