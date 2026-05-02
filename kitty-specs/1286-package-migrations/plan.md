# Plan: #1286 — Phased delivery

## Phase 0 — Design lock (this mission)

- [x] Mission + `spec.md` approved as architectural guardrail (Russell / Russell+agent).
- [ ] Optional: short comment on GitHub #1286 linking to `kitty-specs/1286-package-migrations/spec.md` for contributors.

## Phase 1 — Ship the convention (meets GitHub acceptance)

1. Pick **reference package** for first framework-owned migration beyond queue/notification/scheduler (issue suggests **OIDC** if use case is ready).
2. Add **`migrations/`** (or agreed layout) + **`extra.waaseyaa.migrations`** if not already wired for that package’s DDL needs.
3. Implement migration using **`Migration` + `SchemaBuilder`** only (no provider boot DDL).
4. **Spec** — document path layout, naming, `$after` ordering rules, manifest merge behavior, and “do not use `boot()` for additive columns”.
5. **Tests** — loader + one integration path on fresh DB.
6. **Follow-up PR** — remove **`OidcServiceProvider::boot()`** `addFieldColumns` workaround once migration is merged and verified.

## Phase B — Manifest evolution (RFC until coded)

- Extend **`extra.waaseyaa.migrations`** shape (string | list) with compiler + loader support.
- Consider **checksum** column or migration identity hash in `MigrationRepository` (ADR).
- Introduce **`--dry-run` / `--verify`** flags aligned with diff engine from #529.

## Phase C — SchemaDiff-native migrations (#529 dependency)

- Replace or augment `Migration::up(SchemaBuilder)` authoring with **structural diff objects** derived from entity/field registries.
- Keep ledger + batch semantics; change **authoring surface**, not operator safety model.
