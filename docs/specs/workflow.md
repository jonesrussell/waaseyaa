# Workflow governance (Spec Kitty–first)

**Planning and execution** for substantive work are driven by **[Spec Kitty](https://github.com/Priivacy-ai/spec-kitty)** — missions, work packages, `spec-kitty next`, the dashboard, and `.kittify/` artifacts — not by GitHub issues alone. **`docs/specs/`** remains the contract layer agents read from disk.

**GitHub** stays the **integration and visibility surface**: pull requests, Actions, releases, security, fork/contributor discovery, and **optional** issues (including M11 governed-change filings). CI and merge reality still live on GitHub; Spec Kitty does not replace the PR or the pipeline.

## Versioning Model

Framework **revision identity** (monorepo Git SHA vs split `waaseyaa/*` packages, golden SHA for apps, `bin/waaseyaa-version`) is documented in [version-provenance.md](./version-provenance.md). Root `composer.json` `"version"` in the monorepo is not a published semver line.

**Per-site consumer audits** (repeatable convergence checklist, artifact location, roster order): [per-site-convergence-audit.md](./per-site-convergence-audit.md).

The Waaseyaa Framework and Minoo (the flagship consumer app) version independently.

- **Framework versions** represent platform contract stability (ingestion envelope, schema registry, ACL substrate, operator diagnostics, CI gates).
- **App versions** (Minoo etc.) represent product feature maturity.
- The framework is the platform; apps are consumers. App versioning is constrained by framework releases, not the reverse.
- The framework passed v1.0 after platform contracts (ingestion envelope, schema registry, ACL, versioning, CI gates) were stabilized through v0.7–v0.12. Post-v1.0 milestones follow semantic intent: minor versions add capabilities (search, revisions, workspaces), v2.0 introduces breaking schema changes.

## Framework Milestones

| Milestone | Description | Status |
|-----------|-------------|--------|
| v0.7 | SSR path templates stabilized; Admin SPA critical bugs resolved; app developer experience unblocked | Closed |
| v0.8 | Default content type (core.note), boot enforcement, ACL baseline, CI versioning gates — platform contracts begin | Closed |
| v0.9 | Ingestion envelope, schema registry, namespace rules, RBAC, telemetry, operator diagnostics, onboarding guardrails | Closed |
| v0.10 | Feature flags, tenant migration plan — contract evolution and rollout safety finalized before v1.0 lock | Closed |
| v0.11 | Ingestion pipeline defaults — envelope schema, validation, error format, logging, CI enforcement | Closed |
| v0.12 | Operator diagnostics & health — CLI health commands, runtime diagnostics, schema drift detection, ingestion health | Closed |
| v1.0 | Platform contracts locked — ingestion, schema registry, ACL, versioning, CI stable | Closed |
| v1.1 | Post-v1.0 stabilization and cleanup | Closed |
| v1.2 | Continued stabilization | Closed |
| v1.3 | GraphQL & cleanup | Closed |
| v1.4 | Remove database-legacy & unify under DBAL | Closed |
| v1.5 | Admin Surface Completion — complete admin-surface package: controllers, host contract, catalog API | Open |
| v1.6 | Search Provider — implement concrete `SearchProviderInterface` (SQLite FTS5); independent with no milestone dependencies | Open |
| v1.7 | Revision System — implement `RevisionableInterface` + `RevisionableStorageInterface`; depends on: v1.4 (DBAL unification) | Open |
| v1.8 | Projects & Workspaces — framework-level project/workspace model and kernel isolation boundaries; depends on: v1.4 (DBAL unification) | Open |
| v1.9 | Production Queue Backend — add Redis or database-backed queue driver for production async | Open |
| v2.0 | Schema Evolution — auto-ALTER tables on field definition changes and generate migrations; depends on: v1.7 (Revision System) | Open |

**Update this table whenever milestones are added, closed, or redescribed.**

## GitHub milestone tracks (mirror for issues)

When a **GitHub issue** exists (community visibility, Dependabot, M11 templates, or contributor preference), assign it to **one** of the **five Track milestones** (*Track 1* … *Track 5* — not separate `v1.5` … `v2.0` titles on GitHub). That keeps the issue board consistent for anyone browsing GitHub only. The semantic table above remains the **capability narrative**; Tracks are a **slice label** for issues, parallel to how Spec Kitty missions group work.

| GitHub milestone | Primary focus | Typical semantic alignment |
|------------------|---------------|----------------------------|
| Track 1 — Entity system & hydration | Core platform, entity stack, foundation, package discovery | v1.4 outcomes, entity/storage contracts, L0–L3 depth |
| Track 2 — Bimaaji & agentic | Bimaaji, AI packages, agent-facing APIs | v1.5–v1.6 adjacent surfaces, L4–L5 |
| Track 3 — Parity & performance | CI parity, coverage, PHPStan, DX, performance | Cross-cutting quality; supports all semantic versions |
| Track 4 — Schema evolution | Schema and migration evolution workstreams | v2.0 preparatory work |
| Track 5 — Ecosystem identity | Split/release, tokens, docs/tooling, northcloud ecosystem | Governance, packaging, consumer rollout |

**Dependabot and dependency PRs:** Open **issues** (including bots) should still carry a Track milestone when they exist as issues. **Pull requests** that only bump dependencies may omit `(#N)` / mission reference in the title when there is no tracking artifact; if there is a chore or security issue or Spec Kitty WP, link it per rule #4.

**Periodic audit:** After large roadmap changes, reconcile GitHub issue hygiene if you use issues — see [docs/audits/2026-04-25-github-milestones-issues-audit.md](../audits/2026-04-25-github-milestones-issues-audit.md) for an inventory template.

## Milestone Narrative Arc

**Pre-v1 (platform foundation):**
- v0.7 — make the platform usable
- v0.8 — define the platform contract
- v0.9 — expand the platform contract (tenant onboarding, security)
- v0.10 — polish the admin experience
- v0.11 — ingestion pipeline foundation
- v0.12 — operator diagnostics and health

**v1.x (platform capabilities):**
- v1.0 — lock the platform contract
- v1.1–v1.3 — stabilization, GraphQL, cleanup
- v1.4 — unify storage under DBAL
- v1.5 — complete the admin surface
- v1.6 — add search (SQLite FTS5)
- v1.7 — add revision tracking
- v1.8 — multi-project/workspace support
- v1.9 — production-grade queue backend

**v2.x (breaking changes):**
- v2.0 — automatic schema evolution (field-definition diffing, migration generation)

## The 5 Workflow Rules

### 1. Substantive work begins in Spec Kitty
Do not drive multi-step implementation from a blank prompt. Use an **active Spec Kitty mission and work package** (or the next step from `spec-kitty next`) so intent, review gates, and merge discipline stay in `.kittify/` and the mission state machine. **M11 governed-change** and similar templates that require a **GitHub filing issue** still use that issue as the audit front door — link it from the mission or PR body so traceability stays intact.

### 2. GitHub issues (when they exist) belong to a Track milestone
If an issue is open on GitHub, assign **exactly one** Track milestone. Unassigned issues are incomplete triage for **that surface**. Use `bin/check-milestones` to surface gaps. Omitting GitHub issues entirely for Spec Kitty–only work is allowed; do not force an issue if the mission alone is sufficient for your slice.

### 3. Roadmap intent is semantic + mission state
The **Framework milestones** table and narrative in this document describe **capability intent** (v1.x / v2.0). **Spec Kitty mission structure** is the primary execution map for agents. **GitHub Track 1–5** names mirror rough themes for issue readers; they do not override mission ordering.

### 4. PRs must be traceable
Every PR must link **what it delivers**: prefer `feat(#N): …` when a GitHub issue exists; otherwise reference the **Spec Kitty mission / work package** (title, path under `.kittify/`, or link) in the title or body. Use `.github/pull_request_template.md`. Dependency-only PRs may follow the Dependabot exception above.

### 5. Read mission context before generating work
At session start, prefer **Spec Kitty** context (`spec-kitty next`, dashboard, active WP) when the repo is under a mission. **`bin/check-milestones`** remains a **supplementary** GitHub hygiene signal (SessionStart hook): read it when work touches GitHub issues or community triage.

## Drift Detection

**Specs:** `tools/drift-detector.sh` and manual reads of `docs/specs/` — see [ops/observability/drift-detection.md](../../ops/observability/drift-detection.md).

**GitHub issue hygiene:** `bin/check-milestones` (SessionStart hook) reports:
- Open issues with no milestone (incomplete triage **on GitHub**)
- Open milestones with no open issues (possibly stale **on GitHub**)

The script exits 0 always. Output is a warning surface, not a CI gate. It does **not** replace Spec Kitty mission state or spec ownership.

## Composer Manifest Policy (Codified + Gated)

`composer.json` consistency is a hard policy enforced by `bin/check-composer-policy` in hooks and CI.

Policy rules:

1. `config.sort-packages` is required and must be `true` in all first-party `composer.json` manifests.
2. `@dev` constraints for `waaseyaa/*` are allowed only in root `composer.json` (monorepo local development aggregator with path repositories).
3. Wildcard constraints for internal `waaseyaa/*` packages are forbidden (for example `*`).
4. `waaseyaa/core` must keep optional observability/dev packages (`waaseyaa/debug`, `waaseyaa/telescope`, `waaseyaa/testing`) out of `require`; they belong in `suggest`.

## Release Tag Parity

Release tags must split to every package repo that is represented under `packages/*/composer.json`.

- Guard script: `bin/check-release-tag-parity`
- Primary enforcement: `.github/workflows/split.yml` — `verify-tag-parity` after the split matrix, then `publish-github-release` (so parity always runs before the monorepo GitHub Release exists)
- Recovery / backfill: `.github/workflows/github-release.yml` (`workflow_dispatch` only; optional parity preflight + release for an existing tag)

This prevents publishing a framework tag where a required split package tag is missing (the failure class that left consumers unable to resolve `waaseyaa/core` when one required package had not been published).

Failure format is machine- and human-readable, including:
- file path
- violated rule id
- current value
- expected value

The top-level M11 post-execution governance baseline is [m11-post-execution-governance-bootstrap.md](./m11-post-execution-governance-bootstrap.md). Governed changes enter that loop through [the governed-change issue template](../../.github/ISSUE_TEMPLATE/m11-governed-change.md) (GitHub as **audit front door**); link the filing issue from the active **Spec Kitty** mission or PR when both exist. This workflow spec is the repo-local backlink to that artifact. The operating loop itself is [m11-steady-state-conformance-loop.md](./m11-steady-state-conformance-loop.md), and steady-state drift scans and C17+ logging use [m11-periodic-drift-scan-protocol.md](./m11-periodic-drift-scan-protocol.md) and the [M11 drift-scan log issue template](../../.github/ISSUE_TEMPLATE/m11-drift-scan-log.md).
