# Tasks / work packages — 1335-layer-coverage-completion

WP01 was the decomposition phase (output: `decomposition.md`). WP02-WP10 follow.

## Work package roster

| WP | Slug | Layer / scope | Symbol count | Issues | Status |
|----|------|---------------|-------------:|--------|--------|
| WP01 | decomposition | n/a | n/a | n/a | done |
| WP02 | l0-foundation-covers | L0 foundation | 269 | #1338 | unscheduled |
| WP03 | l1-core-data-covers | L1 core data | 177 | #1337 | unscheduled |
| WP04 | l2-content-types-covers | L2 content types | 42 | #1336 | unscheduled |
| WP05 | l3-services-covers | L3 services | 21 | #1335 | unscheduled |
| WP06 | l4-api-covers | L4 API | 64 | #1344 | unscheduled |
| WP07 | l5-ai-covers | L5 AI | 70 | #1341 | unscheduled |
| WP08 | l6-interfaces-covers | L6 interfaces | 226 | #1343 | unscheduled |
| WP09 | phpstan-neon-completeness | tooling + neon | n/a | #1340, #1342 | unscheduled |
| WP10 | coverage-gate | CI enforcement | n/a | mission-level | unscheduled |

## Sequencing

- **WP09 first.** Modernizes the audit tool per Convention C1 and completes `phpstan.neon` per #1340 and #1342 (using live file as ground truth). Without this, layer WPs target a moving definition of "covered."
- **WP02-WP08 fully parallel** after WP09 merges. No inter-layer dependencies; each runs `php tools/audit/GenerateLayerAudit.php N` independently.
- **WP10 last.** Ratchet the CI gate in only after layer WPs merge. Landing mid-flight blocks parallel layer work.

## Per-WP scope and acceptance evidence

### WP02-WP08 — Per-layer coverage backlogs

**Scope.** Add `#[CoversClass]` for every uncovered public symbol in the layer's audit output. Mark truly-internal symbols `@internal` per Convention C2.

**Public contracts touched.** None at runtime; coverage attributes are testing metadata. Does formalize Convention C2 boundary.

**Acceptance evidence (per WP).** `php tools/audit/GenerateLayerAudit.php N` reports 0 missing symbols. Diff is mechanical; no behavior change.

### WP09 — phpstan-neon-completeness (#1340, #1342)

**Scope.**

1. Modernize `tools/audit/GenerateLayerAudit.php` to count `#[CoversClass]` attributes per Convention C1.
2. Add `deployer` to `phpstan.neon` (live file shows it missing).
3. Document the `admin` exclusion inline in `phpstan.neon` per Convention C3.
4. Sync any L5 AI packages missing from `phpstan.neon` per #1340.
5. Make the audit tool respect `@internal` per Convention C2.

**Public contracts touched.** Audit tool input format (Convention C1); `phpstan.neon` enumeration; `@internal` semantics.

**Acceptance evidence.** Audit tool counts attributes; `vendor/bin/phpstan analyse` runs over every active PHP package; inline exclusion comments in place.

### WP10 — coverage-gate

**Scope.** CI step that fails any PR introducing new uncovered public symbols. Routed through `composer verify` once architectural-remediation WP09 lands it; otherwise standalone in `.github/workflows/`.

**Public contracts touched.** CI policy.

**Acceptance evidence.** PR with intentionally-uncovered symbol fails CI; PR with covered symbol passes.

**Depends on:** all layer WPs (WP02-WP08) merged.

---

## Review gate

Each WP runs through Spec Kitty `implement` -> `review`. WP09 first, WP02-WP08 in any order or parallel, WP10 last.
