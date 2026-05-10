# Admin SPA Modernization Audit

**Mission ID:** `01KRA3RV5GPMP178F2VMEX1TW1`
**Mission slug:** `admin-spa-modernization-audit-01KRA3RV`
**Mission type:** research
**Target branch:** `main`
**Created:** 2026-05-10

## Overview

The Waaseyaa admin SPA (`packages/admin`) has fallen behind the rest of the framework along multiple dimensions: framework-API contracts have moved (bundles, tenancy slot, attribute-first entities, cast-aware attributes, FieldDefinition invariants, work-surface), entire L2–L6 subsystems have shipped with no admin UI, and the package envelope itself (manifest, exports, dist/contracts build pipeline, README, Nuxt/TypeScript/Vitest/Playwright versions) is unmaintained. Stakeholders perceive the SPA as "severely out of date" but no inventory exists that turns that perception into a finite, sized backlog.

This is an **audit-only** mission. It produces a structured assessment document plus a set of milestone-tagged GitHub issues that scope the follow-up implementation missions. No code under `packages/admin/` and no backend code is modified by this mission.

## Intent Summary

Produce `docs/audits/admin-spa-modernization-2026-05-10.md` — a prioritized backlog covering four axes (framework alignment drift, feature coverage gaps, dependency/tooling staleness, package envelope/boundary) plus a short deferred-UX stub. The doc leads with a "Top 5 follow-up missions" section that names, sizes, orders, and links proposed follow-up missions. Each cited gap and follow-up mission is mirrored as a GitHub issue with the appropriate Track milestone so the backlog is queryable outside the audit file.

## User Scenarios & Testing

### Primary actors

- **Framework maintainer** deciding which admin SPA modernization mission to fund next.
- **Implementing agent** picking up a follow-up mission and needing concrete file pointers, commit citations, and sizing.
- **Reviewer** verifying that a follow-up mission's scope matches an audited gap.

### Acceptance scenarios

1. A maintainer opens `docs/audits/admin-spa-modernization-2026-05-10.md`, reads the Top 5 follow-up missions, and can sequence the next quarter of admin SPA work without re-doing the research.
2. An implementing agent picks any single gap from any of the four axes and can act on it without further investigation: the entry cites at least one commit/issue, names specific file paths in `packages/admin/`, classifies the gap (broken / degraded / unsurfaced / no-op / missing-UI / stale-dep / envelope-defect), and carries an XS/S/M/L size.
3. A reviewer cross-references a follow-up GitHub issue against the audit doc and finds a one-to-one match between issue scope and the audited entry that spawned it.
4. The audit reflects the **full v1.x lifetime** of changes to the relevant backend packages, not only recent commits.

### Edge cases

- A gap is already partially addressed in flight on another branch → entry must note "in-flight" and link the open PR/mission.
- A backend mission's admin-side impact is genuinely no-op → still listed with classification `no-op` so future readers know it was considered.
- A subsystem package exists but is itself stub/incomplete in the framework → listed under coverage gaps with a note that admin UI is blocked on the backend maturing.
- Two gaps logically merge into a single follow-up mission → audit explicitly groups them under one proposed mission entry.

## Requirements

### Functional Requirements

