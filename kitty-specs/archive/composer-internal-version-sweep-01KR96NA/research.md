# Research: composer-internal-version-sweep-01KR96NA

This mission is bookkeeping plumbing — adding release-time tooling and a
cross-file consistency gate. There is no novel domain modeling required.
The substantive design choices already live in `plan.md`; this document
records the questions that were answered before settling on those choices.

## Decision log

| ID | Decision | Alternative considered | Rationale (short) | Detail |
|---|---|---|---|---|
| D1 | CP-NEW reference is `git describe --tags --abbrev=0 --match='v*.*.*'` | (a) repo-tracked `VERSION` file; (b) embed `version` in root composer.json | Tag is the single existing source of truth and the same value Packagist resolves `self.version` against; no new artifact to keep in sync; embedding `version` in root would conflict with the `self.version` mechanism that powers `waaseyaa/framework` consumers. | `plan.md` D1 |
| D2 | JSON round-trip via `Composer\Json\JsonFile`, not raw sed | sed-based regex rewrite | Composer's JsonFile preserves trailing commas, key order, and indentation; sed would reformat aggressively and is fragile across edge cases. JsonFile is already a transitive dependency. | `plan.md` D2 |
| D3 | Sync helper and CP-NEW gate share `bin/lib/internal-version-sync.php` | duplicate the version-resolution logic in each script | Single source of truth for `resolveCurrentVersion()` + `expectedConstraint()`. Tests live against the lib; CLI scripts get integration coverage only. | `plan.md` D3 |
| D4 | Backfill is a single mechanical commit; no per-package commits | per-package commits for granular review | The change is mechanical and atomic — splitting per package adds review noise without aiding bisect (every commit touches the same logical thing). | `plan.md` D4 |
| D5 | Branches in flight at release-cut time rebase before merging; no warn-only mode | run CP-NEW in advisory mode for one alpha cycle after each cut | Rebase-before-merge is normal hygiene in a monorepo with release-time mutations. Warn-only complicates the gate's contract for a corner case that occurs at most once per alpha. | `plan.md` D5 |

## Open questions

None blocking implementation. Two questions are deferred to WP-time
discovery rather than research:

- **Q1**: Should `bin/sync-internal-versions` accept the `v` prefix
  (`v0.1.0-alpha.176`) or require it stripped (`0.1.0-alpha.176`)?
  Resolution at WP01 time — likely accept both, normalize to stripped
  form internally to match what shows up in `composer.json` constraints.
- **Q2**: When CI's `composer-policy` job adds `fetch-tags: true`, does
  it slow down checkout enough to matter? Resolution at WP03 time —
  measure and decide whether to use `fetch-depth: 0` instead, or a
  targeted `git fetch --tags --depth=1` in the job script.

## Sources

See `research/source-register.csv` for the source register (none for this
mission — all decisions are derivable from the codebase and existing
docs) and `research/evidence-log.csv` for the evidence trail.
