# Mission spec: 1335-layer-coverage-completion

**Charter:** Drive coverage symbol gaps to zero across L0-L6 and complete PHPStan neon package coverage in one mechanical sweep. Enforce zero-regression via a CI gate.

**Milestone:** Track 1 + Track 3

**Origin:** Pass 1 architect-mode triage (2026-04-30). Mission absorbs 9 closed issues spanning per-layer audit backlogs (#1335-#1338, #1341, #1343, #1344) and PHPStan neon completeness (#1340, #1342).

**Decomposition artifact:** `decomposition.md` in this directory.

---

## Decision: layer-by-layer + tooling + gate (9 WPs)

Work is largely mechanical and parallel. No architectural contract surfaces. Three convention choices need ratification (below). Each layer WP is independently verifiable via `php tools/audit/GenerateLayerAudit.php N`.

WP roster summary (full per-WP detail in `tasks.md`):

| WP | Layer / scope | Symbols | Issues |
|----|---------------|--------:|--------|
| WP02 | L0 foundation coverage | 269 | #1338 |
| WP03 | L1 core data coverage | 177 | #1337 |
| WP04 | L2 content types coverage | 42 | #1336 |
| WP05 | L3 services coverage | 21 | #1335 |
| WP06 | L4 API coverage | 64 | #1344 |
| WP07 | L5 AI coverage | 70 | #1341 |
| WP08 | L6 interfaces coverage | 226 | #1343 |
| WP09 | phpstan-neon + audit-tool modernization | n/a | #1340, #1342 |
| WP10 | coverage CI gate | n/a | mission-level |

Sequencing: WP09 first (defines "covered"). WP02-WP08 fully parallel after WP09 merges. WP10 last (otherwise it blocks parallel layer work).

---

## Three ratified conventions (approved 2026-04-30)

These are not architectural contracts. They are convention choices that lock in how the work is done. **Status: ratified.** They are now repo-wide rules.

### Convention C1: `#[CoversClass]` attribute over `@covers` PHPDoc — RATIFIED (Path A)

The current `tools/audit/GenerateLayerAudit.php` counts only PHPDoc `@covers` blocks. Modern PHPUnit 10.5+ idiom (and existing project convention per CLAUDE.md) uses `#[CoversClass]`, `#[Test]`, `#[CoversNothing]` attributes.

**Decision: Path A.** Modernize the audit tool to count `#[CoversClass]` attributes. Drive attribute coverage to zero. PHPDoc `@covers` permitted only where attributes cannot reach. Aligns the audit with the codebase's modern stance.

### Convention C2: `@internal` as the sole exemption mechanism — RATIFIED

Public symbols that must not be covered (truly-internal helpers, deprecated shims) are marked `@internal`. No free-form rationale files, no `phpcs:disable`-style escape hatches. Audit tool reads `@internal` as the only allowlist.

### Convention C3: PHPStan neon exclusion policy — RATIFIED

Not all L6 packages belong in `phpstan.neon`. `admin` is a Nuxt SPA, not PHP. Exclusion is documented inline as a comment in `phpstan.neon` per excluded package. Audit tool respects the inline comment.

---

## Drift flag (verify before WP09 starts)

Issue #1342 listed **7** missing L6 packages in `phpstan.neon`. Live file inspection (2026-04-30) shows only **2** actually missing: `admin` (intentionally excluded per Convention C3) and `deployer` (real omission). Use the live `phpstan.neon` as ground truth, not the issue body. WP09 spec-lock should re-derive the missing list from current state.

---

## Acceptance

The mission accepts when ALL of:

1. Audit tool reports 0 missing coverage symbols across L0-L6 (definition per Convention C1).
2. Every active framework PHP package is enumerated in `phpstan.neon` or has an inline exclusion comment per Convention C3.
3. CI fails any PR that introduces new uncovered public symbols (CI gate from WP10, routed through `composer verify` once architectural-remediation WP09 lands it).
4. `@internal` is the canonical exemption (Convention C2); no orphan rationale files remain.
5. `tools/audit/GenerateLayerAudit.php` is updated per Convention C1.

---

## Risks

1. **Path A modernization may surface previously hidden gaps** in classes already attributed but not counted by the old tool. Cost is small; audit gives fast feedback.
2. **L0 foundation (269 symbols) is the long pole.** Most symbols and most-depended-on. If any layer needs to land alone, WP02 first; otherwise parallel.
3. **CI gate landed mid-flight blocks unrelated work.** Park WP10 until layer WPs merge.
