# Admin SPA Envelope Re-shape & Build Pipeline

**Mission**: `admin-spa-envelope-reshape-01KRJ9ZE`
**Mission ID**: `01KRJ9ZE3KW2KHH5J1442AAH12`
**Mission type**: software-dev
**Target branch**: `main`
**Tracking issue**: [#1412](https://github.com/waaseyaa/framework/issues/1412)
**Source audit**: [`docs/audits/admin-spa-modernization-2026-05-10.md#m2`](../../docs/audits/admin-spa-modernization-2026-05-10.md#m2)
**Closes audit entries**: E-Pkg-01, E-Pkg-02, E-Pkg-03, E-Pkg-04, E-Pkg-06, E-Docs-01
**Pre-resolved at specify-time**: E-Pkg-05 was already closed in the audit (CI gate exists at `.github/workflows/admin.yml:22-78`)

## Background

The admin SPA package (`packages/admin/`) is the only JS-only package in a PHP monorepo. Its `package.json` currently advertises a contracts/adapters distribution shape via an `exports` map (`./` → `dist/contracts/index.{d.ts,js}`, `./adapters` → `dist/adapters/index.{d.ts,js}`), but no `dist/` directory is committed and audit grep across `waaseyaa/framework` and `waaseyaa.org` found **zero** consumers importing `@waaseyaa/admin`. The 21-line README is also drastically out of sync with the comprehensive `docs/specs/admin-spa.md` and gives no usable summary to a reader landing on the package.

The maintainer has decided (2026-05-13):

1. **Distribution model**: PRIVATE APP. The admin SPA is an application, not a library. Mark `private: true`, remove the `exports` map and the `dist/` references, and do not add `peerDependencies`.
2. **Monorepo shape**: STATUS QUO. Keep the package as the only JS-only sibling in the PHP monorepo. No pre-built tarball model. PR #1350 (manual dist rebuild) becomes obsolete once the exports map is gone.
3. **README**: 50–80 line publishable summary covering bootstrap contract, AdminSurface, codified-context telemetry, auth phase 2, build commands, and deployment notes.

## User Scenarios & Testing

### Primary user: framework consumer / packager

**Scenario 1** — A developer running `composer require waaseyaa/framework` in a new Waaseyaa app receives the admin SPA as a server-served Nuxt build via `admin-surface`. They do NOT run `npm install @waaseyaa/admin` directly. The `exports` map in `packages/admin/package.json` is therefore a load-bearing lie: it implies importable contracts that do not exist on disk and that no consumer asks for.
- **Expected after M2**: `package.json` is marked `private: true`, the `exports` map is removed, and no broken file references remain. Running `npm install` in `packages/admin/` still produces a runnable Nuxt app.

**Scenario 2** — A new contributor opens `packages/admin/README.md` to understand what the package is, how to develop it, and how it deploys. Today they see 21 lines that mostly defer to docs.
- **Expected after M2**: README is 50–80 lines, gives a complete enough mental model to start dev, points at `docs/specs/admin-spa.md` for the full contract, and lists the actual build/dev commands.

### Primary user: CI / release pipeline

**Scenario 3** — CI runs `.github/workflows/admin.yml` on every push touching `packages/admin/`. The `admin/contracts` job runs `nuxi typecheck`, `npm run build:contracts`, validates `contracts/bootstrap.schema.json`, runs `vitest`. M2 must not break this gate.
- **Expected after M2**: CI gate is intact and continues to pass. `build:contracts` still emits `dist/contracts/` as a verification artifact (uploaded by CI, gitignored locally) — it just no longer pretends to be a published export.

### Edge cases

- A future contributor who genuinely needs to publish admin contracts to npm later can revisit this decision; M2 does not commit us to never-publishing, it just removes the misleading shape today.
- `tsconfig.contracts.json` may still need to compile to `dist/` for the CI verification artifact. The compiler is unchanged; only the package manifest's claims about that output change.
- PR #1350 is a manual dist rebuild; after M2, the dist serving moves entirely through the normal deploy flow (Caddy + admin-surface public directory). PR #1350 will be closed as obsolete or rebased if any remaining piece is still useful.

## Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | `packages/admin/package.json` MUST declare `"private": true` to prevent accidental publication. | accepted |
| FR-002 | `packages/admin/package.json` MUST NOT contain an `"exports"` map that references non-existent `dist/contracts/` or `dist/adapters/` artifacts. The map MUST be removed entirely (not stubbed out). | accepted |
| FR-003 | `packages/admin/package.json` MUST NOT contain `"main"` or `"types"` top-level fields that point at `dist/` paths. These MUST be removed. | accepted |
| FR-004 | `packages/admin/package.json` MUST declare `"engines": { "node": ">=22.0.0" }` (matching the project's documented Node baseline). | accepted |
| FR-005 | `packages/admin/package.json` MUST NOT introduce `"peerDependencies"` for `vue`, `nuxt`, or any other framework dependency. | accepted |
| FR-006 | `packages/admin/package.json` MUST retain the existing `build:contracts` script unchanged in behavior, so the existing CI gate (`.github/workflows/admin.yml`) continues to verify it. | accepted |
| FR-007 | `packages/admin/.gitignore` MUST continue to exclude `dist/`. M2 MUST verify this and not regress it. | accepted |
| FR-008 | `packages/admin/README.md` MUST be rewritten to a 50–80 line publishable summary covering: Overview, Architecture (PHP host + Nuxt SPA), Bootstrap contract, AdminSurface PHP runtime, Codified-context telemetry, Auth Phase 2, Build & dev commands, Deployment notes, Pointer to `docs/specs/admin-spa.md` for the full contract. | accepted |
| FR-009 | `docs/specs/admin-spa.md` MUST be updated to reflect the private-app distribution shape: remove or revise any section that implies consumers import `@waaseyaa/admin` contracts. Add a brief note that `build:contracts` is a CI verification artifact, not a published export. | accepted |
| FR-010 | `docs/audits/admin-spa-modernization-2026-05-10.md` MUST be updated to mark E-Pkg-01..04, E-Pkg-06, and E-Docs-01 as **CLOSED** with a citation to the M2 merge commit and PR. §4.6 monorepo-shape MUST record "status quo (option 1) adopted". | accepted |
| FR-011 | PR #1350 MUST be reconciled: the implementer evaluates whether it should be closed (dist serving moves into the normal deploy flow) or kept (if a justified use case remains). The decision and rationale MUST be recorded in the mission's WP findings and applied (close or rebase as needed). | accepted |
| FR-012 | After M2 lands, `rg "@waaseyaa/admin" --type ts --type js --type json -g '!**/node_modules/**' -g '!**/package-lock.json'` across `waaseyaa/framework` and `waaseyaa.org` MUST return zero import statements (existing zero, just verified). | accepted |
| FR-013 | The GitHub tracking issue [#1412](https://github.com/waaseyaa/framework/issues/1412) MUST be closed by the merging PR via a `Closes #1412` footer. | accepted |

## Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|---|---|---|---|
| NFR-001 | The `packages/admin/` test suite (Vitest + Playwright) MUST continue to pass after M2 changes. | `npm test` exit 0; `npm run test:e2e` exit 0 against a `nuxt dev` server. | accepted |
| NFR-002 | The Nuxt build MUST continue to succeed after M2 changes. | `npm run build` exit 0; produced `.output/` directory present. | accepted |
| NFR-003 | The `build:contracts` script MUST continue to succeed and emit `dist/contracts/index.{d.ts,js}` for CI verification (even though the package no longer claims it as an export). | `npm run build:contracts` exit 0; `dist/contracts/index.d.ts` exists post-build. | accepted |
| NFR-004 | The CI workflow `.github/workflows/admin.yml` MUST run green on the M2 PR with no workflow file changes required. | All jobs green on first push after M2 changes. | accepted |
| NFR-005 | The new README MUST be 50–80 lines (inclusive). Markdown MUST be lint-clean against the project's existing markdown conventions. | `wc -l packages/admin/README.md` returns a value in `[50, 80]`. | accepted |
| NFR-006 | The mission MUST land as a single PR (or a small number of well-sequenced PRs) within 1 week of mission start. | Calendar elapsed ≤ 7 days from mission `created_at` to merge. | accepted |

## Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | NO source code change inside `packages/admin/app/`. M2 is envelope-only; component, composable, plugin, and route logic is out of scope. | accepted |
| C-002 | NO dependency version bumps in `packages/admin/package.json` (those belong to M1, [#1411](https://github.com/waaseyaa/framework/issues/1411)). | accepted |
| C-003 | NO adoption of `@nuxt/image` or `@nuxt/fonts` modules (deferred per M1B investigation; admin SPA has zero `<img>` tags and zero web fonts to justify them). | accepted |
| C-004 | NO bundle / tenancy work (M3 territory, [#1413](https://github.com/waaseyaa/framework/issues/1413)). | accepted |
| C-005 | NO new admin surfaces for workflows, queue, scheduler, notification (M4 territory). | accepted |
| C-006 | NO AI / agentic admin surfaces (M5 territory). | accepted |
| C-007 | NO PHP-side changes to `packages/admin-surface/` unless directly required by the envelope reshape (e.g. a referenced path needs updating). Default expectation: zero PHP changes. | accepted |
| C-008 | The CI workflow file `.github/workflows/admin.yml` MUST NOT be modified by M2 (we verify the existing gate; we do not rewrite it). Exception: a comment-only edit clarifying that `build:contracts` produces a verification artifact is permitted. | accepted |
| C-009 | The mission MUST adhere to Waaseyaa workflow rules: every PR carries `#1412` traceability and a Spec Kitty mission reference; CHANGELOG `[Unreleased]` gets a release-notes bullet. | accepted |

## Success Criteria

1. **Honest envelope**: `packages/admin/package.json` accurately describes what the package is (a private monorepo-internal application), with no aspirational claims about exports that do not exist.
2. **Publishable README**: a new contributor reading `packages/admin/README.md` can understand purpose, architecture, dev workflow, and deployment in under five minutes without leaving the README except to follow the explicit spec pointer.
3. **CI regression-safe**: all existing CI jobs continue to pass; no manual intervention required.
4. **Spec coherence**: `docs/specs/admin-spa.md` and `docs/audits/admin-spa-modernization-2026-05-10.md` agree with the new envelope shape; no stale claims about distribution remain.
5. **Audit closure**: E-Pkg-01..04, E-Pkg-06, E-Docs-01 are marked closed in the audit doc with traceable commit / PR citations.
6. **YAGNI verified, not assumed**: a fresh `rg "@waaseyaa/admin"` across both repos confirms zero importers, validating the private-app decision in writing.
7. **#1412 closed**: GitHub issue #1412 is closed by the merging PR; Track 1 milestone gains a completed item.

## Key Entities

| Entity | Description |
|---|---|
| `packages/admin/package.json` | The npm manifest for the admin SPA. The primary surface of M2 changes. |
| `packages/admin/README.md` | The package's human-readable summary. Rewritten in M2. |
| `packages/admin/tsconfig.contracts.json` | TS config that emits `dist/contracts/`. Touched only if its output references break after manifest changes. |
| `packages/admin/contracts/` and `packages/admin/contracts/adapters/` | Source TS for the contracts/adapters layer. Unchanged in M2 (envelope only). |
| `.github/workflows/admin.yml` | CI gate for the admin SPA package. Verified, not modified, in M2. |
| `docs/specs/admin-spa.md` | The canonical contract for the admin SPA. Updated in M2 to reflect private-app shape. |
| `docs/audits/admin-spa-modernization-2026-05-10.md` | The audit document. Annotated in M2 to record audit-entry closure. |
| Pull Request [#1350](https://github.com/waaseyaa/framework/pull/1350) | Manual dist rebuild PR. Reconciled in M2 (close or rebase decision). |

## Assumptions

- The audit's E-Pkg-05 closure (CI gate exists in `.github/workflows/admin.yml:22-78`) is correct and current. M2 verifies this assumption but does not re-validate the gate's internal logic.
- The `build:contracts` script's value as a "can these contracts be emitted as standalone .d.ts" smoke test is worth keeping even without a published export. The audit makes this case explicitly and the maintainer accepts it.
- `engines.node >=22.0.0` is consistent with the project's documented Node baseline. If the actual deployed Node version is older, the implementer flags it as a discovery and adjusts before merge.
- No external Waaseyaa application is currently in development that would want to consume `@waaseyaa/admin` as a published npm package within the next ~6 months. If one appears, this decision can be reversed in a future mission without M2 leaving residue (the contracts source remains intact; only the manifest's claims change).
- `docs/specs/admin-spa.md` is the authoritative spec; updates to it during M2 do not require a separate spec-update mission.

## Dependencies

- **Required prior work**: none. M2 is independent of M1 (dependency bumps) and can ship in parallel.
- **Blocks**: M3 ([#1413](https://github.com/waaseyaa/framework/issues/1413)) does NOT strictly require M2, but M3 is easier to reason about when the envelope is honest.
- **Tooling**: `spec-kitty` 3.1.8+, `npm` (current), Node ≥ 22.

## Out of Scope

- All items listed under "Constraints" above (C-001..C-008).
- UX / visual polish of admin SPA (deferred per audit's "UX / Visual Polish — Deferred" section).
- Adopting a CSS framework (intentional per audit's E-Mod-02 "informational" classification).
- i18n module migration (E-Mod-03 deferred).
- Any work on Inertia, SSR, or alternative admin surface technologies.

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Removing the `exports` map silently breaks a tooling assumption inside the monorepo (e.g. a tsconfig path or Vite alias) | low | medium | Run `npm run build`, `npm run build:contracts`, `npm test`, `npm run test:e2e` locally before push. CI also re-runs these. |
| README rewrite drifts from `docs/specs/admin-spa.md` | medium | low | Reference spec sections verbatim where appropriate; treat README as a curated summary, not a re-statement. |
| `engines.node` introduces a CI matrix mismatch | low | low | Verify against `.github/workflows/admin.yml` Node version setup. Adjust if the workflow uses an older Node. |
| PR #1350 has merge conflicts or follow-on commits not captured | medium | low | Read the full PR diff and discussion before closing; if any artifact is still useful (e.g. a build script tweak), cherry-pick it into M2 before closing #1350. |
| The audit doc has additional E-Pkg / E-Docs entries beyond those listed that should also be closed | low | low | Re-read `docs/audits/admin-spa-modernization-2026-05-10.md` section 4 during plan phase; update FR-010 list if any entry was missed at specify-time. |

## Traceability

| Audit ID | Status target | M2 deliverable |
|---|---|---|
| E-Pkg-01 (exports map references missing dist/) | closed | FR-002, FR-003 |
| E-Pkg-02 (no engines constraint) | closed | FR-004 |
| E-Pkg-03 (no publishConfig / private) | closed | FR-001 |
| E-Pkg-04 (no peerDependencies) | closed (decision: not needed) | FR-005 |
| E-Pkg-05 (no CI step verifies build:contracts) | already closed in audit | FR-006 (verification only) |
| E-Pkg-06 (no known downstream consumer) | closed (YAGNI confirmed) | FR-012 |
| E-Docs-01 (README is 21 lines) | closed | FR-008 |
| §4.6 monorepo-shape recommendation | adopted (option 1: status quo) | FR-010 |
