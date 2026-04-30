# Decomposition — layer-coverage-completion

Date: 2026-04-30 (Pass 2 WP01 output)

Mission charter: "Drive PHPDoc `@covers` to zero across L0-L6 and complete PHPStan neon package coverage in one mechanical sweep."

This is a tooling/coverage-hygiene mission, not an architecture mission. The decomposition reflects that: the work is large in volume but shallow in conceptual surface.

---

## Decision: WP shape

**Recommended: Option A — one WP per layer (7 PHPDoc WPs) + one cross-cutting WP for PHPStan neon completeness + one tooling/CI gate WP. Total: 9 WPs.**

Reasoning:

- **Layer-by-layer is the natural unit of work** because each layer's audit has its own generator (`tools/audit/GenerateLayerAudit.php N`), its own JSON map (`build/layerN-audit/symbol_test_map_layerN.json`), its own segment script for some layers, and its own zero-target finding ID (`L0-COV-AGG`..`L6-COV-AGG`). Each WP can run, verify, and merge independently, and a per-WP run of the audit generator is the obvious acceptance signal.
- **No cross-layer dependency surfaced in the issue bodies.** Each layer audit only checks symbols defined in that layer's packages against tests in that layer's tree. L1 PHPDoc work does not require L0 to be done first; the `@covers \FQCN` reference is a string and PHP autoload resolves it at PHPUnit run time, not at audit time. So WPs are parallelisable.
- **The two PHPStan neon issues (#1340, #1342) are inherently small and cross-cutting.** Bundling both into one WP keeps the `phpstan.neon` edits in a single review and one CI run, instead of leaking neon edits into two layer WPs that are otherwise pure docblock churn. (#1340 also carries a small PHP code fix — `cast.useless` in `AnomalyDetector.php` — which fits naturally with the neon WP since it's the same file PHPStan starts complaining about once the path is added.)
- **A small tooling WP at the end locks in the gate.** Without a CI check, this work regresses the day someone adds a new public class. A `bin/check-covers-coverage` script (or equivalent invocation of the existing audit tools) wired into `composer phpstan` or a new `composer check-covers` target turns the zero-state into a maintained invariant.

Option B (one big PHPDoc WP) was rejected because ~869 symbols across 60+ packages is not a reviewable unit. Option C (foundation/middle/top) was rejected because the boundaries are arbitrary and don't match the audit tool's per-layer outputs.

---

## Hidden contracts

The mission is **substantively mechanical**, but the issue bodies surface three contractual nuances that the spec should call out (none requires architectural negotiation; they are conventions to ratify):

1. **Audit tool counts PHPDoc `@covers \FQCN` only, not PHPUnit `#[CoversClass]`.** Every issue body restates this. The project mixes both styles in tests, but `CLAUDE.md` already mandates the attribute style (`#[Test]`, `#[CoversClass(...)]`, `#[CoversNothing]`) for new code. The spec must decide one of two stances:
   - **(a)** add the missing PHPDoc `@covers` lines to existing tests (matches the audit tool, but expands an old style the constitution discourages); or
   - **(b)** modernise: extend the audit tool to also accept `#[CoversClass]` attributes, then add attributes (or both) to drive the count to zero.
   The pragmatic choice for a "mechanical sweep" charter is (a) — it's closer to the line counts in the issues — but the spec must explicitly bless that and note the constitution divergence so future readers don't assume tests should be re-attribute-d. Either way, this is a **convention to ratify**, not invent.

2. **Acceptance allows `@internal` exemption.** Every issue body says: "count trends to 0, **or** symbols legitimately marked `@internal` / documented exemptions." This is an escape hatch for genuinely internal helpers. The spec should require an audit-side allowlist or a per-symbol `@internal` annotation, not a free-form "documented exemption" file, so the gate stays mechanical.

3. **L6 PHPStan inclusion is *not* universal.** Issue #1343 explicitly notes `admin` is "largely Nuxt; PHP `src/` under `packages/admin` may still need `@covers` where tests exist." Issue #1342 lists 7 L6 packages missing from `phpstan.neon`, but the **live `phpstan.neon` already includes `admin-surface`, `debug`, `genealogy`, `graphql`, `inertia`**. Only `admin` and `deployer` actually remain absent today. The acceptance criterion in #1342 already foresaw this: "or a documented, intentional exclusion list with reason — e.g. non-PHP SPA — and CI policy." The spec must record the exclusion policy explicitly (e.g., "`packages/admin/` is a Nuxt SPA; only its `src/` PHP shim, if any, is in scope") rather than blindly add all seven.

**Drift flag:** Issue #1342 states 7 missing L6 neon entries; live `phpstan.neon` shows only 2 truly missing (`admin`, `deployer`). The numeric scope is smaller than the issue body claims. Use the live file as ground truth; cite #1342 for finding IDs only.

**No new attribute, event, or interface is invented by this mission.** The "contracts" are conventions about how `@covers` is counted and which `phpstan.neon` paths exist. That is enough to write spec.md without fabricating an architectural surface.

---

## Work package roster

Symbol counts are taken from issue bodies as planning numbers; expect modest drift since issues closed (re-run the audit at WP start to baseline).

| WP | Slug | Layer(s) | Symbol target (issue) | Neon work | Member issues |
|----|------|----------|----------------------:|-----------|---------------|
| WP02 | `l0-foundation-covers` | L0 — Foundation (analytics, cache, foundation, geo, http-client, i18n, ingestion, mail, mercure, oauth-provider, plugin, queue, scheduler, state, typed-data, validation, error-handler, database-legacy, analytics) | 269 | none | #1338 |
| WP03 | `l1-core-data-covers` | L1 — Core Data (entity, entity-storage, access, user, config, field, auth, oidc, testing) | 177 | none | #1337 |
| WP04 | `l2-content-types-covers` | L2 — Content Types (node, taxonomy, media, path, menu, note, relationship, groups, engagement, messaging) | 42 | none | #1336 |
| WP05 | `l3-services-covers` | L3 — Services (workflows, search, seo, notification, billing, github, northcloud) | 21 | none | #1335 |
| WP06 | `l4-api-covers` | L4 — API (api, bimaaji, routing) | 64 | none | #1344 |
| WP07 | `l5-ai-covers` | L5 — AI (ai-agent, ai-observability, ai-pipeline, ai-schema, ai-vector) | 70 | none | #1341 |
| WP08 | `l6-interfaces-covers` | L6 — Interfaces (cli, admin-surface, graphql, mcp, ssr, genealogy, telescope, deployer, inertia, debug; admin = PHP shim only) | 226 | none | #1343 |
| WP09 | `phpstan-neon-completeness` | cross-cutting | 0 covers | yes — add `ai-observability` (already done; verify), add `admin` (decide scope or exclude with rationale), add `deployer`; document SPA exclusion policy; fix `cast.useless` in `packages/ai-observability/src/Analysis/AnomalyDetector.php:136` | #1340, #1342 |
| WP10 | `coverage-gate` | tooling/CI | n/a | n/a | (mission-level) |

**WP10 contents:** add `composer check-covers` (or fold into `composer phpstan` / a `composer ci` aggregate) that runs `php tools/audit/GenerateLayerAudit.php N` for N=0..6 and fails on any nonzero `*-COV-AGG`. Add it to the GitHub Actions workflow that already runs `composer phpstan` and `bin/check-package-layers`. Without WP10, the zero-state is not maintained.

Estimate: ~869 PHPDoc symbols total + ~3 neon edits + 1 PHP fix + 1 CI wiring. Volume is real but each unit is mechanical.

---

## Sequencing

**WP02–WP08 are independent and can run in parallel.** Each operates only on its own layer's tests; no cross-layer reference is created.

**WP09 (PHPStan neon) must precede WP10 (coverage gate)** so the neon CI step is green before a new gate is added. WP09 is independent of WP02–WP08.

**WP10 (coverage gate) is last.** It must merge after every layer hits zero so the gate doesn't block existing PRs. If a layer WP slips, WP10's gate would block other unrelated work; landing it last keeps the blast radius confined to this mission.

Recommended order: WP09 first (smallest, unblocks neon CI for downstream PRs), then WP02–WP08 in any order (parallelise across agents/sessions), then WP10.

---

## Tooling

Pre-work that should land alongside or in WP09 to make WP02–WP08 efficient:

- **Re-run all 7 audits and capture current counts.** Issue bodies cite numbers from when the issues were filed. Real numbers may have drifted (some L3 work happened mid-flight per #1335). Each layer WP should re-baseline at start.
- **Confirm `tools/audit/segment_lN_covers_by_package.php` exists for every layer.** Issue #1338 notes the L0 segment script is optional but missing; #1337 references the L1 one as present. Adding the missing segment scripts (L0, L2, L4, L5, L6) gives per-package buckets that make the layer WPs much more reviewable. Light tooling, fits in WP02 or its own sub-task.
- **Decide the `@covers` style stance up-front.** See "Hidden contracts" #1. The spec should pick (a) PHPDoc-style and freeze it, or (b) modernise the audit tool. If (b), the audit-tool change is shared infrastructure and belongs in WP09 alongside the neon work.

---

## Acceptance for the mission as a whole

The mission is done when **all** of the following hold:

1. `php tools/audit/GenerateLayerAudit.php N` reports `LN-COV-AGG = 0` (or only `@internal`-annotated / allowlisted symbols remain) for **N = 0, 1, 2, 3, 4, 5, 6**.
2. `phpstan.neon` `paths:` includes every package present in `bin/check-package-layers` **except** explicit, in-file-commented exclusions (e.g., `# admin: Nuxt SPA — PHP shim only`). The exclusion list is enumerable, finite, and rationale-bearing.
3. `composer phpstan` is green across the expanded path set; `cast.useless` in `AnomalyDetector.php:136` is fixed.
4. `CLAUDE.md` Layer 5 table lists `ai-observability`.
5. A CI gate (WP10) fails the build if any `LN-COV-AGG > 0` or if a new package appears in `bin/check-package-layers` without a corresponding `phpstan.neon` entry. The zero-state is now maintained, not just achieved.
6. All 9 absorbed GitHub issues remain closed; the merge commits/PRs cite this mission and the relevant issues per `docs/specs/workflow.md`.

If any acceptance item slips, leave the mission open rather than marking it done with carve-outs — partial completion of a mechanical sweep is exactly the kind of debt this mission exists to eliminate.
