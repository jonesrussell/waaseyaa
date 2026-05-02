# Plan: 1335-layer-coverage-completion

Phased implementation. Mostly parallel; two ordering constraints (WP09 first, WP10 last).

## Phase 0 — Decomposition (WP01, complete)

Output: `decomposition.md` lists 9 WPs, 3 conventions to ratify, 1 drift flag against issue #1342.

Decision: layer-by-layer with separate tooling and CI gate WPs. No SPLIT.

## Phase 1 — Tooling and convention lock (WP09)

Modernize the audit tool per Convention C1 (count `#[CoversClass]` attributes). Complete `phpstan.neon` per #1340 and #1342 using the live file as ground truth (issue #1342 overstated the gap). Document inline exclusions per Convention C3. Make audit tool respect `@internal` per Convention C2.

WP09 lands first because it defines "covered" for every downstream WP.

## Phase 2 — Layer sweep (WP02-WP08, parallel)

Each layer WP runs independently after Phase 1 merges.

| WP | Layer | Symbol count |
|----|-------|-------------:|
| WP02 | L0 foundation | 269 |
| WP03 | L1 core data | 177 |
| WP04 | L2 content types | 42 |
| WP05 | L3 services | 21 |
| WP06 | L4 API | 64 |
| WP07 | L5 AI | 70 |
| WP08 | L6 interfaces | 226 |

L0, L6, L1 are the long poles by symbol count. L3 is the shortest.

## Phase 3 — CI gate (WP10)

Add the regression gate after all layer WPs have merged. Routed through `composer verify` once architectural-remediation WP09 produces it; otherwise standalone in `.github/workflows/`.

## Mission close

When the audit tool reports 0 missing coverage symbols across L0-L6, every active PHP package is in `phpstan.neon` (or has an inline exclusion comment), and the regression gate is live.