| ID | Status | Requirement |
|----|--------|-------------|
| FR-001 | Required | The audit must produce `docs/audits/admin-spa-modernization-2026-05-10.md` committed to the mission worktree. |
| FR-002 | Required | The audit document must contain four enumerated axis sections in this order: (1) Framework Alignment Drift, (2) Feature Coverage Gaps, (3) Dependency / Tooling Staleness, (4) Package Envelope / Boundary. |
| FR-003 | Required | The audit document must include a leading "Top 5 Follow-up Missions" section that names each proposed mission, sizes it XS/S/M/L, declares an ordering, and notes inter-mission dependencies. |
| FR-004 | Required | Section 1 (Drift) must cover the full v1.x lifetime of commits touching `packages/{entity, entity-storage, field, api, access, auth, routing, user, config, telescope}` and `packages/foundation/src/Http/`. |
| FR-005 | Required | Each drift entry must cite at least one commit hash or issue/PR number, name affected admin SPA file paths, carry one of `{broken, degraded, unsurfaced, no-op}`, and a size in `{XS, S, M, L}`. |
| FR-006 | Required | Section 2 (Coverage) must walk every subsystem in the CLAUDE.md orchestration table and classify it as `no-UI`, `minimal-UI`, or `complete-UI`. |
| FR-007 | Required | Each `no-UI` or `minimal-UI` coverage entry must include a one-paragraph proposed admin surface description and a size in `{XS, S, M, L}`. |
| FR-008 | Required | Section 3 (Tooling) must audit current versus latest stable versions for at minimum: Nuxt, Vue, vue-router, TypeScript, Vitest, Playwright, `@nuxt/test-utils`, `vue-tsc`, and Node engine targets. |
| FR-009 | Required | Section 3 must enumerate deprecation warnings observed during `nuxt dev`/`nuxt build`, lint or typecheck gaps, and test-coverage gaps in `packages/admin/tests/` and `packages/admin/e2e/`. |
| FR-010 | Required | Section 4 (Envelope) must audit `packages/admin/package.json` (exports map, files array, publish posture, peer/engine deps), `nuxt.config.ts` (modules, runtime config vs backend env contract, proxy rules), `tsconfig.json` plus `tsconfig.contracts.json`, the `dist/contracts/` build pipeline and its downstream consumers, README freshness, directory structure, Playwright/Vitest config modernity, and a recommendation on whether the package's current shape (only JS-only package in a PHP monorepo) still makes sense. |
| FR-011 | Required | The audit must include a short "UX / Visual Polish — Deferred" stub section explicitly out of scope for this audit, with a one-paragraph pointer to where UX work would slot in later. |
| FR-012 | Required | For every entry listed in any of the four axes and for every proposed follow-up mission in the Top 5 section, a GitHub issue must be created and linked from the audit doc. |
| FR-013 | Required | Every GitHub issue created by this mission must be assigned to an active Track milestone (per `bin/check-milestones`). |
| FR-014 | Required | The audit document must include an explicit "Out of Scope" section listing what this mission deliberately does not do: any code change in `packages/admin/`, any backend code change, any execution of follow-up missions, UX/visual polish work. |
| FR-015 | Required | The audit must record any in-flight or already-merged work that overlaps with audited gaps and link the relevant PR or mission. |

### Non-Functional Requirements

| ID | Status | Requirement | Threshold |
|----|--------|-------------|-----------|
| NFR-001 | Required | Drift inventory completeness | At least 90% of commits in the v1.x window touching the listed backend packages are classified (the remainder may be batch-classified as `no-op` with a single-line rationale). |
| NFR-002 | Required | Coverage walk completeness | 100% of subsystems listed in the CLAUDE.md orchestration table appear in Section 2 with a classification. |
| NFR-003 | Required | Citation density | Every drift and coverage entry carries at least one commit/issue/PR/file-path citation. Zero unsupported assertions. |
| NFR-004 | Required | Sizing discipline | Every actionable entry (drift, coverage, tooling, envelope, follow-up mission) carries an XS/S/M/L size. Use a single sizing rubric defined at the top of the doc. |
| NFR-005 | Required | Issue–audit traceability | Every GitHub issue links back to the audit doc anchor that spawned it, and every audit entry references its issue number once filed. Bidirectional. |
| NFR-006 | Required | Document readability | The audit is committee-readable: a maintainer should be able to pick the next mission to fund in under 10 minutes by reading only the Top 5 section. |

### Constraints

