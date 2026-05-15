---
work_package_id: WP12
title: Charter amendments + cookbook + conventions + CLAUDE.md + CHANGELOG + public-surface-map + closure
dependencies:
- WP01
- WP02
- WP03
- WP04
- WP05
- WP06
- WP07
- WP08
- WP09
- WP10
- WP11
requirement_refs:
- FR-059
- FR-060
- FR-061
- FR-062
- FR-063
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T056
- T057
- T058
- T059
- T060
- T061
history: []
authoritative_surface: docs/
execution_mode: planning_artifact
owned_files:
- docs/specs/stability-charter.md
- docs/cookbook/listing-first-cut.md
- docs/conventions/cache-tags-and-contexts.md
- docs/specs/listing-pipeline-v1.md
- docs/specs/public-surface-map.md
- docs/public-surface-map.php
- CLAUDE.md
- CHANGELOG.md
tags: []
---

## Objective

Close out M-007: land all documentation, charter amendments, public-surface-map updates, CLAUDE.md orchestration entries, and CHANGELOG. Final WP — runs after all implementation WPs are merged. This WP makes the mission a stable, governed surface rather than just shipped code.

## Context

- Per ADR 015 §Consequences, charter §3.2 must gain a new beta-entry criterion (10) for listing-pipeline stability + at-least-one-production-consumer.
- Charter §5 gains two new subsections: §5.X (listing surface) + §5.Y (cache tag/context surface). Section numbers chosen at amendment time (continue from §5.8 which M-002 added).
- `docs/cookbook/listing-first-cut.md` and `docs/conventions/cache-tags-and-contexts.md` are NEW files.
- `docs/specs/public-surface-map.md` and `docs/public-surface-map.php` are existing — add the new surface entries.
- `CLAUDE.md` orchestration table gets a new row for `packages/listing/*` → `docs/specs/listing-pipeline-v1.md`. Layer 3 services list updated.

## Subtask details

### T056 — Charter §3.2 criterion 10 amendment

**Steps:**
1. Edit `docs/specs/stability-charter.md`.
2. In §3.2 beta entry criteria, add criterion 10:
   > **Criterion 10 (post-charter):** `ListingDefinition` contract is stable and at least one consumer app uses it for production listings. Without that, "beta" misleads consumers.
3. Use the exact wording from ADR 015 §Consequences.
4. Renumber subsequent criteria if any exist beyond current count.

**Files:** `docs/specs/stability-charter.md` (modified).

### T057 — Charter §5.X listing surface

**Steps:**
1. Add a new §5.X subsection (number assigned at amendment time — continue from §5.8 for migration, so likely §5.9).
2. Section content (per spec §6 + `research.md` cross-mission impact summary):
   - **Stable surface (charter §5.9):**
     - Value objects: `ListingDefinition`, `FilterDefinition`, `SortDefinition`, `Pagination`, `ListingResult`, `ExposedFilterValues`
     - Factories: `Filter`, `Sort`
     - Enums: `Operator`, `SortDirection`
     - Services: `ListingResolver`, `ListingDefinitionRegistry`, `ExposedFilterParser`
     - Capability: `HasListingsInterface`
     - Exceptions: `UnsupportedListingException`, `UnknownListingException`
   - **INTERNAL (not stable):**
     - `ListingCacheKeyBuilder`, `ListingCacheInvalidator`, `ListingDefinitionValidator`, `ExposedFilterCoercer`, `ListingCoercionException`, `ListingDiscoverer`

**Files:** Continued in `stability-charter.md`.

### T058 — Charter §5.Y cache tag/context surface

**Steps:**
1. Add a new §5.Y subsection (likely §5.10).
2. Section content:
   - **Stable surface (charter §5.10):**
     - Interfaces: `TaggedCacheInterface`
     - Services: `ContextResolver`, `ContextRegistry`
     - Constants: `ContextNames::USER_ROLES`, `USER_ID`, `LANGUAGE_CONTENT`, `LANGUAGE_INTERFACE`, `URL_QUERY_PREFIX`
     - Format rules: tag-string regex `[a-z][a-z0-9_:.-]*`; canonical tag vocabulary (`entity:<type>`, `entity:<type>:<id>`, `entity:<type>:<id>:<langcode>`)
3. Make the cross-cutting note from §403: "Cache tags + contexts are stable surface even though they live in `cache` package — the listing contract makes them load-bearing." Verify the existing wording is still accurate; tighten if needed.

**Files:** Continued in `stability-charter.md`.

### T059 — Cookbook + conventions docs

**Steps:**
1. Create `docs/cookbook/listing-first-cut.md`:
   - Adapt from `kitty-specs/listing-pipeline-v1-01KRMN0B/quickstart.md`
   - Production-flavor: real Hugo-style frontmatter, polished prose, no quickstart-specific cross-refs to mission spec
   - Sections: Scenario → Declare a listing → Resolve in a controller → Render in Twig → Automatic cache invalidation → Testing pattern → Common variations
2. Create `docs/conventions/cache-tags-and-contexts.md`:
   - Adapt from `contracts/tagged-cache.md` + `contracts/context-architecture.md`
   - Production-flavor: this is the canonical reference for downstream missions and consumer apps
   - Sections: Tag-string format → Canonical tag vocabulary → Context-name format → Canonical context names → Resolver semantics → How to extend (register custom context names) → How to invalidate (event listener pattern)

**Files:** Both new docs (~250 lines total).

### T060 — `CLAUDE.md` + `CHANGELOG.md` updates

**Steps:**
1. Edit `CLAUDE.md`:
   - Add a row to the orchestration table:
     ```
     | `packages/listing/*` | — | `docs/specs/listing-pipeline-v1.md` |
     ```
   - Update Layer 3 services list to include `listing`:
     ```
     | 3 | Services | workflows, search, seo, notification, billing, github, migration, northcloud, listing |
     ```
   - Verify the orchestration table is alphabetised correctly within Layer 3.
2. Edit `CHANGELOG.md`:
   - Add a bullet under `[Unreleased]` → `### Added`:
     - `M-007 listing-pipeline-v1: Views-equivalent declarative listing surface (ListingDefinition, FilterDefinition, SortDefinition, ListingResolver, ExposedFilterParser, HasListingsInterface, langcode-aware filtering, per-row access policy, offset+limit pagination). New packages/listing/ at Layer 3.`
     - `M-007 cache tag/context architecture: TaggedCacheInterface (setWithTags + invalidateByTag + getTagsFor), ContextRegistry, ContextResolver, canonical ContextNames constants. New §5.10 stable surface in stability-charter.`
     - `M-007 lifecycle event additive surface patch: AfterSaveEvent + AfterDeleteEvent gain optional affectedLangcodes property (null default; backwards-compatible). SqlStorageDriver backfills the array on translatable saves.`

**Files:** `CLAUDE.md` + `CHANGELOG.md` (both modified).

### T061 — `public-surface-map` additions + post-mortem stamp

**Steps:**
1. Edit `docs/public-surface-map.md`:
   - Add entries for every charter §5.9 + §5.10 type listed in T057 + T058.
2. Edit `docs/public-surface-map.php`:
   - Add FQCN → 'public' entries for the new surface (programmatic enforcement).
3. Edit `docs/specs/listing-pipeline-v1.md`:
   - Add a post-mortem stamp at the top (immediately after the title) noting:
     - Mission squash-merge SHA (filled in after the mission merges; leave a placeholder like `<SHA TBD at merge>` initially)
     - Mission close date
     - Brief summary of what shipped vs. open follow-ups (if any)

**Files:** All three files (modified).

## Test strategy

Documentation-only WP. Validation is by:
- `bin/check-no-secrets` (any committed paths)
- `bin/check-composer-policy` (composer.json untouched at this stage)
- Manual review: charter sections render correctly in mkdocs / GitHub Markdown
- `tools/drift-detector.sh` should report no new drift after this WP lands

## Definition of Done

- [ ] Charter §3.2.10 amendment landed with exact ADR 015 wording
- [ ] Charter §5.9 (listing surface) + §5.10 (cache tag/context surface) sections exist with all surfaces enumerated
- [ ] `docs/cookbook/listing-first-cut.md` exists and matches the surface as shipped
- [ ] `docs/conventions/cache-tags-and-contexts.md` exists with full vocabulary + extension guidance
- [ ] CLAUDE.md orchestration row + Layer 3 list entry added
- [ ] CHANGELOG.md `[Unreleased]` bullets added (3 entries)
- [ ] `docs/public-surface-map.{php,md}` registers every new public surface
- [ ] `docs/specs/listing-pipeline-v1.md` carries a post-mortem stamp
- [ ] `tools/drift-detector.sh` reports no new drift
- [ ] `bin/check-composer-policy` + `bin/check-package-layers` + `bin/check-no-secrets` green

## Risks

| Risk | Mitigation |
|---|---|
| Charter section numbers conflict (e.g., another mission landed §5.9 first) | Check current state of `stability-charter.md` at implementation time; bump if needed |
| Cookbook drift from actual shipped surface | Adapt directly from quickstart.md and verify against the live implementation post-WP11 |
| public-surface-map.php enforcement test fails on new entries | Test runs at CI; missing entries cause test failure — comprehensive list in T057 + T058 |
| Charter wording quotes ADR 015 imprecisely | Use direct quote from ADR 015 §Consequences for criterion 10 |
| Post-mortem squash SHA unknown at WP12 implementation time | Leave a `<SHA TBD>` placeholder; M-007 mission-close-out (a separate session like the M-001..M-006 close-outs) fills it via a follow-up commit |

## Reviewer guidance

- Verify charter additions use exact ADR 015 wording for criterion 10.
- Verify §5.9 + §5.10 surface enumeration matches spec §6 (cross-check every entry).
- Verify cookbook is production-grade prose (not quickstart-flavor — drop mission spec cross-refs, keep code examples).
- Verify CLAUDE.md orchestration row is alphabetically positioned within Layer 3.
- Verify CHANGELOG bullets follow the project's Keep-a-Changelog format conventions (existing bullets in the file are the template).
- Verify drift detector still reports green (no orphaned specs introduced).

## Implementation command

```bash
spec-kitty agent action implement WP12 --agent <name>
```