| ID | Status | Constraint |
|----|--------|------------|
| C-001 | Required | No file under `packages/admin/` may be modified by this mission. |
| C-002 | Required | No backend (`packages/*` excluding `packages/admin/`) source file may be modified by this mission. |
| C-003 | Required | The audit doc must live at `docs/audits/admin-spa-modernization-2026-05-10.md`. |
| C-004 | Required | The audit must classify the admin SPA package envelope using the same rubric as the drift and coverage sections (status + size). |
| C-005 | Required | Follow-up GitHub issues must be filed within this mission and not deferred to a later mission. |
| C-006 | Allowed | The audit may reference `packages/admin/` files by path and may quote short excerpts for citation purposes; this does not constitute modification. |

## Success Criteria

- **SC-001**: A reader unfamiliar with the admin SPA can sequence the next five admin SPA modernization missions in under 10 minutes using only the audit's Top 5 section.
- **SC-002**: 100% of orchestration-table subsystems appear in Section 2 with an explicit `no-UI` / `minimal-UI` / `complete-UI` classification.
- **SC-003**: At least 90% of in-window backend commits touching the listed packages are addressed in Section 1.
- **SC-004**: For every drift, coverage, tooling, and envelope entry there exists a corresponding GitHub issue with an active Track milestone, and the audit links to the issue.
- **SC-005**: An implementing agent picking up any follow-up mission from the Top 5 can begin work without re-doing audit research — the audit's citations and file pointers suffice.
- **SC-006**: Reviewers can independently verify any drift entry by following its commit/issue citation and the named admin SPA file path(s).

## Key Entities

- **Drift entry**: backend change × admin SPA impact × classification × size × citation × proposed remedy.
- **Coverage entry**: subsystem × current UI status × proposed surface × size × dependencies.
- **Tooling finding**: dependency or config × current version/state × recommended version/state × deprecation/risk notes × size.
- **Envelope finding**: package-shell aspect (exports, dist, README, structure, monorepo posture) × current state × recommendation × size.
- **Follow-up mission**: name × scope summary × covered entries × size × ordering × dependencies on other follow-ups × tracking issue.
- **Tracking issue**: GitHub issue × Track milestone × backlink to audit anchor.

## Assumptions

- The full v1.x lifetime is interpreted as "all commits on `main` up to the audit date" — there is no pre-v1.0 admin SPA worth auditing.
- The CLAUDE.md orchestration table is the authoritative subsystem inventory; if a package exists outside that table it is still listed but flagged as orchestration-table-orphan.
- "Track" milestones refer to the two active milestones surfaced by `bin/check-milestones` (Track 1 — Entity system & hydration; Track 2 — Bimaaji & agentic). New follow-up issues attach to whichever Track best matches the gap; cross-cutting issues use Track 1 by default and note the cross-cut.
- Sizing rubric (defined in the audit doc): XS ≤ 0.5 day; S ≤ 2 days; M ≤ 1 week; L > 1 week (decomposition expected).
- The audit consumer is a maintainer or implementing agent, not an end-user; technology terms (Nuxt, vue-router, AdminSurface, dist/contracts) are appropriate.

## Out of Scope

- Any modification to `packages/admin/` source, config, dependencies, or build output.
- Any backend code change.
- Executing any of the follow-up missions identified by the audit.
- UX/visual design work, design-system maturity, or component-library polish (deferred — short stub only).
- Re-evaluating Waaseyaa's framework architecture or layer graph.
- Cross-project admin work outside `packages/admin/` (e.g. Claudriel UI).

## Dependencies

- Read access to git history for `packages/{entity, entity-storage, field, api, access, auth, routing, user, config, telescope, foundation}`.
- Read access to GitHub issues/PRs for cross-linking.
- `gh` CLI authenticated for issue creation.
- `bin/check-milestones` available for milestone validation.
- Current admin SPA source on `main` for file-path verification and citation.

## Branch Strategy

Current branch at workflow start: **main**. Planning/base branch for this feature: **main**. Completed changes must merge into **main**.
